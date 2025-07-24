<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rule;

class ImageUploadRequest extends FormRequest
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
        $maxDimensions = $this->getMaxDimensions();

        return [
            'images' => 'required|array|min:1|max:10',
            'images.*' => [
                'required',
                File::image()
                    ->max($maxFileSize)
                    ->dimensions(
                        Rule::dimensions()
                            ->minWidth(100)
                            ->minHeight(100)
                            ->maxWidth($maxDimensions['width'])
                            ->maxHeight($maxDimensions['height'])
                    ),
            ],
            'type' => 'required|string|in:posts,avatars,covers,groups,messages',
            'compress' => 'sometimes|boolean',
            'quality' => 'sometimes|integer|min:10|max:100',
            'max_width' => 'sometimes|integer|min:100|max:4000',
            'max_height' => 'sometimes|integer|min:100|max:4000',
            'maintain_aspect_ratio' => 'sometimes|boolean',
            'generate_thumbnails' => 'sometimes|boolean',
            'watermark' => 'sometimes|boolean',
            'alt_text' => 'sometimes|array',
            'alt_text.*' => 'string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateImageContent($validator);
            $this->validateFileTypes($validator);
            $this->validateTotalSize($validator);
        });
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'images.required' => 'At least one image is required.',
            'images.array' => 'Images must be provided as an array.',
            'images.min' => 'At least one image is required.',
            'images.max' => 'Cannot upload more than 10 images at once.',
            'images.*.required' => 'Each image file is required.',
            'images.*.max' => 'Image size cannot exceed ' . $this->getMaxFileSizeForHumans() . '.',
            'type.required' => 'Upload type is required.',
            'type.in' => 'Invalid upload type.',
            'quality.min' => 'Image quality must be at least 10%.',
            'quality.max' => 'Image quality cannot exceed 100%.',
            'max_width.min' => 'Maximum width must be at least 100 pixels.',
            'max_width.max' => 'Maximum width cannot exceed 4000 pixels.',
            'max_height.min' => 'Maximum height must be at least 100 pixels.',
            'max_height.max' => 'Maximum height cannot exceed 4000 pixels.',
        ];
    }

    /**
     * Get the maximum file size based on upload type.
     */
    protected function getMaxFileSize(): int
    {
        return match ($this->input('type')) {
            'avatars' => 5 * 1024, // 5MB in KB
            'covers' => 10 * 1024, // 10MB in KB
            'posts' => 20 * 1024, // 20MB in KB
            'groups' => 10 * 1024, // 10MB in KB
            'messages' => 5 * 1024, // 5MB in KB
            default => 10 * 1024, // 10MB in KB
        };
    }

    /**
     * Get the maximum dimensions based on upload type.
     */
    protected function getMaxDimensions(): array
    {
        return match ($this->input('type')) {
            'avatars' => ['width' => 2000, 'height' => 2000],
            'covers' => ['width' => 4000, 'height' => 2000],
            'posts' => ['width' => 4000, 'height' => 4000],
            'groups' => ['width' => 3000, 'height' => 2000],
            'messages' => ['width' => 2000, 'height' => 2000],
            default => ['width' => 3000, 'height' => 3000],
        };
    }

    /**
     * Get maximum file size for humans.
     */
    protected function getMaxFileSizeForHumans(): string
    {
        $sizeKb = $this->getMaxFileSize();
        if ($sizeKb >= 1024) {
            return round($sizeKb / 1024, 1) . 'MB';
        }
        return $sizeKb . 'KB';
    }

    /**
     * Validate image content for inappropriate material.
     */
    protected function validateImageContent($validator): void
    {
        if (!$this->hasFile('images')) {
            return;
        }

        foreach ($this->file('images') as $index => $image) {
            if (!$image || !$image->isValid()) {
                continue;
            }

            // Basic file extension check
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower($image->getClientOriginalExtension());
            
            if (!in_array($extension, $allowedExtensions)) {
                $validator->errors()->add(
                    "images.{$index}",
                    'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)
                );
            }

            // Check for potential malicious files disguised as images
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $image->getRealPath());
            finfo_close($finfo);

            $allowedMimeTypes = [
                'image/jpeg',
                'image/png', 
                'image/gif',
                'image/webp'
            ];

            if (!in_array($mimeType, $allowedMimeTypes)) {
                $validator->errors()->add(
                    "images.{$index}",
                    'File does not appear to be a valid image.'
                );
            }
        }
    }

    /**
     * Validate file types match their content.
     */
    protected function validateFileTypes($validator): void
    {
        if (!$this->hasFile('images')) {
            return;
        }

        foreach ($this->file('images') as $index => $image) {
            if (!$image || !$image->isValid()) {
                continue;
            }

            // Verify image can be processed
            try {
                $imageInfo = getimagesize($image->getRealPath());
                if ($imageInfo === false) {
                    $validator->errors()->add(
                        "images.{$index}",
                        'File is not a valid image or is corrupted.'
                    );
                }
            } catch (\Exception $e) {
                $validator->errors()->add(
                    "images.{$index}",
                    'Unable to process image file.'
                );
            }
        }
    }

    /**
     * Validate total upload size.
     */
    protected function validateTotalSize($validator): void
    {
        if (!$this->hasFile('images')) {
            return;
        }

        $totalSize = 0;
        foreach ($this->file('images') as $image) {
            if ($image && $image->isValid()) {
                $totalSize += $image->getSize();
            }
        }

        $maxTotalSize = 100 * 1024 * 1024; // 100MB total
        if ($totalSize > $maxTotalSize) {
            $validator->errors()->add(
                'images',
                'Total upload size cannot exceed 100MB.'
            );
        }
    }

    /**
     * Get processed and validated data.
     */
    public function getProcessedData(): array
    {
        $validated = $this->validated();
        
        return [
            'images' => $validated['images'],
            'type' => $validated['type'],
            'options' => [
                'compress' => $validated['compress'] ?? true,
                'quality' => $validated['quality'] ?? 85,
                'max_width' => $validated['max_width'] ?? null,
                'max_height' => $validated['max_height'] ?? null,
                'maintain_aspect_ratio' => $validated['maintain_aspect_ratio'] ?? true,
                'generate_thumbnails' => $validated['generate_thumbnails'] ?? true,
                'watermark' => $validated['watermark'] ?? false,
            ],
            'alt_texts' => $validated['alt_text'] ?? [],
        ];
    }
} 