<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\GatingFact;
use App\Entity\VentureLane;
use App\Venture\VentureService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Validation\EntityValidationException;
use Waaseyaa\Entity\Validation\EntityValidator;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * Framework-enforced field constraints on the venture entities (alpha.204+).
 *
 * The venture types are registered via EntityType::fromClass(), so the
 * #[Field] declarations on the entity classes (scenario ints with min: 0, the
 * gating-fact status with allowed_values) become save-time constraints the
 * REPOSITORY enforces — not service code. These tests prove the enforcement
 * end to end against real sqlite storage wired with the same validator the
 * kernel uses, and prove VentureService maps a violation to its existing
 * boundary signal (null), which the controller turns into the same 422 JSON
 * shape as before. The whole-number coercion that stays app-side (numeric
 * strings from text inputs) is pinned here too, with the reason.
 */
final class VentureValidationTest extends TestCase
{
    /** @var array<string, EntityRepository> */
    private array $repos = [];

    private VentureService $service;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();

        // Same wiring AbstractKernel::bootEntityTypeManager() uses: fromClass
        // types (so the #[Field] declarations reach getFieldDefinitions())
        // and the shared default validator on every repository.
        foreach ([VentureLane::class, GatingFact::class] as $class) {
            $type = EntityType::fromClass($class, revisionable: true, revisionDefault: true);
            $handler = new SqlSchemaHandler($type, $db);
            $handler->ensureTable();
            $handler->ensureRevisionTable();
            $resolver = new SingleConnectionResolver($db);
            $this->repos[$type->id()] = new EntityRepository(
                $type,
                new SqlStorageDriver($resolver),
                new EventDispatcher(),
                new RevisionableStorageDriver($resolver, $type),
                $db,
                validator: EntityValidator::createDefault(),
            );
        }

        $etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $this->repos[$id],
        );
        foreach ([VentureLane::class, GatingFact::class] as $class) {
            $etm->registerEntityType(EntityType::fromClass($class, revisionable: true, revisionDefault: true));
        }

        $this->service = new VentureService($etm);
        $this->service->createLane(
            'technology',
            'Technology',
            'The platform lane.',
            ['worst' => [0, 0, 0, 0, 0], 'likely' => [179000, 400000, 900000, 1800000, 3060000], 'best' => [0, 0, 0, 0, 0]],
            ['Placeholder until checked against the workbook.'],
            '',
            10,
            'Seed (model mirror)',
            'Initial seed',
        );
        $this->service->createFact('faraday-test-data', 'technology', 'Independent test data', 'No test data, no government sale.', 'placeholder', 10, 'Seed', 'Initial seed');
    }

    #[Test]
    public function the_repository_itself_rejects_an_out_of_range_scenario_value(): void
    {
        // The enforcement point is the framework save, not service code: a
        // direct repository save of a negative cell throws with a violation
        // naming the field (the derived Range(min: 0) from settings.min).
        $repo = $this->repos['venture_lane'];
        $lane = $this->service->findLaneByKey('technology');
        $this->assertNotNull($lane);
        $lane->set('y1_likely', -5);

        try {
            $repo->save($lane);
            $this->fail('Expected EntityValidationException for a negative scenario value.');
        } catch (EntityValidationException $e) {
            $paths = [];
            foreach ($e->violations as $violation) {
                $paths[] = $violation->getPropertyPath();
            }
            $this->assertContains('y1_likely', $paths);
        }
    }

    #[Test]
    public function an_invalid_scenario_value_maps_to_the_boundary_error_signal_and_persists_nothing(): void
    {
        // Negative, fractional, and non-numeric all surface as the service's
        // null (the signal VenturesController::saveLane() has always mapped
        // to its {ok: false, error: ...} 422 JSON), and storage is untouched.
        foreach ([-5, 4.5, 'abc'] as $bad) {
            $result = $this->service->updateLane('technology', ['y1_likely' => $bad], 'Russell');
            $this->assertNull($result, var_export($bad, true) . ' must be rejected');
        }

        $lane = $this->service->findLaneByKey('technology');
        $this->assertNotNull($lane);
        $this->assertSame(179000, $lane->getScenarioValue(1, 'likely'), 'rejected saves must not persist');
        $this->assertCount(1, $this->service->listLaneHistory('technology'), 'rejected saves must not record a revision');
    }

    #[Test]
    public function a_valid_lane_update_is_unchanged_by_the_enforcement(): void
    {
        $result = $this->service->updateLane('technology', ['y1_likely' => 200000, 'summary' => 'Updated framing.'], 'Russell');

        $this->assertNotNull($result);
        $this->assertSame('Russell', $result['editor_label']);
        $this->assertEqualsCanonicalizing(['y1_likely', 'summary'], $result['changed']);

        $lane = $this->service->findLaneByKey('technology');
        $this->assertNotNull($lane);
        $this->assertSame(200000, $lane->getScenarioValue(1, 'likely'));
        $this->assertSame('Updated framing.', $lane->getSummary());
        $this->assertCount(2, $this->service->listLaneHistory('technology'), 'a valid edit still records a revision');
    }

    #[Test]
    public function numeric_strings_stay_accepted_via_the_kept_app_side_coercion(): void
    {
        // The entities declare no $casts, so the framework's cast-aware get()
        // does NOT coerce — a raw "40000" (text input) or whole float 40000.0
        // would fail the declared Type('int') constraint. The service keeps a
        // whole-number coercion step (coercion, not validation) so these
        // historically-accepted payloads still save; 4.5 fails the whole-int
        // equality, stays a float, and is rejected by the framework above.
        $result = $this->service->updateLane('technology', ['y2_likely' => '450000'], 'Russell');
        $this->assertNotNull($result);
        $this->assertSame(['y2_likely'], $result['changed']);

        $result = $this->service->updateLane('technology', ['y3_likely' => 950000.0], 'Russell');
        $this->assertNotNull($result);

        $lane = $this->service->findLaneByKey('technology');
        $this->assertNotNull($lane);
        $this->assertSame(450000, $lane->getScenarioValue(2, 'likely'));
        $this->assertSame(950000, $lane->getScenarioValue(3, 'likely'));
    }

    #[Test]
    public function the_status_enum_rejects_garbage_at_the_repository_and_the_boundary(): void
    {
        // Repository level: the Choice constraint derived from
        // allowed_values = GatingFact::STATUSES refuses anything else.
        $repo = $this->repos['gating_fact'];
        $fact = $this->service->findFactByKey('faraday-test-data');
        $this->assertNotNull($fact);
        $fact->setStatus('bogus');

        try {
            $repo->save($fact);
            $this->fail('Expected EntityValidationException for an unknown status.');
        } catch (EntityValidationException $e) {
            $paths = [];
            foreach ($e->violations as $violation) {
                $paths[] = $violation->getPropertyPath();
            }
            $this->assertContains('status', $paths);
        }

        // Boundary level: the service maps the violation to null (the 422
        // signal) and persists nothing.
        $this->assertNull($this->service->updateFact('faraday-test-data', 'bogus', null, 7, 'Russell'));
        $reloaded = $this->service->findFactByKey('faraday-test-data');
        $this->assertNotNull($reloaded);
        $this->assertSame('placeholder', $reloaded->getStatus());
    }

    #[Test]
    public function a_valid_confirm_flip_is_unchanged_by_the_enforcement(): void
    {
        $result = $this->service->updateFact('faraday-test-data', 'confirmed', null, 7, 'Matthew');

        $this->assertNotNull($result);
        $this->assertSame('confirmed', $result['status']);
        $this->assertSame('Matthew', $result['confirmed_by']);

        $fact = $this->service->findFactByKey('faraday-test-data');
        $this->assertNotNull($fact);
        $this->assertTrue($fact->isConfirmed());
        $this->assertSame('Matthew', $fact->getConfirmedByLabel());

        // And back to placeholder clears the stamp (the existing semantics).
        $this->assertNotNull($this->service->updateFact('faraday-test-data', 'placeholder', null, 7, 'Matthew'));
        $cleared = $this->service->findFactByKey('faraday-test-data');
        $this->assertNotNull($cleared);
        $this->assertSame('', $cleared->getConfirmedByLabel());
    }
}
