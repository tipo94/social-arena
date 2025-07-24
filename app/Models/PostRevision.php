<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRevision extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
        'version_number',
        'content',
        'type',
        'metadata',
        'visibility',
        'media_attachments',
        'changes_made',
        'diff_data',
        'edit_reason',
        'edit_source',
        'user_agent',
        'ip_address',
        'is_major_edit',
        'content_length',
        'characters_added',
        'characters_removed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'media_attachments' => 'array',
        'changes_made' => 'array',
        'diff_data' => 'array',
        'is_major_edit' => 'boolean',
        'version_number' => 'integer',
        'content_length' => 'integer',
        'characters_added' => 'integer',
        'characters_removed' => 'integer',
    ];

    /**
     * Get the post that this revision belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user who made this revision.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the content changes for this revision.
     */
    public function getContentChanges(): array
    {
        return $this->diff_data['content_diff'] ?? [];
    }

    /**
     * Get summary of what was changed.
     */
    public function getChangeSummary(): array
    {
        $summary = [];
        
        foreach ($this->changes_made as $field => $change) {
            $summary[] = [
                'field' => $field,
                'type' => $this->getChangeType($change),
                'description' => $this->getChangeDescription($field, $change),
            ];
        }

        return $summary;
    }

    /**
     * Check if this is a content-only edit.
     */
    public function isContentOnlyEdit(): bool
    {
        return count($this->changes_made) === 1 && isset($this->changes_made['content']);
    }

    /**
     * Get the edit impact level.
     */
    public function getEditImpactLevel(): string
    {
        if ($this->is_major_edit) {
            return 'major';
        }

        $changeCount = count($this->changes_made);
        $characterChanges = $this->characters_added + $this->characters_removed;

        if ($changeCount >= 3 || $characterChanges > 100) {
            return 'moderate';
        }

        return 'minor';
    }

    /**
     * Get human-readable edit source.
     */
    public function getEditSourceAttribute(): string
    {
        return match ($this->attributes['edit_source']) {
            'web' => 'Website',
            'api' => 'API',
            'admin' => 'Admin Panel',
            'mobile' => 'Mobile App',
            'automation' => 'Automated System',
            default => ucfirst($this->attributes['edit_source']),
        };
    }

    /**
     * Scope for major edits.
     */
    public function scopeMajorEdits($query)
    {
        return $query->where('is_major_edit', true);
    }

    /**
     * Scope for recent revisions.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for revisions by specific user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get change type for a field.
     */
    protected function getChangeType(array $change): string
    {
        if (!isset($change['old'])) {
            return 'added';
        }

        if (!isset($change['new']) || $change['new'] === null) {
            return 'removed';
        }

        return 'modified';
    }

    /**
     * Get human-readable change description.
     */
    protected function getChangeDescription(string $field, array $change): string
    {
        $type = $this->getChangeType($change);
        
        return match ($field) {
            'content' => match ($type) {
                'added' => 'Content added',
                'removed' => 'Content removed',
                'modified' => 'Content modified',
            },
            'visibility' => match ($type) {
                'modified' => "Visibility changed from {$change['old']} to {$change['new']}",
                default => 'Visibility updated',
            },
            'type' => match ($type) {
                'modified' => "Post type changed from {$change['old']} to {$change['new']}",
                default => 'Post type updated',
            },
            'metadata' => 'Additional information updated',
            default => ucfirst($field) . ' ' . $type,
        };
    }
} 