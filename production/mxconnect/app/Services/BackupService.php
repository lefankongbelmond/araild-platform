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
        Mutual::operational()->get()->each(function (Mutual $mutual) use (&$results, $dir) {
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

        $this->prune((int) config('mxconnect.backup_keep', 7));

        return $this->record($results, $elapsedMinutes);
    }

    /** Ne garde que les N dernières sauvegardes locales (quota d'hébergement). */
    private function prune(int $keep): void
    {
        if ($keep <= 0) {
            return;
        }
        $dirs = glob(storage_path('app/backups/*'), GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach (array_slice($dirs, 0, max(0, count($dirs) - $keep)) as $old) {
            \Illuminate\Support\Facades\File::deleteDirectory($old);
        }
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
        if ($this->canExec()) {
            @exec($cmd, $out, $result);
        }

        // Hébergement mutualisé : exec() désactivé ou mysqldump absent ->
        // export SQL en PHP pur (PDO), compatible phpMyAdmin.
        if ($result !== 0 || ! is_file($path) || filesize($path) === 0) {
            return $this->phpDump($connection, $path);
        }

        return true;
    }

    private function canExec(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('exec', $disabled, true);
    }

    /** Export SQL complet (structure + données) sans binaire externe. */
    private function phpDump(string $connection, string $path): bool
    {
        try {
            $db = \Illuminate\Support\Facades\DB::connection($connection);
            $pdo = $db->getPdo();
            @mkdir(dirname($path), 0775, true);
            $fh = fopen($path, 'w');
            if (! $fh) {
                return false;
            }

            fwrite($fh, "-- MX-CONNECT export PHP · base " . $db->getDatabaseName() . " · " . now()->toDateTimeString() . "\n");
            fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($db->select('SHOW FULL TABLES') as $row) {
                $row = array_values((array) $row);
                if (($row[1] ?? 'BASE TABLE') !== 'BASE TABLE') {
                    continue;
                }
                $table = $row[0];
                $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `{$table}`"))[1];
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`", \PDO::FETCH_NUM);
                $batch = [];
                while ($r = $stmt->fetch()) {
                    $batch[] = '(' . implode(',', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                    if (count($batch) === 200) {
                        fwrite($fh, "INSERT INTO `{$table}` VALUES " . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($fh, "INSERT INTO `{$table}` VALUES " . implode(",\n", $batch) . ";\n");
                }
                fwrite($fh, "\n");
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
