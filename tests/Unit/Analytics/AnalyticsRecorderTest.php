<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;

/**
 * Proves the first-party analytics pipeline end to end against an in-memory
 * SQLite: schema -> record -> report. Mirrors oiatc's recorder test.
 */
final class AnalyticsRecorderTest extends TestCase
{
    private DatabaseInterface $db;

    private AnalyticsRecorder $recorder;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite(':memory:');
        new AnalyticsSchema($this->db)->ensure();
        $this->recorder = new AnalyticsRecorder($this->db, 'test-secret');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rows(): array
    {
        $rows = [];
        foreach ($this->db->query('SELECT * FROM ' . AnalyticsSchema::TABLE, []) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    #[Test]
    public function it_records_a_valid_pageview_without_storing_raw_ip_or_ua(): void
    {
        $stored = $this->recorder->record(
            ['t' => 'pageview', 'v' => 'view-1', 'p' => '/', 'r' => 'https://www.google.com/'],
            '203.0.113.7',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        );

        $this->assertTrue($stored);
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame('pageview', $rows[0]['event_type']);
        $this->assertSame('/', $rows[0]['path']);
        $this->assertSame('www.google.com', $rows[0]['referrer_host']);
        $this->assertSame('desktop', $rows[0]['device']);
        // Cookieless: a salted hash is stored, never the raw IP/UA.
        $this->assertSame(64, strlen((string) $rows[0]['visitor_hash']));
        $this->assertStringNotContainsString('203.0.113.7', json_encode($rows[0]));
    }

    #[Test]
    public function it_rejects_bots_and_unknown_event_types(): void
    {
        $this->assertFalse($this->recorder->record(
            ['t' => 'pageview', 'v' => 'v', 'p' => '/'],
            '1.2.3.4',
            'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ));
        $this->assertFalse($this->recorder->record(['t' => 'nonsense', 'v' => 'v', 'p' => '/'], null, null));
        $this->assertCount(0, $this->rows());
    }

    #[Test]
    public function report_summary_counts_the_pageview(): void
    {
        $this->recorder->record(['t' => 'pageview', 'v' => 'view-1', 'p' => '/'], '203.0.113.7', 'Mozilla/5.0');
        $today = gmdate('Y-m-d');
        $summary = new AnalyticsReport($this->db)->summary($today, $today);

        $this->assertSame(1, $summary['totals']['views']);
        $this->assertSame(1, $summary['totals']['visitors']);
        $this->assertSame('/', $summary['pages'][0]['path']);
    }
}
