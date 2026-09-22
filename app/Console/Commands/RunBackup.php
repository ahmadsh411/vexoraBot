<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RunBackup extends Command
{
    protected $signature = 'backup:run
                            {--keep=7 : عدد النسخ المحفوظة}
                            {--compress : ضغط النسخة}
                            {--force : بدون تأكيد}';

    protected $description = 'إنشاء نسخة احتياطية لقاعدة البيانات';

    public function handle(): int
    {
        $this->info('💾 بدء النسخ الاحتياطي...');
        $this->newLine();

        // ─── إعدادات قاعدة البيانات ───
        $db = config('database.connections.' . config('database.default'));

        if (($db['driver'] ?? '') !== 'mysql') {
            $this->error('❌ مدعوم فقط MySQL حالياً.');
            return self::FAILURE;
        }

        // ─── إنشاء المجلد ───
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // ─── اسم الملف ───
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename  = "backup_{$timestamp}.sql";
        $filepath  = "{$backupDir}/{$filename}";

        // ─── أمر mysqldump ───
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s 2>&1',
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port'] ?? '3306'),
            escapeshellarg($db['database']),
            escapeshellarg($filepath)
        );

        $this->line("📦 إنشاء: {$filename}");

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('❌ فشل النسخ الاحتياطي:');
            $this->error(implode("\n", $output));
            Log::error('Backup failed', ['output' => $output, 'code' => $returnCode]);
            return self::FAILURE;
        }

        // ─── حجم الملف ───
        $size = filesize($filepath);
        $sizeMb = round($size / 1024 / 1024, 2);

        $this->info("✅ تم النسخ الاحتياطي: {$sizeMb} MB");
        $this->newLine();

        // ─── تنظيف النسخ القديمة ───
        $this->cleanOldBackups($backupDir);

        Log::info('Backup created', [
            'file' => $filename,
            'size' => $sizeMb . ' MB',
        ]);

        return self::SUCCESS;
    }

    // ============================================================
    //  تنظيف النسخ القديمة
    // ============================================================
    private function cleanOldBackups(string $dir): void
    {
        $keep = (int) ($this->option('keep') ?? 7);

        $files = glob("{$dir}/backup_*.sql");
        if (! $files || count($files) <= $keep) {
            return;
        }

        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

        $toDelete = array_slice($files, 0, count($files) - $keep);

        $this->line("🗑️  حذف النسخ القديمة (احتفظ بـ {$keep}):");

        foreach ($toDelete as $file) {
            unlink($file);
            $this->line('  🗑️  ' . basename($file));
        }
    }
}
