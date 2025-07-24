<?php

namespace App\Jobs;

use App\Models\MediaAttachment;
use App\Services\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MediaAttachment $mediaAttachment,
        public array $settings
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImageProcessingService $imageProcessingService): void
    {
        try {
            Log::info('Processing image job started', [
                'media_attachment_id' => $this->mediaAttachment->id,
                'filename' => $this->mediaAttachment->filename,
            ]);

            // Process the image optimizations
            $imageProcessingService->processImageOptimizations(
                $this->mediaAttachment,
                $this->settings
            );

            Log::info('Image processing completed successfully', [
                'media_attachment_id' => $this->mediaAttachment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'media_attachment_id' => $this->mediaAttachment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark the media attachment as failed
            $this->mediaAttachment->update([
                'status' => 'failed',
                'analysis_results' => [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Image processing job failed permanently', [
            'media_attachment_id' => $this->mediaAttachment->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark as permanently failed
        $this->mediaAttachment->update([
            'status' => 'failed',
            'analysis_results' => [
                'error' => $exception->getMessage(),
                'failed_permanently_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'image-processing',
            'media-attachment:' . $this->mediaAttachment->id,
            'user:' . $this->mediaAttachment->user_id,
        ];
    }
} 