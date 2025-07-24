<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'formatted_size' => $this->formatted_size,
            'extension' => $this->extension,
            'alt_text' => $this->alt_text,
            
            // URLs
            'url' => $this->full_url,
            'thumbnail_url' => $this->when($this->isImage(), $this->thumbnail_url),
            'medium_url' => $this->when($this->isImage(), $this->medium_url),
            
            // Image/Video specific data
            'width' => $this->when($this->width, $this->width),
            'height' => $this->when($this->height, $this->height),
            'duration' => $this->when($this->duration, $this->duration),
            
            // Variants (different sizes)
            'variants' => $this->when($this->variants, function () {
                $variants = [];
                foreach ($this->variants as $size => $data) {
                    $variants[$size] = [
                        'url' => $this->getVariantUrl($size),
                        'width' => $data['width'] ?? null,
                        'height' => $data['height'] ?? null,
                        'size' => $data['size'] ?? null,
                    ];
                }
                return $variants;
            }),
            
            // Status
            'status' => $this->status,
            'is_ready' => $this->isReady(),
            'is_safe' => $this->is_safe,
            'is_public' => $this->is_public,
            
            // Usage stats
            'views_count' => $this->views_count,
            'downloads_count' => $this->downloads_count,
            
            // Type checks
            'is_image' => $this->isImage(),
            'is_video' => $this->isVideo(),
            'is_audio' => $this->isAudio(),
            'is_document' => $this->isDocument(),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
} 