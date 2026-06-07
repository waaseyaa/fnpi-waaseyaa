<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Anokii\Modules;
use App\Drive\DriveRepository;
use App\Drive\DriveStorage;
use App\Drive\FileSchema;
use App\Drive\FileTypes;
use App\Provider\AnokiiServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Drive (tool #3): routing, the file index repository, sovereign storage via
 * the media layer, type/size helpers, and the template render.
 */
final class DriveTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    private function db(): DatabaseInterface
    {
        $db = DBALDatabase::createSqlite(':memory:');
        new FileSchema($db)->ensure();

        return $db;
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
        $this->assertSame('/anokii/drive', $drive['href']);
        $this->assertSame('', $drive['badge']);
    }

    #[Test]
    public function drive_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.drive', $router->match('/anokii/drive')['_route'] ?? null);
        $this->assertSame('anokii.drive.file', $router->match('/anokii/drive/file/abc')['_route'] ?? null);
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
    public function repository_creates_lists_and_deletes_with_attribution(): void
    {
        $repo = new DriveRepository($this->db());

        $row = $repo->create(
            name: 'China-2011.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 2_201_000,
            ownerId: 7,
            ownerLabel: 'Matthew Owl',
            folder: 'Global relationships',
            storageUri: 'public://drive/china_ab12cd34.jpg',
        );

        $this->assertSame('Matthew Owl', $row['owner_label']);
        $this->assertSame('Global relationships', $row['folder']);
        $this->assertSame('img', $row['kind']);
        $this->assertSame('2.1 MB', $row['size_human']);
        $this->assertTrue($row['is_image']);
        $this->assertSame(1, $row['version']);

        $all = $repo->all();
        $this->assertCount(1, $all);
        $this->assertContains('Global relationships', $repo->folders());

        $uri = $repo->delete((string) $row['uuid']);
        $this->assertSame('public://drive/china_ab12cd34.jpg', $uri);
        $this->assertCount(0, $repo->all());
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
        $repo = new DriveRepository($this->db());
        $repo->create('China-2011.jpg', 'image/jpeg', 2_201_000, 7, 'Matthew Owl', 'Global relationships', 'public://drive/china.jpg');

        $twig = SsrServiceProvider::getTwigEnvironment();
        $html = $twig->render('anokii/drive.html.twig', [
            'nav_active' => 'drive',
            'modules' => Modules::all(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
            'files' => $repo->all(),
            'folders' => $repo->folders(),
        ]);

        $this->assertStringContainsString('China-2011.jpg', $html);
        $this->assertStringContainsString('Matthew Owl', $html);
        $this->assertStringContainsString('Global relationships', $html);
        $this->assertStringContainsString('data-image="1"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }
}
