<?php

namespace App\Services;

use App\Models\Central\DrTest;
use App\Models\Central\Mutual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Automated backup runner (Phase 3) that closes the loop with the DR log: every
 * run is recorded as a DrTest (type backup_restore) with its duration as the RTO
 * actual and an outcome derived from per-database success. The actual dump is a
 * pluggable callable so the mechanism is testable without a live mysqldump.
 *
 * The default dumper shells out to mysqldump per connection; inject a fake in tests.
 */
class BackupService
{
    /** @var callable(string $connection, string $path):bool */
    private $dumper;

    public function __construct(?callable $dumper = null)
    {
        $this->dumper = $dumper ?? fn (string $connection, string $path): bool => $this->mysqldump($connection, $path);
    }

    /**
     * Back up the central database and every tenant database, then record a DrTest.
     * @return DrTest the recorded drill entry
     */
    public function run(): DrTest
    {
        $startedAt = Carbon::now();
        $dir = storage_path('app/backups/' . $startedAt->format('Ymd_His'));

        $results = [];

        // Central.
        $results['central'] = ($this->dumper)('central', $dir . '/central.sql');

        // Each tenant's database connection (stancl exposes it per tenant).
        Mutual::all()->each(function (Mutual $mutual) use (&$results, $dir) {
            $ok = false;
            try {
                $mutual->run(function () use (&$ok, $mutual, $dir) {
                    $ok = ($this->dumper)(config('database.default'), $dir . '/tenant_' . $mutual->id . '.sql');
                });
            } catch (\Throwable $e) {
                $ok = false;
            }
            $results['tenant:' . $mutual->id] = $ok;
        });

        $elapsedMinutes = (int) ceil($startedAt->diffInSeconds(Carbon::now()) / 60);

        return $this->record($results, $elapsedMinutes);
    }

    /** Derive the outcome and write the DrTest row. */
    private function record(array $results, int $elapsedMinutes): DrTest
    {
        $total = count($results);
        $ok = count(array_filter($results));

        $outcome = match (true) {
            $ok === $total => 'success',
            $ok === 0      => 'failed',
            default        => 'partial',
        };

        $failed = array_keys(array_filter($results, fn ($v) => $v === false));

        return DrTest::create([
            'ref'                => 'DRT-' . now()->format('y') . '-' . strtoupper(Str::random(4)),
            'type'               => 'backup_restore',
            'scope'              => "central + " . ($total - 1) . " tenant(s)",
            'performed_on'       => now()->toDateString(),
            'rto_target_minutes' => (int) config('mxconnect.dr.default_rto_minutes'),
            'rto_actual_minutes' => $elapsedMinutes,
            'rpo_target_minutes' => (int) config('mxconnect.dr.default_rpo_minutes'),
            'rpo_actual_minutes' => 0, // fresh dump: no data loss window
            'outcome'            => $outcome,
            'performed_by'       => 'system (scheduled backup)',
            'findings'           => $failed ? 'Failed: ' . implode(', ', $failed) : 'All databases backed up.',
        ]);
    }

    /** Default dumper: mysqldump for a connection to a file. Returns success. */
    private function mysqldump(string $connection, string $path): bool
    {
        $cfg = config("database.connections.{$connection}");
        if (! $cfg) {
            return false;
        }

        @mkdir(dirname($path), 0775, true);

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>/dev/null',
            escapeshellarg($cfg['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($cfg['port'] ?? 3306)),
            escapeshellarg($cfg['username'] ?? 'root'),
            escapeshellarg($cfg['password'] ?? ''),
            escapeshellarg($cfg['database'] ?? ''),
            escapeshellarg($path),
        );

        $result = 1;
        $out = [];
        @exec($cmd, $out, $result);

        return $result === 0;
    }
}
