<?php

declare(strict_types=1);

namespace App\Command;

use App\Venture\VentureSeed;
use App\Venture\VentureService;
use Waaseyaa\CLI\CliIO;

/**
 * Seed the Venture Numbers section: six lanes, their gating facts, and the
 * provenance snapshot, from the checked-in VentureSeed data (mirrored from
 * the modeling workbook, every figure placeholder-grade).
 *
 * Idempotent: a lane, fact, or snapshot that already exists is skipped, so
 * re-running after staff edits never overwrites entered numbers.
 */
final class SeedVenturesCommand
{
    /**
     * Attribution label for seeded rows. The acting uid is the framework's
     * revision_author (null under the CLI — no acting account context).
     */
    private const SEED_LABEL = 'Seed (model mirror)';

    public function __construct(private readonly VentureService $ventures) {}

    public function run(CliIO $io): int
    {
        $created = 0;

        foreach (VentureSeed::lanes() as $i => $lane) {
            if ($this->ventures->findLaneByKey($lane['key']) !== null) {
                $io->writeln(sprintf('  skip   lane %s exists', $lane['key']));
                continue;
            }
            $this->ventures->createLane(
                $lane['key'],
                $lane['title'],
                $lane['summary'],
                $lane['grid'],
                $lane['assumptions'],
                $lane['notes'],
                ($i + 1) * 10,
                self::SEED_LABEL,
                'Seeded from ' . VentureSeed::MODEL_VERSION . ' (placeholder-grade)',
            );
            $io->writeln(sprintf('  seed   lane %s', $lane['key']));
            $created++;
        }

        foreach (VentureSeed::facts() as $i => $fact) {
            if ($this->ventures->findFactByKey($fact['key']) !== null) {
                $io->writeln(sprintf('  skip   fact %s exists', $fact['key']));
                continue;
            }
            $this->ventures->createFact(
                $fact['key'],
                $fact['lane_key'],
                $fact['label'],
                $fact['detail'],
                'placeholder',
                ($i + 1) * 10,
                self::SEED_LABEL,
                'Seeded as placeholder',
            );
            $io->writeln(sprintf('  seed   fact %s', $fact['key']));
            $created++;
        }

        if ($this->ventures->snapshot() === null) {
            $this->ventures->createSnapshot(
                VentureSeed::AS_OF,
                VentureSeed::MODEL_VERSION,
                VentureSeed::SNAPSHOT_NOTE,
                self::SEED_LABEL,
            );
            $io->writeln('  seed   snapshot ' . VentureSeed::AS_OF);
            $created++;
        } else {
            $io->writeln('  skip   snapshot exists');
        }

        $io->writeln($created === 0 ? '  done   nothing to seed.' : sprintf('  done   %d rows seeded.', $created));

        return 0;
    }
}
