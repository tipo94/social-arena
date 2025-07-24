<?php

namespace App\Jobs;

use App\Models\MediaAttachment;
use App\Services\VideoProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2; // Fewer retries for videos due to processing time

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200; // 2 hours for large videos

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MediaAttachment $mediaAttachment,
        public array $settings,
        public string $tempPath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(VideoProcessingService $videoProcessingService): void
    {
        try {
            Log::info('Video processing job started', [
                'media_attachment_id' => $this->mediaAttachment->id,
                'filename' => $this->mediaAttachment->filename,
                'duration' => $this->mediaAttachment->duration,
                'size_mb' => round($this->mediaAttachment->size / 1024 / 1024, 2),
            ]);

            // Process the video with compression and format conversion
            $videoProcessingService->processVideoFile(
                $this->mediaAttachment,
                $this->settings,
                $this->tempPath
            );

            Log::info('Video processing completed successfully', [
                'media_attachment_id' => $this->mediaAttachment->id,
                'processing_time_minutes' => $this->getProcessingTime(),
            ]);

        } catch (\Exception $e) {
            Log::error('Video processing failed', [
                'media_attachment_id' => $this->mediaAttachment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'temp_path_exists' => file_exists($this->tempPath),
            ]);

            // Mark the media attachment as failed
            $this->mediaAttachment->update([
                'status' => 'failed',
                'analysis_results' => array_merge(
                    $this->mediaAttachment->analysis_results ?? [],
                    [
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toISOString(),
                        'processing_time_minutes' => $this->getProcessingTime(),
                    ]
                ),
            ]);

            // Clean up temp file on failure
            if (file_exists($this->tempPath)) {
                unlink($this->tempPath);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video processing job failed permanently', [
            'media_attachment_id' => $this->mediaAttachment->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark as permanently failed
        $this->mediaAttachment->update([
            'status' => 'failed',
            'analysis_results' => array_merge(
                $this->mediaAttachment->analysis_results ?? [],
                [
                    'error' => $exception->getMessage(),
                    'failed_permanently_at' => now()->toISOString(),
                    'final_attempt' => $this->attempts(),
                ]
            ),
        ]);

        // Clean up temp file
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
    }

    /**
     * Get processing time in minutes.
     */
    protected function getProcessingTime(): float
    {
        $startTime = $this->mediaAttachment->analysis_results['processing_started_at'] ?? null;
        
        if ($startTime) {
            $start = \Carbon\Carbon::parse($startTime);
            return round($start->diffInMinutes(now(), true), 2);
        }

        return 0;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'video-processing',
            'media-attachment:' . $this->mediaAttachment->id,
            'user:' . $this->mediaAttachment->user_id,
            'type:' . $this->mediaAttachment->type,
        ];
    }

    /**
     * Calculate the number of seconds after which the job should be retried.
     */
    public function backoff(): array
    {
        return [300, 900]; // Retry after 5 minutes, then 15 minutes
    }
} 