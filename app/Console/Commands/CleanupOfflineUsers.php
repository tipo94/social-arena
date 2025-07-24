<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UserPresenceService;

class CleanupOfflineUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:cleanup-offline 
                           {--threshold=5 : Minutes after which users are considered offline}
                           {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up users who are no longer online based on their last activity';

    public function __construct(
        private UserPresenceService $presenceService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting offline users cleanup...');
        
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        try {
            if (!$dryRun) {
                $cleanedCount = $this->presenceService->cleanupOfflineUsers();
                
                if ($cleanedCount > 0) {
                    $this->info("✅ Successfully marked {$cleanedCount} users as offline");
                } else {
                    $this->info('✅ No users needed to be marked as offline');
                }
            } else {
                // For dry run, we would need to implement a preview method
                $this->info('DRY RUN: Would check for offline users and mark them accordingly');
            }

            // Display current statistics
            $this->displayPresenceStats();

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to cleanup offline users: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            
            return Command::FAILURE;
        }
    }

    /**
     * Display current presence statistics
     */
    private function displayPresenceStats(): void
    {
        try {
            $onlineCount = $this->presenceService->getOnlineUserCount();
            $this->info("📊 Current online users: {$onlineCount}");
            
            $onlineUsers = $this->presenceService->getOnlineUsers(10);
            
            if (!empty($onlineUsers)) {
                $this->info('👥 Recently active users:');
                foreach (array_slice($onlineUsers, 0, 5) as $user) {
                    $lastActivity = $user['last_activity_at'] ?? 'Unknown';
                    $this->line("   • {$user['name']} (@{$user['username']}) - Last active: {$lastActivity}");
                }
                
                if (count($onlineUsers) > 5) {
                    $remaining = count($onlineUsers) - 5;
                    $this->line("   ... and {$remaining} more");
                }
            }
            
        } catch (\Exception $e) {
            $this->warn("Could not retrieve presence statistics: {$e->getMessage()}");
        }
    }
} 