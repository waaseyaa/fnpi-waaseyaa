<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Anokii\Modules;
use App\Entity\Pillar;
use App\Provider\AnokiiServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Identity Workspace (tool #1), entity-native rebuild. This suite covers the
 * wiring fnpi owns - module registration, routes, the registered entity type,
 * and the Pillar entity (revisionable capability + faithful field carriage,
 * including long notes). The framework's own revision suite covers
 * listRevisions / _data round-trips; the live Pi check covers the end-to-end
 * migrate + edit flow against the real data.
 */
final class IdentityPillarTest extends TestCase
{
    #[Test]
    public function identity_is_live_in_the_registry(): void
    {
        $module = Modules::find('identity');
        $this->assertNotNull($module);
        $this->assertTrue($module['live']);
        $this->assertSame('/anokii/identity', $module['href']);
    }

    #[Test]
    public function identity_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.identity', $router->match('/anokii/identity')['_route'] ?? null);
        $this->assertSame('anokii.identity.history', $router->match('/anokii/identity/purpose/history')['_route'] ?? null);
    }

    #[Test]
    public function entity_types_register_a_revisionable_identity_pillar(): void
    {
        /** @var list<EntityType> $types */
        $types = require dirname(__DIR__, 2) . '/config/entity-types.php';

        $byId = [];
        foreach ($types as $type) {
            $this->assertInstanceOf(EntityType::class, $type);
            $byId[$type->id()] = $type;
        }

        $this->assertArrayHasKey('identity_pillar', $byId);
        $pillar = $byId['identity_pillar'];
        $this->assertTrue($pillar->isRevisionable(), 'identity_pillar must be revisionable');
        $this->assertSame('revision_id', $pillar->getKeys()['revision'] ?? null);
        $this->assertSame(Pillar::class, $pillar->getClass());
    }

    #[Test]
    public function pillar_entity_is_revisionable_capable_and_carries_fields(): void
    {
        // A long notes block (the kind Matthew pastes, e.g. the ~4000 char moat
        // notes) must round-trip intact through the _data blob.
        $longNotes = str_repeat('Sovereignty is the purpose, the qualification is the moat. ', 80);
        $this->assertGreaterThan(4000, strlen($longNotes));

        $pillar = new Pillar();
        $pillar->fill(
            pid: 'moat',
            section: 'positioning',
            title: 'Differentiation / moat',
            nowLabel: 'Now',
            body: 'Four layers, only FNPI has all four.',
            isQuote: false,
            decideLabel: 'Keep',
            decision: 'Carry it through every channel.',
            status: 'defined',
            notes: $longNotes,
            pills: [['t' => 'FNPI', 'cyan' => true], ['t' => 'Sovereignty', 'cyan' => false]],
            isFull: false,
            sortOrder: 7,
            editorLabel: 'Matthew',
            updatedAt: '2026-06-07T00:38:04Z',
        );
        $pillar->recordEdit('Imported from prototype');

        // Capability comes from ContentEntityBase on alpha.191+ (no app wiring).
        $this->assertInstanceOf(RevisionableEntityInterface::class, $pillar);

        $this->assertSame('moat', $pillar->getPid());
        $this->assertSame('positioning', $pillar->getSection());
        $this->assertSame('Differentiation / moat', $pillar->getTitle());
        $this->assertSame('defined', $pillar->getStatus());
        $this->assertSame($longNotes, $pillar->getNotes(), 'long notes survive intact');
        $this->assertFalse($pillar->isQuote());
        $this->assertSame(7, $pillar->getSortOrder());
        $this->assertSame('Matthew', $pillar->getEditorLabel());
        $this->assertSame('2026-06-07T00:38:04Z', $pillar->getUpdatedAt());

        $pills = $pillar->getPills();
        $this->assertCount(2, $pills);
        $this->assertSame('FNPI', $pills[0]['t']);
        $this->assertTrue($pills[0]['cyan']);
        $this->assertFalse($pills[1]['cyan']);

        // The edit summary is mirrored into the revision log for native tooling.
        $this->assertSame('Imported from prototype', $pillar->getRevisionLog());
    }

    #[Test]
    public function pillar_status_edit_updates_status_and_records_a_log(): void
    {
        $pillar = new Pillar();
        $pillar->fill(
            pid: 'purpose', section: 'foundation', title: 'Purpose', nowLabel: 'Now', body: '',
            isQuote: false, decideLabel: 'Decide', decision: '', status: 'gap', notes: '',
            pills: [], isFull: false, sortOrder: 1, editorLabel: '', updatedAt: '',
        );

        $pillar->setStatus('work')->setEditorLabel('Russell')->recordEdit('Status set to work');

        $this->assertSame('work', $pillar->getStatus());
        $this->assertSame('Russell', $pillar->getEditorLabel());
        // The acting uid is no longer app data: the framework records it as
        // revision_author on save (RevisionAuthorProvenanceTest covers it).
        // With no revision metadata and no legacy snapshot, the fallback is 0.
        $this->assertSame(0, $pillar->getEditorUid());
        $this->assertSame('Status set to work', $pillar->getRevisionLog());
    }
}
