<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use App\Services\VideoProcessingService;

class VideoUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxFileSize = $this->getMaxFileSize();
        $allowedMimeTypes = $this->getAllowedMimeTypes();

        return [
            'videos' => 'required|array|min:1|max:5', // Max 5 videos at once
            'videos.*' => [
                'required',
                'file',
                'mimetypes:' . implode(',', $allowedMimeTypes),
                'max:' . $maxFileSize, // Size in KB
            ],
            'type' => 'required|string|in:posts,messages,groups,stories',
            'compress' => 'sometimes|boolean',
            'quality' => 'sometimes|string|in:low,medium,high,ultra',
            'max_duration' => 'sometimes|integer|min:5|max:3600', // 5 seconds to 1 hour
            'output_format' => 'sometimes|string|in:mp4,webm,both',
            'generate_thumbnail' => 'sometimes|boolean',
            'thumbnail_time' => 'sometimes|numeric|min:0', // Seconds into video for thumbnail
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateVideoContent($validator);
            $this->validateDuration($validator);
            $this->validateTotalSize($validator);
            $this->validateCodecs($validator);
        });
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'videos.required' => 'At least one video is required.',
            'videos.array' => 'Videos must be provided as an array.',
            'videos.min' => 'At least one video is required.',
            'videos.max' => 'Cannot upload more than 5 videos at once.',
            'videos.*.required' => 'Each video file is required.',
            'videos.*.mimetypes' => 'Invalid video format. Allowed formats: ' . implode(', ', $this->getAllowedExtensions()) . '.',
            'videos.*.max' => 'Video size cannot exceed ' . $this->getMaxFileSizeForHumans() . '.',
            'type.required' => 'Upload type is required.',
            'type.in' => 'Invalid upload type.',
            'quality.in' => 'Invalid quality setting. Use: low, medium, high, or ultra.',
            'max_duration.min' => 'Video duration must be at least 5 seconds.',
            'max_duration.max' => 'Video duration cannot exceed 1 hour.',
            'output_format.in' => 'Invalid output format. Use: mp4, webm, or both.',
            'thumbnail_time.min' => 'Thumbnail time must be 0 or greater.',
        ];
    }

    /**
     * Get the maximum file size based on upload type (in KB).
     */
    protected function getMaxFileSize(): int
    {
        $sizes = VideoProcessingService::MAX_SIZES;
        $sizeMB = $sizes[$this->input('type')] ?? $sizes['posts'];
        return $sizeMB * 1024; // Convert to KB
    }

    /**
     * Get maximum file size for humans.
     */
    protected function getMaxFileSizeForHumans(): string
    {
        $sizeMB = VideoProcessingService::MAX_SIZES[$this->input('type')] ?? VideoProcessingService::MAX_SIZES['posts'];
        return $sizeMB . 'MB';
    }

    /**
     * Get allowed MIME types.
     */
    protected function getAllowedMimeTypes(): array
    {
        return [
            'video/mp4',
            'video/avi',
            'video/quicktime', // .mov
            'video/x-ms-wmv',  // .wmv
            'video/x-flv',     // .flv
            'video/webm',
            'video/x-matroska', // .mkv
            'video/3gpp',      // .3gp
            'video/x-msvideo', // .avi (alternative)
        ];
    }

    /**
     * Get allowed file extensions.
     */
    protected function getAllowedExtensions(): array
    {
        return VideoProcessingService::SUPPORTED_FORMATS;
    }

    /**
     * Validate video content and format.
     */
    protected function validateVideoContent($validator): void
    {
        if (!$this->hasFile('videos')) {
            return;
        }

        foreach ($this->file('videos') as $index => $video) {
            if (!$video || !$video->isValid()) {
                continue;
            }

            // Check file extension
            $extension = strtolower($video->getClientOriginalExtension());
            if (!in_array($extension, $this->getAllowedExtensions())) {
                $validator->errors()->add(
                    "videos.{$index}",
                    'Invalid file extension. Allowed: ' . implode(', ', $this->getAllowedExtensions())
                );
            }

            // Verify MIME type matches extension
            $mimeType = $video->getMimeType();
            if (!in_array($mimeType, $this->getAllowedMimeTypes())) {
                $validator->errors()->add(
                    "videos.{$index}",
                    'File does not appear to be a valid video.'
                );
            }

            // Check if file is actually a video (basic check)
            if (!$this->isValidVideoFile($video)) {
                $validator->errors()->add(
                    "videos.{$index}",
                    'File is not a valid video or is corrupted.'
                );
            }
        }
    }

    /**
     * Validate video duration.
     */
    protected function validateDuration($validator): void
    {
        $maxDuration = $this->getMaxDurationForType($this->input('type'));
        $customMaxDuration = $this->input('max_duration');

        if ($customMaxDuration && $customMaxDuration > $maxDuration) {
            $validator->errors()->add(
                'max_duration',
                "Maximum duration for {$this->input('type')} videos is {$maxDuration} seconds."
            );
        }

        // Could add actual video duration validation here if FFmpeg is available
        // For now, we'll validate during processing
    }

    /**
     * Validate total upload size.
     */
    protected function validateTotalSize($validator): void
    {
        if (!$this->hasFile('videos')) {
            return;
        }

        $totalSize = 0;
        foreach ($this->file('videos') as $video) {
            if ($video && $video->isValid()) {
                $totalSize += $video->getSize();
            }
        }

        $maxTotalSize = 2 * 1024 * 1024 * 1024; // 2GB total
        if ($totalSize > $maxTotalSize) {
            $validator->errors()->add(
                'videos',
                'Total upload size cannot exceed 2GB.'
            );
        }
    }

    /**
     * Validate video codecs (if possible).
     */
    protected function validateCodecs($validator): void
    {
        // This would require FFmpeg/FFprobe to be available
        // For now, we'll do basic validation and handle codec issues during processing
        
        if (!$this->hasFile('videos')) {
            return;
        }

        foreach ($this->file('videos') as $index => $video) {
            if (!$video || !$video->isValid()) {
                continue;
            }

            // Check for suspicious file sizes (too small for video)
            if ($video->getSize() < 10240) { // Less than 10KB
                $validator->errors()->add(
                    "videos.{$index}",
                    'File is too small to be a valid video.'
                );
            }

            // Check for extremely large files that might cause issues
            $maxSize = $this->getMaxFileSize() * 1024; // Convert to bytes
            if ($video->getSize() > $maxSize) {
                $validator->errors()->add(
                    "videos.{$index}",
                    'Video file is too large.'
                );
            }
        }
    }

    /**
     * Basic video file validation.
     */
    protected function isValidVideoFile($video): bool
    {
        try {
            // Try to get basic file info
            $path = $video->getRealPath();
            if (!$path || !file_exists($path)) {
                return false;
            }

            // Check if file has video-like characteristics
            $size = filesize($path);
            if ($size < 1024) { // Less than 1KB is suspicious
                return false;
            }

            // Additional validation could be added here with FFprobe
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get maximum duration for upload type.
     */
    protected function getMaxDurationForType(string $type): int
    {
        return match ($type) {
            'posts' => 300,    // 5 minutes
            'messages' => 60,  // 1 minute
            'groups' => 600,   // 10 minutes
            'stories' => 30,   // 30 seconds
            default => 180,    // 3 minutes
        };
    }

    /**
     * Get processed and validated data.
     */
    public function getProcessedData(): array
    {
        $validated = $this->validated();
        
        return [
            'videos' => $validated['videos'],
            'type' => $validated['type'],
            'options' => [
                'compress' => $validated['compress'] ?? true,
                'quality' => $validated['quality'] ?? 'medium',
                'max_duration' => $validated['max_duration'] ?? $this->getMaxDurationForType($validated['type']),
                'output_format' => $validated['output_format'] ?? 'mp4',
                'generate_thumbnail' => $validated['generate_thumbnail'] ?? true,
                'thumbnail_time' => $validated['thumbnail_time'] ?? 1.0,
            ],
        ];
    }

    /**
     * Get quality settings for processing.
     */
    public function getQualitySettings(): array
    {
        $quality = $this->input('quality', 'medium');
        
        return match ($quality) {
            'low' => [
                'crf' => 28,
                'preset' => 'fast',
                'max_bitrate' => '1000k',
            ],
            'medium' => [
                'crf' => 24,
                'preset' => 'medium',
                'max_bitrate' => '2000k',
            ],
            'high' => [
                'crf' => 20,
                'preset' => 'slow',
                'max_bitrate' => '4000k',
            ],
            'ultra' => [
                'crf' => 18,
                'preset' => 'slower',
                'max_bitrate' => '8000k',
            ],
            default => [
                'crf' => 24,
                'preset' => 'medium',
                'max_bitrate' => '2000k',
            ],
        };
    }
} 