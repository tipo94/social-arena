<?php

namespace App\Console\Commands;

use App\Services\StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToCloud extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:migrate-to-cloud 
                            {type=all : Type of files to migrate (avatars, posts, messages, groups, or all)}
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--batch=100 : Number of files to process in each batch}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate files from local storage to cloud storage';

    /**
     * Execute the console command.
     */
    public function handle(StorageService $storageService): int
    {
        $type = $this->argument('type');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');

        $this->info("Starting storage migration to cloud...");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will actually be migrated");
        }

        $types = $type === 'all' 
            ? ['avatars', 'posts', 'messages', 'groups'] 
            : [$type];

        $totalFiles = 0;
        $migratedFiles = 0;
        $errors = [];

        foreach ($types as $fileType) {
            $this->info("\nProcessing {$fileType}...");
            
            try {
                $result = $this->migrateFileType($storageService, $fileType, $dryRun, $batchSize);
                $totalFiles += $result['total'];
                $migratedFiles += $result['migrated'];
                $errors = array_merge($errors, $result['errors']);
            } catch (\Exception $e) {
                $this->error("Error processing {$fileType}: " . $e->getMessage());
                $errors[] = "Type {$fileType}: " . $e->getMessage();
            }
        }

        $this->newLine();
        $this->info("Migration Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Files Found', $totalFiles],
                ['Successfully Migrated', $migratedFiles],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $this->error("\nErrors encountered:");
            foreach ($errors as $error) {
                $this->line("  • {$error}");
            }
        }

        return count($errors) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Migrate files of a specific type
     */
    protected function migrateFileType(StorageService $storageService, string $type, bool $dryRun, int $batchSize): array
    {
        $localDisk = $type;
        $cloudDisk = "s3-{$type}";
        
        if (!Storage::disk($localDisk)->exists('')) {
            $this->warn("Local disk '{$localDisk}' not found or empty");
            return ['total' => 0, 'migrated' => 0, 'errors' => []];
        }

        $files = Storage::disk($localDisk)->allFiles();
        $total = count($files);
        $migrated = 0;
        $errors = [];

        if ($total === 0) {
            $this->info("No files found in {$type} storage");
            return ['total' => 0, 'migrated' => 0, 'errors' => []];
        }

        $this->info("Found {$total} files in {$type} storage");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $batches = array_chunk($files, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $file) {
                try {
                    if (!$dryRun) {
                        $storageService->migrateToCloud($localDisk, $cloudDisk, $file);
                    }
                    $migrated++;
                } catch (\Exception $e) {
                    $errors[] = "File {$file}: " . $e->getMessage();
                }
                
                $progressBar->advance();
            }

            // Small delay between batches to avoid overwhelming the system
            if (!$dryRun) {
                usleep(100000); // 0.1 seconds
            }
        }

        $progressBar->finish();
        $this->newLine();

        return [
            'total' => $total,
            'migrated' => $migrated,
            'errors' => $errors,
        ];
    }
} 