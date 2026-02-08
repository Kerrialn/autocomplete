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
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $class,
        private readonly string $providerName,
        private readonly callable $queryBuilder,
        private readonly string|callable|null $choiceLabel = null,
        private readonly string|callable|null $choiceValue = null,
    ) {}

    public function getName(): string
    {
        return $this->providerName;
    }

    public function search(string $query, int $limit = 10, array $selected = []): array
    {
        $em = $this->getEm();
        $repo = $this->getRepo($em);

        $qb = $this->createBaseQb($repo);
        $root = $qb->getRootAliases()[0] ?? 'e';

        $query = trim($query);

        // Apply keyword filter against choice_label when it's a string (e.g. "title")
        if ($query !== '' && is_string($this->choiceLabel) && $this->choiceLabel !== '') {
            $fieldRef = $this->resolveDqlPath($qb, $root, $this->choiceLabel);
            $qb
                ->andWhere(sprintf('LOWER(%s) LIKE :q', $fieldRef))
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        // Exclude already selected values (works when choice_value is a string field/path)
        if (!empty($selected) && is_string($this->choiceValue) && $this->choiceValue !== '') {
            $valueRef = $this->resolveDqlPath($qb, $root, $this->choiceValue);
            $qb
                ->andWhere($qb->expr()->notIn($valueRef, ':selected'))
                ->setParameter('selected', array_values($selected));
        } elseif (!empty($selected)) {
            // best-effort: exclude by single identifier (common case)
            $ids = $this->getSingleIdentifierField($em);
            if ($ids !== null) {
                $qb
                    ->andWhere($qb->expr()->notIn($root . '.' . $ids, ':selected'))
                    ->setParameter('selected', array_values($selected));
            }
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

        // Fast path: single identifier
        $idField = $this->getSingleIdentifierField($em);
        if ($idField !== null) {
            $entity = $repo->find($id);
            if ($entity) {
                return $this->normalize($entity, $em);
            }
        }

        // Otherwise query by choice_value string field/path if available
        if (is_string($this->choiceValue) && $this->choiceValue !== '') {
            $qb = $this->createBaseQb($repo);
            $root = $qb->getRootAliases()[0] ?? 'e';

            $valueRef = $this->resolveDqlPath($qb, $root, $this->choiceValue);

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
        if ($this->queryBuilder !== null) {
            // Symfony EntityType's query_builder signature: fn(EntityRepository $repo): QueryBuilder
            $qb = ($this->queryBuilder)($repo);
            if ($qb instanceof QueryBuilder) {
                return $qb;
            }
        }

        return $repo->createQueryBuilder('e');
    }

    private function normalize(object $entity, EntityManagerInterface $em): array
    {
        $id = $this->readChoiceValue($entity, $em);
        $label = $this->readChoiceLabel($entity);

        return [
            'id' => (string) $id,
            'label' => (string) $label,
        ];
    }

    private function readChoiceLabel(object $entity): string
    {
        if (is_callable($this->choiceLabel)) {
            return (string) ($this->choiceLabel)($entity);
        }

        if (is_string($this->choiceLabel) && $this->choiceLabel !== '') {
            $v = $this->readPropertyPath($entity, $this->choiceLabel);
            return $v === null ? '' : (string) $v;
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        return '';
    }

    private function readChoiceValue(object $entity, EntityManagerInterface $em): string
    {
        if (is_callable($this->choiceValue)) {
            return (string) ($this->choiceValue)($entity);
        }

        if (is_string($this->choiceValue) && $this->choiceValue !== '') {
            $v = $this->readPropertyPath($entity, $this->choiceValue);
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }

        // fallback: Doctrine identifier
        $meta = $em->getClassMetadata($this->class);
        $ids = $meta->getIdentifierValues($entity);

        if (count($ids) === 1) {
            return (string) array_values($ids)[0];
        }

        // composite id fallback
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
            $isser = 'is' . ucfirst($part);
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
     * Converts a property path like "title" or "parent.title" into a DQL ref.
     * Joins associations for dotted paths.
     */
    private function resolveDqlPath(QueryBuilder $qb, string $rootAlias, string $propertyPath): string
    {
        $parts = explode('.', $propertyPath);

        if (count($parts) === 1) {
            return $rootAlias . '.' . $parts[0];
        }

        $alias = $rootAlias;
        $joins = $qb->getDQLPart('join') ?? [];

        for ($i = 0; $i < count($parts) - 1; $i++) {
            $assoc = $parts[$i];
            $joinExpr = $alias . '.' . $assoc;

            $nextAlias = $alias . '_' . $assoc;

            // only add join once
            $already = false;
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
                $qb->addSelect($nextAlias);
                // refresh joins cache for subsequent checks
                $joins = $qb->getDQLPart('join') ?? [];
            }

            $alias = $nextAlias;
        }

        return $alias . '.' . $parts[count($parts) - 1];
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
