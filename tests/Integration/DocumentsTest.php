<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Anokii\Modules;
use App\Entity\Document;
use App\Entity\DocumentNote;
use App\Provider\AnokiiServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Documents (tool #4): the first entity-native Anokii tool. This suite covers
 * the wiring fnpi owns - module registration, routes, the registered entity
 * types, and the entity classes. The framework's own revision suite covers
 * listRevisions / setCurrentRevision / rollback / _data round-trips, and the
 * live Pi check covers the end-to-end upload + note flow.
 */
final class DocumentsTest extends TestCase
{
    #[Test]
    public function documents_is_live_in_the_registry(): void
    {
        $module = Modules::find('documents');
        $this->assertNotNull($module);
        $this->assertTrue($module['live']);
        $this->assertSame('/anokii/documents', $module['href']);
        $this->assertSame('', $module['badge']);
        $this->assertTrue($module['tile']);
    }

    #[Test]
    public function documents_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.documents', $router->match('/anokii/documents')['_route'] ?? null);
        $this->assertSame('anokii.documents.show', $router->match('/anokii/documents/abc-uuid')['_route'] ?? null);
        $this->assertSame('anokii.documents.file', $router->match('/anokii/documents/abc/file/2/preview')['_route'] ?? null);
    }

    #[Test]
    public function entity_types_register_a_revisionable_document_and_a_note(): void
    {
        /** @var list<EntityType> $types */
        $types = require dirname(__DIR__, 2) . '/config/entity-types.php';

        $byId = [];
        foreach ($types as $type) {
            $this->assertInstanceOf(EntityType::class, $type);
            $byId[$type->id()] = $type;
        }

        $this->assertArrayHasKey('document', $byId);
        $this->assertArrayHasKey('document_note', $byId);

        // The document type must be revisionable (each version is a revision)
        // with a revision key, and default-revision so every save records one.
        $document = $byId['document'];
        $this->assertTrue($document->isRevisionable(), 'document must be revisionable');
        $this->assertSame('revision_id', $document->getKeys()['revision'] ?? null);
        $this->assertSame(Document::class, $document->getClass());

        // The note thread is not revisionable.
        $this->assertFalse($byId['document_note']->isRevisionable());
        $this->assertSame(DocumentNote::class, $byId['document_note']->getClass());
    }

    #[Test]
    public function document_entity_is_revisionable_capable_and_carries_version_fields(): void
    {
        $doc = new Document();
        $doc->setTitle('CANCOM Funding and Growth Strategy')->setFolder('CANCOM')->setOwner(2, 'Matthew Owl');
        $doc->setVersion(
            sourceUri: 'public://documents/v0_ab12.docx',
            sourceFilename: 'v0.docx',
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            sizeBytes: 112136,
            previewUri: 'public://documents/v0_ab12.pdf',
            versionLabel: 'Matthew original',
            authorUid: 2,
            authorLabel: 'Matthew Owl',
        );

        // Capability comes from ContentEntityBase on alpha.191+ (no app wiring).
        $this->assertInstanceOf(RevisionableEntityInterface::class, $doc);

        $this->assertSame('CANCOM Funding and Growth Strategy', $doc->getTitle());
        $this->assertSame('CANCOM', $doc->getFolder());
        $this->assertSame('Matthew Owl', $doc->getOwnerLabel());
        $this->assertSame('public://documents/v0_ab12.docx', $doc->getSourceUri());
        $this->assertSame('public://documents/v0_ab12.pdf', $doc->getPreviewUri());
        $this->assertSame('Matthew original', $doc->getVersionLabel());
        $this->assertSame('Matthew Owl', $doc->getVersionAuthorLabel());
        // The version label is mirrored into the revision log for native tooling.
        $this->assertSame('Matthew original', $doc->getRevisionLog());
    }

    #[Test]
    public function document_note_carries_attributed_thread_fields(): void
    {
        $note = new DocumentNote();
        $note->fill('doc-uuid', 3, 'Russell Jones', 'Opening note on v2.', '2026-06-07T13:00:00Z');

        $this->assertSame('doc-uuid', $note->getDocumentUuid());
        $this->assertSame(3, $note->getAuthorUid());
        $this->assertSame('Russell Jones', $note->getAuthorLabel());
        $this->assertSame('Opening note on v2.', $note->getBody());
        $this->assertSame('2026-06-07T13:00:00Z', $note->getCreatedAt());
    }
}
