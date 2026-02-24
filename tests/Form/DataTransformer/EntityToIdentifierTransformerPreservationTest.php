<?php

namespace Kerrialnewham\Autocomplete\Tests\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Form\DataTransformer\EntityToIdentifierTransformer;
use PHPUnit\Framework\TestCase;

/**
 * Preservation Property Tests
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
 * 
 * Property 2: Preservation - Newly Selected and Modified Values Continue Working
 * 
 * IMPORTANT: These tests capture the BASELINE behavior on UNFIXED code for 
 * non-buggy inputs (cases where isBugCondition returns false).
 * 
 * These tests verify that for all inputs where the bug condition does NOT hold,
 * the transformation and validation produce correct results. After the fix is
 * implemented, these same tests must continue to pass to ensure no regressions.
 * 
 * Non-buggy inputs include:
 * - Newly selected values (scalar IDs from dropdown selection)
 * - Modified selections (adding/removing chips)
 * - Single-select autocomplete fields
 * - Empty value filtering
 * 
 * EXPECTED OUTCOME ON UNFIXED CODE: Tests PASS (confirms baseline behavior)
 * EXPECTED OUTCOME ON FIXED CODE: Tests PASS (confirms no regressions)
 */
class EntityToIdentifierTransformerPreservationTest extends TestCase
{
    private ManagerRegistry $registry;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    protected function setUp(): void
    {
        // Create mock entities for testing
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $this->registry->method('getManagerForClass')
            ->willReturn($this->entityManager);
        
        $this->entityManager->method('getRepository')
            ->willReturn($this->repository);
    }

    /**
     * Property Test 1: Newly Selected Values Work Correctly
     * 
     * **Validates: Requirement 3.1**
     * 
     * When a multi-select autocomplete form field has no pre-filled values AND 
     * the user selects new values from the dropdown, the system SHALL validate 
     * and accept the newly selected values correctly.
     * 
     * This tests the baseline behavior: when users select values from the dropdown,
     * the submitted data contains scalar IDs (strings), not {id, label} objects.
     * This is the normal, working case that must be preserved.
     */
    public function testNewlySelectedValuesWithScalarIds(): void
    {
        $entity1 = $this->createMockEntity(1, 'English');
        $entity2 = $this->createMockEntity(2, 'Spanish');
        $entity3 = $this->createMockEntity(3, 'French');

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2, $entity3) {
                return match ($id) {
                    1, '1' => $entity1,
                    2, '2' => $entity2,
                    3, '3' => $entity3,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true // multiple mode
        );

        // Newly selected values are submitted as scalar IDs (strings)
        $submittedData = ['1', '2', '3'];

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result, 'Should return 3 entities for 3 newly selected values');
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
        $this->assertSame($entity3, $result[2]);
    }

    /**
     * Property Test 2: Newly Selected Values with Various Counts
     * 
     * **Validates: Requirement 3.1**
     * 
     * Tests that newly selected values work correctly regardless of how many
     * items are selected (1, 2, 5, 10, etc.). This is property-based thinking:
     * the behavior should be consistent across different input sizes.
     */
    public function testNewlySelectedValuesVariousCounts(): void
    {
        $counts = [1, 2, 5, 10];

        foreach ($counts as $count) {
            $entities = [];
            for ($i = 1; $i <= $count; $i++) {
                $entities[$i] = $this->createMockEntity($i, "Language $i");
            }

            $this->repository = $this->createMock(EntityRepository::class);
            $this->repository->method('find')
                ->willReturnCallback(function ($id) use ($entities) {
                    $id = (int) $id;
                    return $entities[$id] ?? null;
                });

            $this->entityManager = $this->createMock(EntityManagerInterface::class);
            $this->entityManager->method('getRepository')
                ->willReturn($this->repository);

            $this->registry = $this->createMock(ManagerRegistry::class);
            $this->registry->method('getManagerForClass')
                ->willReturn($this->entityManager);

            $transformer = new EntityToIdentifierTransformer(
                $this->registry,
                'App\Entity\Language',
                null,
                true
            );

            // Generate scalar IDs for newly selected values
            $submittedData = array_map('strval', range(1, $count));

            $result = $transformer->reverseTransform($submittedData);

            $this->assertIsArray($result, "Should return array for count=$count");
            $this->assertCount($count, $result, "Should return $count entities for $count newly selected values");
            
            for ($i = 0; $i < $count; $i++) {
                $this->assertSame($entities[$i + 1], $result[$i], "Entity at index $i should match for count=$count");
            }
        }
    }

    /**
     * Property Test 3: Single-Select Autocomplete Works Correctly
     * 
     * **Validates: Requirement 3.4**
     * 
     * When a single-select autocomplete form field (not multi-select) has a 
     * pre-filled value AND the form is submitted, the system SHALL validate 
     * and process the submission correctly.
     * 
     * Single-select mode is different from multi-select: it submits a single
     * scalar ID, not an array. This behavior must be preserved.
     */
    public function testSingleSelectPrefilledValue(): void
    {
        $entity = $this->createMockEntity(42, 'German');

        $this->repository->method('find')
            ->willReturn($entity);

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            false // single-select mode
        );

        // Single-select submits a scalar ID, not an array
        $submittedData = '42';

        $result = $transformer->reverseTransform($submittedData);

        $this->assertNotNull($result, 'Should return an entity for single-select');
        $this->assertSame($entity, $result, 'Should return the correct entity');
    }

    /**
     * Property Test 4: Single-Select with Various IDs
     * 
     * **Validates: Requirement 3.4**
     * 
     * Tests that single-select works correctly with different ID values,
     * including numeric and string IDs.
     */
    public function testSingleSelectVariousIds(): void
    {
        $testIds = [1, 5, 10, 100, 999];

        foreach ($testIds as $testId) {
            $entity = $this->createMockEntity($testId, "Language $testId");

            $this->repository = $this->createMock(EntityRepository::class);
            $this->repository->method('find')
                ->willReturn($entity);

            $this->entityManager = $this->createMock(EntityManagerInterface::class);
            $this->entityManager->method('getRepository')
                ->willReturn($this->repository);

            $this->registry = $this->createMock(ManagerRegistry::class);
            $this->registry->method('getManagerForClass')
                ->willReturn($this->entityManager);

            $transformer = new EntityToIdentifierTransformer(
                $this->registry,
                'App\Entity\Language',
                null,
                false
            );

            $result = $transformer->reverseTransform((string) $testId);

            $this->assertNotNull($result, "Should return entity for ID=$testId");
            $this->assertSame($entity, $result, "Should return correct entity for ID=$testId");
        }
    }

    /**
     * Property Test 5: Empty Value Filtering in Multi-Select
     * 
     * **Validates: Requirement 3.5**
     * 
     * When empty strings or null values are submitted in a multi-select 
     * autocomplete field array, the system SHALL filter them out before 
     * validation to prevent count mismatch errors.
     * 
     * This is critical baseline behavior: the transformer already filters
     * out empty values, and this must continue to work after the fix.
     */
    public function testEmptyValueFiltering(): void
    {
        $entity1 = $this->createMockEntity(10, 'Italian');
        $entity2 = $this->createMockEntity(20, 'Portuguese');

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2) {
                return match ($id) {
                    10, '10' => $entity1,
                    20, '20' => $entity2,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        // Submitted data with empty strings mixed in (scalar IDs, not objects)
        $submittedData = ['10', '', '20', null, ''];

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Should filter out empty values and return only 2 entities');
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
    }

    /**
     * Property Test 6: Empty Value Filtering with Various Patterns
     * 
     * **Validates: Requirement 3.5**
     * 
     * Tests that empty value filtering works correctly with different patterns
     * of empty values (leading, trailing, multiple consecutive empties).
     */
    public function testEmptyValueFilteringVariousPatterns(): void
    {
        $entity1 = $this->createMockEntity(1, 'Language 1');
        $entity2 = $this->createMockEntity(2, 'Language 2');
        $entity3 = $this->createMockEntity(3, 'Language 3');

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2, $entity3) {
                return match ($id) {
                    1, '1' => $entity1,
                    2, '2' => $entity2,
                    3, '3' => $entity3,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        $testCases = [
            // [submitted data, expected count, description]
            [['', '1', '2'], 2, 'Leading empty'],
            [['1', '', '2'], 2, 'Middle empty'],
            [['1', '2', ''], 2, 'Trailing empty'],
            [['', '', '1', '', '2', '', ''], 2, 'Multiple empties'],
            [['1', null, '2', '', '3'], 3, 'Mixed null and empty strings'],
        ];

        foreach ($testCases as [$submittedData, $expectedCount, $description]) {
            $result = $transformer->reverseTransform($submittedData);

            $this->assertIsArray($result, "Should return array for: $description");
            $this->assertCount($expectedCount, $result, "Should return $expectedCount entities for: $description");
        }
    }

    /**
     * Property Test 7: Null and Empty Array Handling
     * 
     * **Validates: Requirement 3.5**
     * 
     * Tests that null values and empty arrays are handled correctly in
     * multi-select mode (should return empty array, not cause errors).
     */
    public function testNullAndEmptyArrayHandling(): void
    {
        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        // Test null input
        $result = $transformer->reverseTransform(null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Null input should return empty array');

        // Test empty array input
        $result = $transformer->reverseTransform([]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Empty array input should return empty array');

        // Test empty string input
        $result = $transformer->reverseTransform('');
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Empty string input should return empty array');
    }

    /**
     * Property Test 8: Transform Direction (Entity to ID)
     * 
     * **Validates: Requirements 3.1, 3.2, 3.3**
     * 
     * Tests the forward transformation (entity to ID) to ensure it continues
     * to work correctly. This is used when rendering the form with pre-filled
     * values. The transform() method should return scalar IDs, not {id, label} objects.
     */
    public function testTransformEntityToIdMultiSelect(): void
    {
        $entity1 = $this->createMockEntity(100, 'Japanese');
        $entity2 = $this->createMockEntity(101, 'Korean');

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        $result = $transformer->transform([$entity1, $entity2]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('100', $result[0], 'Should return scalar ID string');
        $this->assertEquals('101', $result[1], 'Should return scalar ID string');
    }

    /**
     * Property Test 9: Transform Direction Single-Select
     * 
     * **Validates: Requirement 3.4**
     * 
     * Tests the forward transformation for single-select mode.
     */
    public function testTransformEntityToIdSingleSelect(): void
    {
        $entity = $this->createMockEntity(42, 'Chinese');

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            false
        );

        $result = $transformer->transform($entity);

        $this->assertEquals('42', $result, 'Should return scalar ID string for single-select');
    }

    /**
     * Property Test 10: Transform with Null Values
     * 
     * **Validates: Requirements 3.1, 3.4**
     * 
     * Tests that transform() handles null values correctly in both modes.
     */
    public function testTransformWithNullValues(): void
    {
        // Multi-select mode
        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        $result = $transformer->transform(null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Null should transform to empty array in multi-select');

        // Single-select mode
        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            false
        );

        $result = $transformer->transform(null);
        $this->assertNull($result, 'Null should transform to null in single-select');
    }

    /**
     * Helper method to create a mock entity with ID and label
     */
    private function createMockEntity(int $id, string $label): object
    {
        return new class($id, $label) {
            public function __construct(
                private int $id,
                private string $label
            ) {}

            public function getId(): int
            {
                return $this->id;
            }

            public function getLabel(): string
            {
                return $this->label;
            }

            public function __toString(): string
            {
                return $this->label;
            }
        };
    }
}
