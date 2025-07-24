<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\MediaAttachment;
use App\Jobs\ProcessVideoJob;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoProcessingService
{
    /**
     * Supported video formats for upload.
     */
    const SUPPORTED_FORMATS = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp'];
    
    /**
     * Output formats for conversion.
     */
    const OUTPUT_FORMATS = ['mp4', 'webm'];
    
    /**
     * Maximum file sizes by type (in MB).
     */
    const MAX_SIZES = [
        'posts' => 500,      // 500MB for posts
        'messages' => 100,   // 100MB for messages
        'groups' => 300,     // 300MB for groups
        'stories' => 50,     // 50MB for stories
    ];

    /**
     * Process multiple videos with compression and format conversion.
     */
    public function processVideos(array $videos, string $type, array $options = []): array
    {
        $results = [];
        
        foreach ($videos as $index => $video) {
            try {
                $result = $this->processSingleVideo($video, $type, $options);
                $results[] = $result;
            } catch (\Exception $e) {
                throw new \Exception("Failed to process video {$index}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Process a single video file.
     */
    public function processSingleVideo(UploadedFile $video, string $type, array $options = []): array
    {
        // Get video information
        $videoInfo = $this->getVideoInfo($video);
        
        // Determine processing settings
        $settings = $this->getProcessingSettings($type, $options, $videoInfo);
        
        // Generate filename and paths
        $filename = $this->generateFilename($video, $type);
        $disk = $this->getDiskForType($type);
        
        // Create MediaAttachment record
        $mediaAttachment = $this->createMediaAttachment([
            'user_id' => Auth::id(),
            'filename' => $video->getClientOriginalName(),
            'disk' => $disk,
            'path' => $filename,
            'url' => null, // Will be set after processing
            'mime_type' => $video->getMimeType(),
            'size' => $video->getSize(),
            'extension' => $video->getClientOriginalExtension(),
            'type' => 'video',
            'width' => $videoInfo['width'] ?? null,
            'height' => $videoInfo['height'] ?? null,
            'duration' => $videoInfo['duration'] ?? null,
            'status' => 'processing',
            'is_safe' => true, // Could be enhanced with content analysis
            'is_public' => $this->isPublicType($type),
            'analysis_results' => [
                'original_format' => $videoInfo['format'] ?? null,
                'original_codec' => $videoInfo['codec'] ?? null,
                'processing_started_at' => now()->toISOString(),
            ],
        ]);

        // Store original file temporarily
        $tempPath = $this->storeTemporaryFile($video, $filename);
        
        // Always use background processing for videos
        ProcessVideoJob::dispatch($mediaAttachment, $settings, $tempPath);

        return [
            'media_attachment' => $mediaAttachment,
            'processing' => true,
            'estimated_time' => $this->estimateProcessingTime($videoInfo),
        ];
    }

    /**
     * Get video information using FFmpeg.
     */
    protected function getVideoInfo(UploadedFile $video): array
    {
        if (!$this->isFFmpegAvailable()) {
            return $this->getBasicVideoInfo($video);
        }

        try {
            $process = new Process([
                'ffprobe',
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                $video->getRealPath()
            ]);

            $process->run();

            if (!$process->isSuccessful()) {
                return $this->getBasicVideoInfo($video);
            }

            $output = json_decode($process->getOutput(), true);
            $videoStream = $this->findVideoStream($output['streams'] ?? []);

            return [
                'duration' => (float) ($output['format']['duration'] ?? 0),
                'size' => (int) ($output['format']['size'] ?? $video->getSize()),
                'bitrate' => (int) ($output['format']['bit_rate'] ?? 0),
                'format' => $output['format']['format_name'] ?? null,
                'width' => $videoStream['width'] ?? null,
                'height' => $videoStream['height'] ?? null,
                'codec' => $videoStream['codec_name'] ?? null,
                'fps' => $this->parseFrameRate($videoStream['r_frame_rate'] ?? '0/1'),
            ];

        } catch (\Exception $e) {
            return $this->getBasicVideoInfo($video);
        }
    }

    /**
     * Get basic video info without FFmpeg.
     */
    protected function getBasicVideoInfo(UploadedFile $video): array
    {
        return [
            'size' => $video->getSize(),
            'mime_type' => $video->getMimeType(),
            'extension' => $video->getClientOriginalExtension(),
        ];
    }

    /**
     * Find video stream in FFmpeg output.
     */
    protected function findVideoStream(array $streams): ?array
    {
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                return $stream;
            }
        }
        return null;
    }

    /**
     * Parse frame rate from FFmpeg format.
     */
    protected function parseFrameRate(string $frameRate): float
    {
        if (strpos($frameRate, '/') !== false) {
            [$num, $den] = explode('/', $frameRate);
            return $den > 0 ? (float) $num / (float) $den : 0;
        }
        return (float) $frameRate;
    }

    /**
     * Process video with compression and format conversion.
     */
    public function processVideoFile(MediaAttachment $mediaAttachment, array $settings, string $tempPath): void
    {
        try {
            $disk = $mediaAttachment->disk;
            $results = [];

            // Process each output format
            foreach ($settings['output_formats'] as $format => $formatSettings) {
                $outputPath = $this->getOutputPath($mediaAttachment->path, $format);
                $processedFile = $this->convertVideo($tempPath, $outputPath, $formatSettings);
                
                if ($processedFile) {
                    $results[$format] = [
                        'path' => $outputPath,
                        'url' => Storage::disk($disk)->url($outputPath),
                        'size' => Storage::disk($disk)->size($outputPath),
                        'format' => $format,
                    ];
                }
            }

            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($tempPath, $mediaAttachment->path);
            if ($thumbnailPath) {
                $results['thumbnail'] = [
                    'path' => $thumbnailPath,
                    'url' => Storage::disk($disk)->url($thumbnailPath),
                    'type' => 'image',
                ];
            }

            // Update media attachment
            $primaryFormat = $settings['primary_format'] ?? 'mp4';
            $primaryVideo = $results[$primaryFormat] ?? array_values($results)[0] ?? null;

            $mediaAttachment->update([
                'url' => $primaryVideo['url'] ?? null,
                'variants' => $results,
                'status' => 'ready',
                'analysis_results' => array_merge(
                    $mediaAttachment->analysis_results ?? [],
                    [
                        'processing_completed_at' => now()->toISOString(),
                        'output_formats' => array_keys($results),
                        'compression_ratio' => $this->calculateCompressionRatio($mediaAttachment, $results),
                    ]
                ),
            ]);

            // Clean up temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

        } catch (\Exception $e) {
            $mediaAttachment->update([
                'status' => 'failed',
                'analysis_results' => array_merge(
                    $mediaAttachment->analysis_results ?? [],
                    [
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toISOString(),
                    ]
                ),
            ]);

            throw $e;
        }
    }

    /**
     * Convert video using FFmpeg.
     */
    protected function convertVideo(string $inputPath, string $outputPath, array $settings): bool
    {
        if (!$this->isFFmpegAvailable()) {
            throw new \Exception('FFmpeg is not available for video processing');
        }

        $command = $this->buildFFmpegCommand($inputPath, $outputPath, $settings);
        
        try {
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Store the processed file
            $disk = Storage::disk($this->getDiskForType('posts')); // Default to posts disk
            $disk->putFileAs(
                dirname($outputPath),
                new \Illuminate\Http\File($outputPath),
                basename($outputPath)
            );

            return true;

        } catch (\Exception $e) {
            throw new \Exception("Video conversion failed: " . $e->getMessage());
        }
    }

    /**
     * Build FFmpeg command for video conversion.
     */
    protected function buildFFmpegCommand(string $inputPath, string $outputPath, array $settings): array
    {
        $command = [
            'ffmpeg',
            '-i', $inputPath,
            '-y', // Overwrite output file
        ];

        // Video codec settings
        if (isset($settings['codec'])) {
            $command[] = '-c:v';
            $command[] = $settings['codec'];
        }

        // Audio codec settings
        if (isset($settings['audio_codec'])) {
            $command[] = '-c:a';
            $command[] = $settings['audio_codec'];
        }

        // Bitrate settings
        if (isset($settings['bitrate'])) {
            $command[] = '-b:v';
            $command[] = $settings['bitrate'];
        }

        // Resolution settings
        if (isset($settings['width']) && isset($settings['height'])) {
            $command[] = '-s';
            $command[] = $settings['width'] . 'x' . $settings['height'];
        }

        // Frame rate settings
        if (isset($settings['fps'])) {
            $command[] = '-r';
            $command[] = (string) $settings['fps'];
        }

        // Quality settings
        if (isset($settings['crf'])) {
            $command[] = '-crf';
            $command[] = (string) $settings['crf'];
        }

        // Preset for encoding speed vs compression
        if (isset($settings['preset'])) {
            $command[] = '-preset';
            $command[] = $settings['preset'];
        }

        $command[] = $outputPath;

        return $command;
    }

    /**
     * Generate video thumbnail.
     */
    protected function generateThumbnail(string $videoPath, string $basePath): ?string
    {
        if (!$this->isFFmpegAvailable()) {
            return null;
        }

        try {
            $thumbnailPath = $this->getThumbnailPath($basePath);
            $tempThumbnailPath = sys_get_temp_dir() . '/' . basename($thumbnailPath);

            $process = new Process([
                'ffmpeg',
                '-i', $videoPath,
                '-ss', '00:00:01.000',  // Take frame at 1 second
                '-vframes', '1',         // Take only 1 frame
                '-y',                    // Overwrite if exists
                $tempThumbnailPath
            ]);

            $process->run();

            if ($process->isSuccessful() && file_exists($tempThumbnailPath)) {
                // Store thumbnail
                $disk = Storage::disk($this->getDiskForType('posts'));
                $disk->putFileAs(
                    dirname($thumbnailPath),
                    new \Illuminate\Http\File($tempThumbnailPath),
                    basename($thumbnailPath)
                );

                unlink($tempThumbnailPath);
                return $thumbnailPath;
            }

        } catch (\Exception $e) {
            // Thumbnail generation failed, but don't fail the whole process
        }

        return null;
    }

    /**
     * Get processing settings based on type and options.
     */
    protected function getProcessingSettings(string $type, array $options, array $videoInfo): array
    {
        $baseSettings = $this->getBaseSettings($type);
        $settings = array_merge($baseSettings, $options);

        // Determine output formats
        $settings['output_formats'] = $this->determineOutputFormats($type, $settings, $videoInfo);
        $settings['primary_format'] = $settings['primary_format'] ?? 'mp4';

        return $settings;
    }

    /**
     * Get base processing settings for each type.
     */
    protected function getBaseSettings(string $type): array
    {
        return match ($type) {
            'posts' => [
                'max_duration' => 300,      // 5 minutes
                'max_resolution' => '1920x1080',
                'target_bitrate' => '2000k',
                'crf' => 23,
                'preset' => 'medium',
            ],
            'messages' => [
                'max_duration' => 60,       // 1 minute
                'max_resolution' => '1280x720',
                'target_bitrate' => '1000k',
                'crf' => 25,
                'preset' => 'fast',
            ],
            'groups' => [
                'max_duration' => 600,      // 10 minutes
                'max_resolution' => '1920x1080',
                'target_bitrate' => '2500k',
                'crf' => 22,
                'preset' => 'medium',
            ],
            'stories' => [
                'max_duration' => 30,       // 30 seconds
                'max_resolution' => '1080x1920', // Portrait for stories
                'target_bitrate' => '1500k',
                'crf' => 24,
                'preset' => 'fast',
            ],
            default => [
                'max_duration' => 180,      // 3 minutes
                'max_resolution' => '1280x720',
                'target_bitrate' => '1500k',
                'crf' => 24,
                'preset' => 'medium',
            ],
        };
    }

    /**
     * Determine output formats to generate.
     */
    protected function determineOutputFormats(string $type, array $settings, array $videoInfo): array
    {
        $formats = [];

        // Always generate MP4 for maximum compatibility
        $formats['mp4'] = [
            'codec' => 'libx264',
            'audio_codec' => 'aac',
            'preset' => $settings['preset'] ?? 'medium',
            'crf' => $settings['crf'] ?? 24,
        ];

        // Generate WebM for better compression (if supported)
        if ($this->supportsWebM()) {
            $formats['webm'] = [
                'codec' => 'libvpx-vp9',
                'audio_codec' => 'libopus',
                'crf' => ($settings['crf'] ?? 24) + 2, // Slightly lower quality for WebM
            ];
        }

        // Apply resolution limits
        $maxResolution = $settings['max_resolution'] ?? '1920x1080';
        [$maxWidth, $maxHeight] = explode('x', $maxResolution);

        foreach ($formats as &$format) {
            if (isset($videoInfo['width']) && isset($videoInfo['height'])) {
                if ($videoInfo['width'] > $maxWidth || $videoInfo['height'] > $maxHeight) {
                    $format['width'] = $maxWidth;
                    $format['height'] = $maxHeight;
                }
            }
            
            if (isset($settings['target_bitrate'])) {
                $format['bitrate'] = $settings['target_bitrate'];
            }
        }

        return $formats;
    }

    /**
     * Store temporary file for processing.
     */
    protected function storeTemporaryFile(UploadedFile $video, string $filename): string
    {
        $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.' . $video->getClientOriginalExtension();
        $video->move(dirname($tempPath), basename($tempPath));
        return $tempPath;
    }

    /**
     * Generate unique filename.
     */
    protected function generateFilename(UploadedFile $video, string $type): string
    {
        $timestamp = now()->format('Y/m/d');
        $uuid = Str::uuid();
        
        return "{$type}/{$timestamp}/{$uuid}";
    }

    /**
     * Get output path for specific format.
     */
    protected function getOutputPath(string $basePath, string $format): string
    {
        return $basePath . '.' . $format;
    }

    /**
     * Get thumbnail path.
     */
    protected function getThumbnailPath(string $basePath): string
    {
        return $basePath . '_thumbnail.jpg';
    }

    /**
     * Get disk for storage type.
     */
    protected function getDiskForType(string $type): string
    {
        $useCloud = config('app.env') === 'production' && config('filesystems.cloud');
        
        if ($useCloud) {
            return match ($type) {
                'posts' => 's3-posts',
                'groups' => 's3-groups',
                'messages' => 's3-messages',
                default => 's3',
            };
        }
        
        return match ($type) {
            'posts' => 'posts',
            'groups' => 'groups',
            'messages' => 'messages',
            default => 'public',
        };
    }

    /**
     * Create MediaAttachment record.
     */
    protected function createMediaAttachment(array $data): MediaAttachment
    {
        return MediaAttachment::create($data);
    }

    /**
     * Check if type should be public.
     */
    protected function isPublicType(string $type): bool
    {
        return in_array($type, ['posts', 'groups']);
    }

    /**
     * Estimate processing time based on video info.
     */
    protected function estimateProcessingTime(array $videoInfo): array
    {
        $duration = $videoInfo['duration'] ?? 60;
        $size = $videoInfo['size'] ?? 50 * 1024 * 1024; // 50MB default
        
        // Rough estimation: 1 minute of video = 2-5 minutes processing time
        $baseTime = $duration * 3; // 3 minutes per minute of video
        
        // Adjust for file size (larger files take longer)
        $sizeMB = $size / (1024 * 1024);
        $sizeMultiplier = min(2.0, $sizeMB / 100); // Up to 2x for files > 100MB
        
        $estimatedMinutes = ceil($baseTime * $sizeMultiplier);
        
        return [
            'estimated_minutes' => $estimatedMinutes,
            'estimated_completion' => now()->addMinutes($estimatedMinutes)->toISOString(),
        ];
    }

    /**
     * Calculate compression ratio.
     */
    protected function calculateCompressionRatio(MediaAttachment $mediaAttachment, array $results): float
    {
        $originalSize = $mediaAttachment->size;
        $compressedSize = 0;

        foreach ($results as $result) {
            if (isset($result['size'])) {
                $compressedSize += $result['size'];
            }
        }

        if ($originalSize > 0 && $compressedSize > 0) {
            return round((1 - ($compressedSize / $originalSize)) * 100, 2);
        }

        return 0;
    }

    /**
     * Check if FFmpeg is available.
     */
    protected function isFFmpegAvailable(): bool
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if WebM encoding is supported.
     */
    protected function supportsWebM(): bool
    {
        if (!$this->isFFmpegAvailable()) {
            return false;
        }

        try {
            $process = new Process(['ffmpeg', '-encoders']);
            $process->run();
            $output = $process->getOutput();
            return strpos($output, 'libvpx-vp9') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
} 