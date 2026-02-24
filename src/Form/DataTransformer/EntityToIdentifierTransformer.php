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
     * Transform entity(s) to identifier(s) for view layer.
     *
     * For multiple mode, returns an array of ['id' => ..., 'label' => ...] items
     * so that chip templates can render hidden inputs with the correct value.
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

            // Return scalar IDs only — Symfony's ChoiceType expects value
            // to be an array of strings. The {id, label} items for chip
            // rendering are built separately in buildView (selected_items).
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
        // Debug logging to track incoming data format
        if ($_ENV['APP_DEBUG'] ?? false) {
            trigger_error(
                sprintf(
                    '[EntityToIdentifierTransformer] reverseTransform called for class "%s", multiple=%s, value type=%s, value=%s',
                    $this->class,
                    $this->multiple ? 'true' : 'false',
                    get_debug_type($value),
                    json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                ),
                E_USER_NOTICE
            );
        }

        if ($this->multiple) {
            if ($value === null || $value === '' || $value === []) {
                return [];
            }

            $ids = is_array($value) ? $value : [$value];
            
            // Normalize submitted data format: handle {id, label} objects that may be
            // submitted when chips are pre-rendered and the form is resubmitted
            $normalizedIds = [];
            foreach ($ids as $id) {
                // Handle nested array structures and extract scalar IDs
                if (\is_array($id)) {
                    // Extract 'id' field from {id, label} objects
                    if (isset($id['id'])) {
                        $extractedId = $id['id'];
                        
                        // Handle case where id['id'] itself is an array (nested structure)
                        while (\is_array($extractedId) && isset($extractedId['id'])) {
                            $extractedId = $extractedId['id'];
                        }
                        
                        $id = $extractedId;
                    } else {
                        // If no 'id' key, skip this entry
                        continue;
                    }
                }
                
                // Filter out empty values early to prevent validation count mismatches
                if ($id === null || $id === '' || $id === []) {
                    continue;
                }
                
                // Ensure we have a scalar value
                if (\is_array($id)) {
                    trigger_error(
                        sprintf(
                            '[EntityToIdentifierTransformer] Skipping non-scalar ID after normalization for class "%s": %s',
                            $this->class,
                            json_encode($id, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                        ),
                        E_USER_WARNING
                    );
                    continue;
                }
                
                $normalizedIds[] = $id;
            }
            
            // Debug logging for normalized IDs
            if ($_ENV['APP_DEBUG'] ?? false) {
                trigger_error(
                    sprintf(
                        '[EntityToIdentifierTransformer] Normalized IDs for class "%s": %s',
                        $this->class,
                        json_encode($normalizedIds, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    ),
                    E_USER_NOTICE
                );
            }
            
            $entities = [];
            foreach ($normalizedIds as $id) {
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

            // Debug logging for final result
            if ($_ENV['APP_DEBUG'] ?? false) {
                trigger_error(
                    sprintf(
                        '[EntityToIdentifierTransformer] reverseTransform result for class "%s": %d entities loaded from %d IDs',
                        $this->class,
                        count($entities),
                        count($normalizedIds)
                    ),
                    E_USER_NOTICE
                );
            }

            return $entities;
        }

        // Single mode
        if ($value === null || $value === '') {
            return null;
        }
        
        // Handle {id, label} object in single mode
        if (\is_array($value)) {
            if (isset($value['id'])) {
                $value = $value['id'];
                
                // Handle nested structure
                while (\is_array($value) && isset($value['id'])) {
                    $value = $value['id'];
                }
            } else {
                throw new TransformationFailedException(
                    sprintf('Invalid array value for single-select EntityType "%s": %s', $this->class, json_encode($value))
                );
            }
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
