<?php

namespace Kerrialnewham\Autocomplete\Provider\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class DoctrineEntityProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    private readonly PropertyAccessorInterface $propertyAccessor;
    private readonly mixed $queryBuilder;
    private readonly mixed $choiceLabel;
    private readonly mixed $choiceValue;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $class,
        private readonly string $providerName,
        ?callable $queryBuilder = null,
        string|callable|null $choiceLabel = null,
        string|callable|null $choiceValue = null,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->queryBuilder = $queryBuilder;
        $this->choiceLabel = $choiceLabel;
        $this->choiceValue = $choiceValue;
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $em = $this->getEntityManager();
        $qb = $this->createQueryBuilder($em);

        // Apply search filter
        $this->applySearchFilter($qb, $query);

        // Filter out selected items
        if (!empty($selected)) {
            $idProperty = $this->getIdProperty();
            $qb->andWhere(sprintf('e.%s NOT IN (:selected)', $idProperty))
                ->setParameter('selected', $selected);
        }

        // Apply limit
        $qb->setMaxResults($limit);

        $entities = $qb->getQuery()->getResult();

        return array_map(fn($entity) => $this->transformEntity($entity), $entities);
    }

    public function get(string $id): ?array
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository($this->class);

        $idProperty = $this->getIdProperty();
        $entity = null;

        if ($idProperty === 'id') {
            $entity = $repository->find($id);
        } else {
            $entity = $repository->findOneBy([$idProperty => $id]);
        }

        if ($entity === null) {
            return null;
        }

        return $this->transformEntity($entity);
    }

    private function createQueryBuilder(EntityManagerInterface $em): QueryBuilder
    {
        if ($this->queryBuilder !== null) {
            $qb = ($this->queryBuilder)($em->getRepository($this->class));

            if (!$qb instanceof QueryBuilder) {
                throw new \RuntimeException(
                    sprintf('The query_builder option must return a QueryBuilder instance, got "%s".', get_debug_type($qb))
                );
            }

            return $qb;
        }

        // Default query builder
        return $em->getRepository($this->class)->createQueryBuilder('e');
    }

    private function applySearchFilter(QueryBuilder $qb, string $query): void
    {
        if ($query === '') {
            return;
        }

        $labelProperty = $this->getLabelProperty();

        if ($labelProperty === null) {
            // If no label property can be determined, search on all string fields (best effort)
            // This is a fallback for entities with __toString() or callable choice_label
            return;
        }

        // Apply LIKE search on label property
        $qb->andWhere(sprintf('LOWER(e.%s) LIKE :query', $labelProperty))
            ->setParameter('query', '%' . strtolower($query) . '%');
    }

    private function transformEntity(object $entity): array
    {
        return [
            'id' => $this->extractId($entity),
            'label' => $this->extractLabel($entity),
            'meta' => $this->extractMeta($entity),
        ];
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

    private function extractLabel(object $entity): string
    {
        if (is_callable($this->choiceLabel)) {
            return (string) ($this->choiceLabel)($entity);
        }

        if (is_string($this->choiceLabel)) {
            try {
                return (string) $this->propertyAccessor->getValue($entity, $this->choiceLabel);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    sprintf('Could not extract label from entity "%s" using property "%s": %s', $this->class, $this->choiceLabel, $e->getMessage())
                );
            }
        }

        // Fallback 1: Try __toString()
        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Fallback 2: Try common property names
        $commonProperties = ['name', 'title', 'label', 'displayName', 'fullName', 'username', 'email'];
        foreach ($commonProperties as $property) {
            try {
                if ($this->propertyAccessor->isReadable($entity, $property)) {
                    $value = $this->propertyAccessor->getValue($entity, $property);
                    if ($value !== null && $value !== '') {
                        return (string) $value;
                    }
                }
            } catch (\Exception $e) {
                // Try next property
                continue;
            }
        }

        // Last resort: Show class name + ID
        return sprintf('%s #%s', (new \ReflectionClass($entity))->getShortName(), $this->extractId($entity));
    }

    private function extractMeta(object $entity): array
    {
        // Default: no metadata
        // Developers can override by creating custom providers
        return [];
    }

    private function getIdProperty(): string
    {
        if (is_callable($this->choiceValue)) {
            return 'id'; // Fallback for callables
        }

        return $this->choiceValue ?? 'id';
    }

    private function getLabelProperty(): ?string
    {
        if (is_callable($this->choiceLabel)) {
            return null; // Can't determine property for callables
        }

        return $this->choiceLabel;
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
