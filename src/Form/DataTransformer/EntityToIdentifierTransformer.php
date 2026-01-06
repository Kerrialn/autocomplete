<?php

namespace Kerrialnewham\Autocomplete\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EntityToIdentifierTransformer implements DataTransformerInterface
{
    private readonly PropertyAccessorInterface $propertyAccessor;
    private readonly mixed $choiceValue;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $class,
        string|callable|null $choiceValue = null,
        private readonly bool $multiple = false
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->choiceValue = $choiceValue;
    }

    /**
     * Transform entity(s) to identifier(s) for view layer (AJAX)
     */
    public function transform(mixed $value): mixed
    {
        if ($this->multiple) {
            if ($value === null) {
                return [];
            }

            if (!is_iterable($value)) {
                return [];
            }

            $ids = [];
            foreach ($value as $entity) {
                if ($entity !== null) {
                    $ids[] = $this->extractId($entity);
                }
            }

            return $ids;
        }

        // Single mode
        if ($value === null) {
            return null;
        }

        return $this->extractId($value);
    }

    /**
     * Reverse transform identifier(s) to entity(s) from view layer (form submission)
     */
    public function reverseTransform(mixed $value): mixed
    {
        if ($this->multiple) {
            if ($value === null || $value === '' || $value === []) {
                return [];
            }

            $ids = is_array($value) ? $value : [$value];
            $entities = [];

            foreach ($ids as $id) {
                if ($id === null || $id === '') {
                    continue;
                }

                try {
                    $entity = $this->findEntity($id);
                    if ($entity !== null) {
                        $entities[] = $entity;
                    } else {
                        trigger_error(
                            sprintf('Entity "%s" with ID "%s" not found, skipping.', $this->class, $id),
                            E_USER_WARNING
                        );
                    }
                } catch (\Exception $e) {
                    trigger_error(
                        sprintf('Error loading entity "%s" with ID "%s": %s', $this->class, $id, $e->getMessage()),
                        E_USER_WARNING
                    );
                }
            }

            return $entities;
        }

        // Single mode
        if ($value === null || $value === '') {
            return null;
        }

        $entity = $this->findEntity($value);

        if ($entity === null) {
            throw new TransformationFailedException(
                sprintf('Entity "%s" with ID "%s" not found.', $this->class, $value)
            );
        }

        return $entity;
    }

    private function extractId(object $entity): string
    {
        if (is_callable($this->choiceValue)) {
            return (string) ($this->choiceValue)($entity);
        }

        $property = $this->choiceValue ?? 'id';

        try {
            return (string) $this->propertyAccessor->getValue($entity, $property);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('Could not extract ID from entity "%s" using property "%s": %s', $this->class, $property, $e->getMessage())
            );
        }
    }

    private function findEntity(mixed $id): ?object
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository($this->class);

        // If custom choice_value is a callable, we need to search manually
        if (is_callable($this->choiceValue)) {
            // For callables, we can't efficiently reverse-lookup, so fall back to findAll and filter
            // This is a known limitation for complex choice_value scenarios
            foreach ($repository->findAll() as $entity) {
                if ((string) ($this->choiceValue)($entity) === (string) $id) {
                    return $entity;
                }
            }
            return null;
        }

        $property = $this->choiceValue ?? 'id';

        // For simple property-based lookup
        if ($property === 'id') {
            return $repository->find($id);
        }

        // Find by custom property
        return $repository->findOneBy([$property => $id]);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $em = $this->registry->getManagerForClass($this->class);

        if ($em === null) {
            throw new \RuntimeException(
                sprintf('No entity manager found for class "%s".', $this->class)
            );
        }

        return $em;
    }
}
