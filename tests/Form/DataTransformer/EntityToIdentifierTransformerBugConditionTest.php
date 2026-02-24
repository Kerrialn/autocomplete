<?php

namespace Kerrialnewham\Autocomplete\Tests\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Form\DataTransformer\EntityToIdentifierTransformer;
use PHPUnit\Framework\TestCase;

/**
 * Bug Condition Exploration Test
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3**
 * 
 * Property 1: Fault Condition - Pre-filled Multi-Select Values Validate Successfully
 * 
 * CRITICAL: This test MUST FAIL on unfixed code - failure confirms the bug exists
 * 
 * This test encodes the EXPECTED behavior: forms with pre-filled multi-select 
 * autocomplete values (chips displayed) should validate successfully when submitted 
 * without user modification.
 * 
 * Bug Condition: input.fieldType == 'EntityType' AND input.autocomplete == true 
 *                AND input.multiple == true AND input.hasPrefilledValues == true 
 *                AND input.userModifiedValues == false
 * 
 * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS with validation errors
 * EXPECTED OUTCOME ON FIXED CODE: Test PASSES
 */
class EntityToIdentifierTransformerBugConditionTest extends TestCase
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
     * Test Case 1: Basic pre-filled submission
     * 
     * Simulates: Form with 2 pre-filled language entities, submit without changes
     * 
     * This is the core bug scenario - when chips are pre-rendered and the form
     * is submitted, the data format may be {id, label} objects instead of scalar IDs.
     */
    public function testBasicPrefilledSubmission(): void
    {
        // Create mock entities
        $entity1 = $this->createMockEntity(1, 'English');
        $entity2 = $this->createMockEntity(2, 'Spanish');

        // Configure repository to return entities by ID
        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2) {
                return match ($id) {
                    1, '1' => $entity1,
                    2, '2' => $entity2,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true // multiple mode
        );

        // Simulate pre-filled submission: The bug occurs when the submitted data
        // contains {id, label} objects instead of scalar IDs (this happens when
        // chips are pre-rendered and the form is resubmitted)
        $submittedData = [
            ['id' => '1', 'label' => 'English'],
            ['id' => '2', 'label' => 'Spanish'],
        ];

        // EXPECTED: Should successfully transform to entities
        // ON UNFIXED CODE: May fail or return empty array
        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertCount(2, $result, 'Should return 2 entities for 2 pre-filled values');
        $this->assertSame($entity1, $result[0], 'First entity should match');
        $this->assertSame($entity2, $result[1], 'Second entity should match');
    }

    /**
     * Test Case 2: Post-validation refresh scenario
     * 
     * Simulates: Trigger validation error on another field, refresh form, 
     * resubmit pre-filled values
     * 
     * This tests the scenario where a form fails validation, is refreshed,
     * and the pre-filled chips are displayed again. On resubmission, the
     * data format should still be handled correctly.
     */
    public function testPostValidationRefreshSubmission(): void
    {
        $entity1 = $this->createMockEntity(10, 'French');
        $entity2 = $this->createMockEntity(20, 'German');

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

        // After form refresh, chips are re-rendered with {id, label} format
        $submittedData = [
            ['id' => '10', 'label' => 'French'],
            ['id' => '20', 'label' => 'German'],
        ];

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Should preserve both pre-filled entities after refresh');
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
    }

    /**
     * Test Case 3: Multiple pre-filled items (5+)
     * 
     * Simulates: Form with 5+ pre-filled entities, submit without changes
     * 
     * Tests that the bug affects forms with many pre-filled items, not just
     * a few. This ensures the fix handles arrays of any size.
     */
    public function testMultiplePrefilledItems(): void
    {
        $entities = [];
        for ($i = 1; $i <= 7; $i++) {
            $entities[$i] = $this->createMockEntity($i, "Language $i");
        }

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entities) {
                $id = (int) $id;
                return $entities[$id] ?? null;
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        // Simulate 7 pre-filled items submitted as {id, label} objects
        $submittedData = [];
        for ($i = 1; $i <= 7; $i++) {
            $submittedData[] = ['id' => (string) $i, 'label' => "Language $i"];
        }

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(7, $result, 'Should handle 7 pre-filled entities');
        
        for ($i = 0; $i < 7; $i++) {
            $this->assertSame($entities[$i + 1], $result[$i], "Entity at index $i should match");
        }
    }

    /**
     * Test Case 4: Empty value mixed in with valid IDs
     * 
     * Simulates: Empty string in submitted array alongside valid IDs
     * 
     * Tests that empty values are properly filtered out and don't cause
     * validation count mismatches. This is a known edge case where the
     * submitted array may contain empty strings that should be ignored.
     */
    public function testEmptyValueMixedWithValidIds(): void
    {
        $entity1 = $this->createMockEntity(5, 'Italian');
        $entity2 = $this->createMockEntity(6, 'Portuguese');

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2) {
                return match ($id) {
                    5, '5' => $entity1,
                    6, '6' => $entity2,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        // Submitted data with empty string mixed in (can happen in edge cases)
        $submittedData = [
            ['id' => '5', 'label' => 'Italian'],
            ['id' => '', 'label' => ''],  // Empty value should be filtered out
            ['id' => '6', 'label' => 'Portuguese'],
        ];

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Should filter out empty value and return only 2 entities');
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
    }

    /**
     * Test Case 5: Mixed format - some scalar IDs, some {id, label} objects
     * 
     * This tests a potential edge case where the submitted data contains
     * a mix of scalar IDs and {id, label} objects. This could happen if
     * some values are newly selected (scalar) and some are pre-filled (objects).
     */
    public function testMixedFormatScalarAndObjects(): void
    {
        $entity1 = $this->createMockEntity(100, 'Japanese');
        $entity2 = $this->createMockEntity(101, 'Korean');
        $entity3 = $this->createMockEntity(102, 'Chinese');

        $this->repository->method('find')
            ->willReturnCallback(function ($id) use ($entity1, $entity2, $entity3) {
                return match ($id) {
                    100, '100' => $entity1,
                    101, '101' => $entity2,
                    102, '102' => $entity3,
                    default => null,
                };
            });

        $transformer = new EntityToIdentifierTransformer(
            $this->registry,
            'App\Entity\Language',
            null,
            true
        );

        // Mixed format: scalar ID + {id, label} object + scalar ID
        $submittedData = [
            '100',  // Scalar ID (newly selected)
            ['id' => '101', 'label' => 'Korean'],  // Object (pre-filled)
            '102',  // Scalar ID (newly selected)
        ];

        $result = $transformer->reverseTransform($submittedData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result, 'Should handle mixed format and return 3 entities');
        $this->assertSame($entity1, $result[0]);
        $this->assertSame($entity2, $result[1]);
        $this->assertSame($entity3, $result[2]);
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
