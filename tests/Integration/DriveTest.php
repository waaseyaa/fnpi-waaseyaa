<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Anokii\Modules;
use App\Entity\DriveFile;
use App\Provider\AnokiiServiceProvider;
use App\Drive\DriveStorage;
use App\Drive\FileTypes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Drive (tool #3), entity-native rebuild. Covers routing, the type/size helpers,
 * sovereign storage via the media layer, the registered entity type, the
 * DriveFile entity, and the template render. The framework revision suite covers
 * listRevisions / _data round-trips; the live Pi check covers the migration of
 * the real files.
 */
final class DriveTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/drive_test_' . bin2hex(random_bytes(6));
        mkdir($dir, 0o755, true);

        return $dir;
    }

    #[Test]
    public function drive_is_live_in_the_registry(): void
    {
        $drive = Modules::find('drive');
        $this->assertNotNull($drive);
        $this->assertTrue($drive['live']);
        $this->assertSame('/admin/anokii/drive', $drive['href']);
    }

    #[Test]
    public function drive_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.drive', $router->match('/admin/anokii/drive')['_route'] ?? null);
        $this->assertSame('anokii.drive.file', $router->match('/admin/anokii/drive/file/abc')['_route'] ?? null);
    }

    #[Test]
    public function entity_types_register_a_revisionable_drive_asset(): void
    {
        /** @var list<EntityType> $types */
        $types = require dirname(__DIR__, 2) . '/config/entity-types.php';
        $byId = [];
        foreach ($types as $type) {
            $byId[$type->id()] = $type;
        }

        $this->assertArrayHasKey('drive_asset', $byId);
        $this->assertTrue($byId['drive_asset']->isRevisionable(), 'drive_asset must be revisionable');
        $this->assertSame('revision_id', $byId['drive_asset']->getKeys()['revision'] ?? null);
        $this->assertSame(DriveFile::class, $byId['drive_asset']->getClass());
    }

    #[Test]
    public function drive_file_entity_is_revisionable_and_carries_fields(): void
    {
        $file = new DriveFile();
        $file->fill(
            name: 'China-2011.jpg',
            mimeType: 'image/jpeg',
            kind: 'img',
            sizeBytes: 2_201_000,
            ownerUid: 7,
            ownerLabel: 'Matthew Owl',
            folder: 'Global relationships',
            storageUri: 'public://drive/china_ab12cd34.jpg',
            uploadedAt: '2026-06-07 12:00:00',
            editorLabel: 'Matthew Owl',
            updatedAt: '2026-06-07 12:00:00',
        );
        $file->recordEdit('Imported from prototype');

        $this->assertInstanceOf(RevisionableEntityInterface::class, $file);
        $this->assertSame('China-2011.jpg', $file->getName());
        $this->assertSame('img', $file->getKind());
        $this->assertSame('Matthew Owl', $file->getOwnerLabel());
        $this->assertSame('Global relationships', $file->getFolder());
        $this->assertSame('public://drive/china_ab12cd34.jpg', $file->getStorageUri());
        $this->assertSame('2026-06-07 12:00:00', $file->getUploadedAt());
        $this->assertSame('Imported from prototype', $file->getRevisionLog());
    }

    #[Test]
    public function file_types_map_mime_and_extension_to_icon_kind(): void
    {
        $this->assertSame('img', FileTypes::kind('image/jpeg', 'china.jpg'));
        $this->assertSame('pdf', FileTypes::kind('application/pdf', 'report.pdf'));
        $this->assertSame('xls', FileTypes::kind('text/csv', 'data.csv'));
        $this->assertSame('img', FileTypes::kind('application/octet-stream', 'photo.HEIC'));
        $this->assertSame('gen', FileTypes::kind('application/octet-stream', 'thing.bin'));
    }

    #[Test]
    public function human_size_formats_bytes(): void
    {
        $this->assertSame('512 B', FileTypes::humanSize(512));
        $this->assertSame('1.0 KB', FileTypes::humanSize(1024));
        $this->assertSame('2.1 MB', FileTypes::humanSize(2_201_000));
    }

    #[Test]
    public function storage_stores_resolves_and_deletes_bytes_via_media_layer(): void
    {
        $dir = $this->tempDir();
        $storage = new DriveStorage($dir, ['text/plain'], 1024 * 1024);

        $source = $dir . '/source.txt';
        file_put_contents($source, 'sovereign bytes');

        $file = $storage->store($source, 'notes.txt', 7);
        $this->assertStringStartsWith('public://drive/', $file->uri);
        $this->assertSame(7, $file->ownerId);
        $this->assertSame(15, $file->size);

        $path = $storage->pathForUri($file->uri);
        $this->assertNotNull($path);
        $this->assertSame('sovereign bytes', file_get_contents($path));

        $storage->delete($file->uri);
        $this->assertNull($storage->pathForUri($file->uri));
    }

    #[Test]
    public function storage_rejects_disallowed_mime_type(): void
    {
        $dir = $this->tempDir();
        $storage = new DriveStorage($dir, ['image/png'], 1024 * 1024);
        $source = $dir . '/source.txt';
        file_put_contents($source, 'nope');

        $this->expectException(\InvalidArgumentException::class);
        $storage->store($source, 'notes.txt', 1);
    }

    #[Test]
    public function drive_template_renders_files_and_folders(): void
    {
        // Build a presented row in the controller's shape (no database needed).
        $row = [
            'uuid' => 'abc-uuid', 'name' => 'China-2011.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'img', 'size_bytes' => 2_201_000, 'size_human' => '2.1 MB', 'is_image' => true,
            'owner_label' => 'Matthew Owl', 'folder' => 'Global relationships',
            'uploaded_at' => '2026-06-07 12:00:00', 'version' => 1,
            'view_url' => '/admin/anokii/drive/file/abc-uuid', 'download_url' => '/admin/anokii/drive/file/abc-uuid?dl=1',
        ];

        $twig = SsrServiceProvider::getTwigEnvironment();
        $html = $twig->render('anokii/drive.html.twig', [
            'nav_active' => 'drive',
            'modules' => Modules::all(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
            'files' => [$row],
            'folders' => ['Global relationships'],
        ]);

        $this->assertStringContainsString('China-2011.jpg', $html);
        $this->assertStringContainsString('Matthew Owl', $html);
        $this->assertStringContainsString('Global relationships', $html);
        $this->assertStringContainsString('data-image="1"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }
}
