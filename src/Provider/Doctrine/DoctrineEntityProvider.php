<?php

namespace Kerrialnewham\Autocomplete\Provider\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;

final class DoctrineEntityProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    private ?\Closure $queryBuilderFactory = null;

    private ?string $choiceLabelPath = null;
    private ?\Closure $choiceLabelFn = null;
    private ?string $detectedLabelPath = null;

    private ?string $choiceValuePath = null;
    private ?\Closure $choiceValueFn = null;

    /**
     * @param mixed $queryBuilder  Expected: null or closure/fn(EntityRepository): QueryBuilder
     * @param mixed $choiceLabel   Expected: null or string property path or closure/fn(object): string
     * @param mixed $choiceValue   Expected: null or string property path or closure/fn(object): string
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $class,
        mixed $queryBuilder = null,
        mixed $choiceLabel = null,
        mixed $choiceValue = null,
    ) {
        if ($queryBuilder instanceof QueryBuilder) {
            $qb = $queryBuilder;
            $this->queryBuilderFactory = static fn () => clone $qb;
        } elseif ($queryBuilder !== null) {
            $this->queryBuilderFactory = \Closure::fromCallable($queryBuilder);
        }

        if (is_string($choiceLabel) && $choiceLabel !== '') {
            $this->choiceLabelPath = $choiceLabel;
        } elseif ($choiceLabel !== null && is_callable($choiceLabel)) {
            $this->choiceLabelFn = \Closure::fromCallable($choiceLabel);
        }

        if (is_string($choiceValue) && $choiceValue !== '') {
            $this->choiceValuePath = $choiceValue;
        } elseif ($choiceValue !== null && is_callable($choiceValue)) {
            $this->choiceValueFn = \Closure::fromCallable($choiceValue);
        }
    }

    public function search(string $query, int $limit = 10, array $selected = []): array
    {
        $em = $this->getEm();
        $repo = $this->getRepo($em);

        $qb = $this->createBaseQb($repo);
        $root = $qb->getRootAliases()[0] ?? 'e';

        $query = trim($query);
        $labelPath = $this->getEffectiveLabelPath($em);

        // keyword filter (LIKE %query%) uses the string choice_label path (e.g. "title")
        if ($query !== '' && $labelPath !== null) {
            $fieldRef = $this->resolveDqlPath($qb, $root, $labelPath);

            $qb
                ->andWhere(sprintf('LOWER(%s) LIKE :q', $fieldRef))
                ->setParameter('q', '%' . $this->lower($query) . '%');
        }

        // Exclude selected (prefer choice_value path; fallback to primary id)
        if (!empty($selected)) {
            $selected = array_values($selected);

            if ($this->choiceValuePath !== null) {
                $valueRef = $this->resolveDqlPath($qb, $root, $this->choiceValuePath);
                $qb
                    ->andWhere($qb->expr()->notIn($valueRef, ':selected'))
                    ->setParameter('selected', $selected);
            } else {
                $idField = $this->getSingleIdentifierField($em);
                if ($idField !== null) {
                    $qb
                        ->andWhere($qb->expr()->notIn($root . '.' . $idField, ':selected'))
                        ->setParameter('selected', $selected);
                }
            }
        }

        // Stable ordering by label path (if available)
        if ($labelPath !== null) {
            $qb->addOrderBy($this->resolveDqlPath($qb, $root, $labelPath), 'ASC');
        }

        $qb->setMaxResults($limit);

        $entities = $qb->getQuery()->getResult();

        $out = [];
        foreach ($entities as $entity) {
            $out[] = $this->normalize($entity, $em);
        }

        return $out;
    }

    public function get(string $id): ?array
    {
        $em = $this->getEm();
        $repo = $this->getRepo($em);

        // Fast path: entity primary key
        $entity = $repo->find($id);
        if ($entity) {
            return $this->normalize($entity, $em);
        }

        // If choiceValuePath is a non-PK field, query by it
        if ($this->choiceValuePath !== null) {
            $qb = $this->createBaseQb($repo);
            $root = $qb->getRootAliases()[0] ?? 'e';

            $valueRef = $this->resolveDqlPath($qb, $root, $this->choiceValuePath);

            $entity = $qb
                ->andWhere($qb->expr()->eq($valueRef, ':id'))
                ->setParameter('id', $id)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $entity ? $this->normalize($entity, $em) : null;
        }

        return null;
    }

    private function createBaseQb(EntityRepository $repo): QueryBuilder
    {
        if ($this->queryBuilderFactory !== null) {
            $qb = ($this->queryBuilderFactory)($repo);
            if ($qb instanceof QueryBuilder) {
                return $qb;
            }
        }

        return $repo->createQueryBuilder('e');
    }

    private function normalize(object $entity, EntityManagerInterface $em): array
    {
        return [
            'id' => (string) $this->readChoiceValue($entity, $em),
            'label' => (string) $this->readChoiceLabel($entity),
        ];
    }

    private function readChoiceLabel(object $entity): string
    {
        if ($this->choiceLabelFn !== null) {
            return (string) ($this->choiceLabelFn)($entity);
        }

        if ($this->choiceLabelPath !== null) {
            $v = $this->readPropertyPath($entity, $this->choiceLabelPath);
            return $v === null ? '' : (string) $v;
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Auto-detect a string column from Doctrine metadata
        $labelPath = $this->getEffectiveLabelPath($this->getEm());
        if ($labelPath !== null) {
            $v = $this->readPropertyPath($entity, $labelPath);
            return $v === null ? '' : (string) $v;
        }

        return '';
    }

    private function readChoiceValue(object $entity, EntityManagerInterface $em): string
    {
        if ($this->choiceValueFn !== null) {
            return (string) ($this->choiceValueFn)($entity);
        }

        if ($this->choiceValuePath !== null) {
            $v = $this->readPropertyPath($entity, $this->choiceValuePath);
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }

        $meta = $em->getClassMetadata($this->class);
        $ids = $meta->getIdentifierValues($entity);

        if (count($ids) === 1) {
            return (string) array_values($ids)[0];
        }

        return (string) json_encode($ids);
    }

    private function readPropertyPath(object $obj, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $obj;

        foreach ($parts as $part) {
            if ($current === null) {
                return null;
            }

            $getter = 'get' . ucfirst($part);
            $isser  = 'is' . ucfirst($part);
            $hasser = 'has' . ucfirst($part);

            if (is_object($current) && method_exists($current, $getter)) {
                $current = $current->{$getter}();
                continue;
            }
            if (is_object($current) && method_exists($current, $isser)) {
                $current = $current->{$isser}();
                continue;
            }
            if (is_object($current) && method_exists($current, $hasser)) {
                $current = $current->{$hasser}();
                continue;
            }
            if (is_object($current) && property_exists($current, $part)) {
                $current = $current->{$part};
                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * "title" -> "e.title"
     * "parent.title" -> joins e.parent as e_parent and returns "e_parent.title"
     */
    private function resolveDqlPath(QueryBuilder $qb, string $rootAlias, string $propertyPath): string
    {
        $parts = explode('.', $propertyPath);

        if (count($parts) === 1) {
            return $rootAlias . '.' . $parts[0];
        }

        $alias = $rootAlias;

        for ($i = 0; $i < count($parts) - 1; $i++) {
            $assoc = $parts[$i];
            $joinExpr = $alias . '.' . $assoc;
            $nextAlias = $alias . '_' . $assoc;

            $already = false;
            $joins = $qb->getDQLPart('join');

            if (isset($joins[$alias])) {
                foreach ($joins[$alias] as $j) {
                    if ($j->getJoin() === $joinExpr && $j->getAlias() === $nextAlias) {
                        $already = true;
                        break;
                    }
                }
            }

            if (!$already) {
                $qb->leftJoin($joinExpr, $nextAlias);
            }

            $alias = $nextAlias;
        }

        return $alias . '.' . $parts[count($parts) - 1];
    }

    /**
     * Returns the label column path to use for searching/ordering.
     * If choiceLabelPath is set, returns that. Otherwise auto-detects
     * a suitable string column from Doctrine metadata (prefers name/title/label).
     */
    private function getEffectiveLabelPath(EntityManagerInterface $em): ?string
    {
        if ($this->choiceLabelPath !== null) {
            return $this->choiceLabelPath;
        }

        // If a closure is set, we can't derive a DQL path from it
        if ($this->choiceLabelFn !== null) {
            return null;
        }

        if ($this->detectedLabelPath !== null) {
            return $this->detectedLabelPath;
        }

        try {
            $meta = $em->getClassMetadata($this->class);
        } catch (\Exception) {
            return null;
        }

        $preferred = ['name', 'title', 'label'];
        $firstString = null;

        foreach ($meta->getFieldNames() as $field) {
            $type = $meta->getTypeOfField($field);
            if (!\in_array($type, ['string', 'text'], true)) {
                continue;
            }

            if ($firstString === null) {
                $firstString = $field;
            }

            if (\in_array($field, $preferred, true)) {
                $this->detectedLabelPath = $field;
                return $this->detectedLabelPath;
            }
        }

        if ($firstString !== null) {
            $this->detectedLabelPath = $firstString;
            return $this->detectedLabelPath;
        }

        return null;
    }

    private function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }

    private function getEm(): EntityManagerInterface
    {
        $em = $this->registry->getManagerForClass($this->class);
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine ORM EntityManager found for "%s".', $this->class));
        }
        return $em;
    }

    private function getRepo(EntityManagerInterface $em): EntityRepository
    {
        $repo = $em->getRepository($this->class);
        if (!$repo instanceof EntityRepository) {
            throw new \LogicException(sprintf('Repository for "%s" is not an EntityRepository.', $this->class));
        }
        return $repo;
    }

    private function getSingleIdentifierField(EntityManagerInterface $em): ?string
    {
        $meta = $em->getClassMetadata($this->class);
        $ids = $meta->getIdentifierFieldNames();
        return count($ids) === 1 ? $ids[0] : null;
    }
}
