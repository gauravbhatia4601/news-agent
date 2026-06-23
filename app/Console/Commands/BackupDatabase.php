<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'news:backup-db {--retention=7 : Days to keep backups}';

    protected $description = 'Backup the database to storage and prune old backups';

    public function handle(): int
    {
        $retention = (int) $this->option('retention');
        $connection = config('database.default');
        $dbName = config("database.connections.{$connection}.database");
        $timestamp = now()->format('Y-m-d_His');
        $filename = "db-backups/{$dbName}_{$timestamp}.sql.gz";

        $dumpCommand = $this->buildDumpCommand($connection, $filename);

        if ($dumpCommand === null) {
            $this->error("Database backup not supported for connection: {$connection}");
            return self::FAILURE;
        }

        $this->info("Backing up database to {$filename}...");

        $exitCode = null;
        $output = [];
        exec($dumpCommand . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Backup failed: ' . implode("\n", $output));
            return self::FAILURE;
        }

        $this->info("Backup created successfully.");

        $this->pruneOldBackups($retention);

        return self::SUCCESS;
    }

    private function buildDumpCommand(string $connection, string $filename): ?string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('db-backups');

        $fullPath = $disk->path($filename);

        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'pgsql') {
            $env = [];
            $env[] = 'PGPASSWORD=' . escapeshellarg($config['password'] ?? '');
            $cmd = sprintf(
                '%s -U %s -h %s -p %s -d %s | gzip > %s',
                env('PG_DUMP_PATH', 'pg_dump'),
                escapeshellarg($config['username'] ?? 'postgres'),
                escapeshellarg($config['host'] ?? '127.0.0.1'),
                escapeshellarg((string) ($config['port'] ?? 5432)),
                escapeshellarg($config['database']),
                escapeshellarg($fullPath)
            );
            return implode(' ', $env) . ' ' . $cmd;
        }

        return null;
    }

    private function pruneOldBackups(int $retentionDays): void
    {
        $disk = Storage::disk('local');
        $files = $disk->files('db-backups');
        $cutoff = now()->subDays($retentionDays);

        $pruned = 0;
        foreach ($files as $file) {
            $time = $disk->lastModified($file);
            if ($time < $cutoff->timestamp) {
                $disk->delete($file);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $this->info("Pruned {$pruned} backup(s) older than {$retentionDays} days.");
        }
    }
}