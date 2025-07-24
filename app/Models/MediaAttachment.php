<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class MediaAttachment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'attachable_type',
        'attachable_id',
        'filename',
        'disk',
        'path',
        'url',
        'mime_type',
        'size',
        'extension',
        'alt_text',
        'type',
        'width',
        'height',
        'duration',
        'status',
        'variants',
        'analysis_results',
        'is_safe',
        'is_public',
        'downloads_count',
        'views_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'variants' => 'array',
        'analysis_results' => 'array',
        'is_safe' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Get the user that owns the media attachment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attachable model (post, comment, message, etc.).
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL for the media file.
     */
    public function getFullUrlAttribute(): string
    {
        if ($this->url) {
            return $this->url;
        }

        try {
            return Storage::disk($this->disk)->url($this->path);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get variant URL for a specific size.
     */
    public function getVariantUrl(string $size): ?string
    {
        if (!$this->variants || !isset($this->variants[$size])) {
            return null;
        }

        $variantPath = $this->variants[$size]['path'] ?? null;
        if (!$variantPath) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->url($variantPath);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get thumbnail URL (falls back to original if not available).
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->getVariantUrl('thumbnail') ?: $this->full_url;
    }

    /**
     * Get medium-sized URL (falls back to original if not available).
     */
    public function getMediumUrlAttribute(): string
    {
        return $this->getVariantUrl('medium') ?: $this->full_url;
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Check if the media is an audio file.
     */
    public function isAudio(): bool
    {
        return $this->type === 'audio';
    }

    /**
     * Check if the media is a document.
     */
    public function isDocument(): bool
    {
        return in_array($this->type, ['document', 'archive']);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(): void
    {
        $this->increment('downloads_count');
    }

    /**
     * Check if the media is ready for display.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->is_safe;
    }

    /**
     * Scope for only ready media.
     */
    public function scopeReady($query)
    {
        return $query->where('status', 'ready')->where('is_safe', true);
    }

    /**
     * Scope for images only.
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope for videos only.
     */
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    /**
     * Scope for public media.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Delete the actual file when the model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (MediaAttachment $media) {
            // Delete the main file
            if ($media->disk && $media->path) {
                Storage::disk($media->disk)->delete($media->path);
            }

            // Delete variants
            if ($media->variants) {
                foreach ($media->variants as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk($media->disk)->delete($variant['path']);
                    }
                }
            }
        });
    }
} 