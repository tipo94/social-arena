<?php

namespace App\Console\Commands;

use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

class ProcessPendingDeletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:process-deletions 
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--force : Force processing even if not scheduled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending account deletions that have passed their grace period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing pending account deletions...');

        $deletionService = app(AccountDeletionService::class);
        $usersToDelete = $deletionService->getUsersScheduledForDeletion();

        if ($usersToDelete->isEmpty()) {
            $this->info('No accounts are scheduled for deletion.');
            return Command::SUCCESS;
        }

        $this->info("Found {$usersToDelete->count()} account(s) scheduled for deletion:");

        // Display accounts to be deleted
        $this->table(
            ['User ID', 'Email', 'Username', 'Deletion Requested', 'Scheduled Deletion'],
            $usersToDelete->map(function ($user) {
                return [
                    $user->id,
                    $user->email,
                    $user->username,
                    $user->deletion_requested_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $user->will_be_deleted_at?->format('Y-m-d H:i:s') ?? 'N/A',
                ];
            })->toArray()
        );

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: No accounts were actually deleted.');
            return Command::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Are you sure you want to permanently delete these accounts?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Process deletions
        $results = $deletionService->processPendingDeletions();

        // Display results
        $this->info("Deletion processing completed:");
        $this->line("- Processed: {$results['processed']}");
        $this->line("- Successful: {$results['successful']}");
        $this->line("- Failed: {$results['failed']}");

        if (!empty($results['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->error("User {$error['user_id']}: {$error['error']}");
            }
        }

        if ($results['failed'] > 0) {
            $this->warn("Some deletions failed. Check logs for details.");
            return Command::FAILURE;
        }

        $this->info("All account deletions processed successfully.");
        return Command::SUCCESS;
    }
} 