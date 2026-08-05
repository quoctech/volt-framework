<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use CodeIgniter\CLI\CLI;
use Config\Volt;
use Throwable;

/**
 * Backup/restore database bằng pg_dump/pg_restore.
 *
 * File backup dùng custom-format nén (-Fc) để hỗ trợ restore linh hoạt.
 */
class BackupService
{
    private string $backupDir;

    public function __construct(?string $backupDir = null)
    {
        $config = config(Volt::class);

        $this->backupDir = $backupDir ?? $config->backupDir;
        if ($this->backupDir === '') {
            $this->backupDir = WRITEPATH . 'backups';
        }

        if (! is_dir($this->backupDir) && ! @mkdir($this->backupDir, 0775, true) && ! is_dir($this->backupDir)) {
            throw new \RuntimeException("Cannot create backup directory: {$this->backupDir}");
        }
    }

    /**
     * Tạo backup của một database.
     *
     * @return string Đường dẫn file backup đã tạo.
     */
    public function backup(string $dbName, string $dbHost = 'localhost', int $dbPort = 5432, string $dbUser = 'volt_admin', string $dbPassword = ''): string
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);

        $file = $this->backupDir . '/' . $dbName . '_' . date('Ymd_His') . '.dump';
        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %d -U %s -d %s -Fc -Z 5 -f %s 2>&1',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            $dbPort,
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($file),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($file)) {
            if (is_file($file)) {
                @unlink($file);
            }
            throw new \RuntimeException('pg_dump failed: ' . implode("\n", $output));
        }

        return $file;
    }

    /**
     * Restore database từ file backup.
     */
    public function restore(string $dbName, string $file, string $dbHost = 'localhost', int $dbPort = 5432, string $dbUser = 'volt_admin', string $dbPassword = ''): void
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);

        if (! is_file($file)) {
            throw new \RuntimeException("Backup file not found: {$file}");
        }

        $cmd = sprintf(
            'PGPASSWORD=%s pg_restore -h %s -p %d -U %s -d %s --clean --if-exists --no-owner --no-privileges %s 2>&1',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            $dbPort,
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($file),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('pg_restore failed: ' . implode("\n", $output));
        }
    }

    /**
     * Liệt kê các file backup trong thư mục backup.
     *
     * @return list<string>
     */
    public function listBackups(?string $dbName = null): array
    {
        $files = glob($this->backupDir . '/*.dump') ?: [];
        sort($files);

        if ($dbName !== null) {
            $prefix = $dbName . '_';
            $files = array_values(array_filter(
                $files,
                static fn (string $f): bool => str_starts_with(basename($f), $prefix),
            ));
        }

        return $files;
    }

    /**
     * Xóa các backup cũ hơn retention (ngày). Trả về số file đã xóa.
     */
    public function prune(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $removed = 0;

        foreach ($this->listBackups() as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Chạy restore test: tạo DB tạm, restore vào đó, trả về kết quả.
     */
    public function verifyRestore(string $dbName, string $file, string $dbHost = 'localhost', int $dbPort = 5432, string $dbUser = 'volt_admin', string $dbPassword = ''): array
    {
        $this->assertSafeIdentifier($dbName);
        $testDb = $dbName . '_restoretest_' . date('YmdHis');
        $created = false;

        try {
            $sql = sprintf(
                'CREATE DATABASE "%s" OWNER "%s"',
                str_replace('"', '""', $testDb),
                str_replace('"', '""', $dbUser),
            );
            $cmd = sprintf(
                'PGPASSWORD=%s psql -U %s -h %s -p %d -d postgres -c %s 2>&1',
                escapeshellarg($dbPassword),
                escapeshellarg($dbUser),
                escapeshellarg($dbHost),
                $dbPort,
                escapeshellarg($sql),
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                return ['ok' => false, 'message' => 'Cannot create test database: ' . implode("\n", $output)];
            }
            $created = true;

            $this->restore($testDb, $file, $dbHost, $dbPort, $dbUser, $dbPassword);

            return ['ok' => true, 'message' => "Restore verified into temporary database '{$testDb}'."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            if ($created) {
                $dropCmd = sprintf(
                    'PGPASSWORD=%s psql -U %s -h %s -p %d -d postgres -c %s 2>&1',
                    escapeshellarg($dbPassword),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbHost),
                    $dbPort,
                    escapeshellarg(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', str_replace('"', '""', $testDb))),
                );
                exec($dropCmd, $_, $dropCode);
            }
        }
    }

    private function assertSafeIdentifier(string $value): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new \RuntimeException("Unsafe identifier: '{$value}'");
        }
    }
}
