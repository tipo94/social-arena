<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostDeletionLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_id',
        'post_user_id',
        'deleted_by',
        'deletion_type',
        'deletion_reason',
        'post_snapshot',
        'original_created_at',
        'deleted_at',
        'restoration_deadline',
        'was_restored',
        'restored_at',
        'restored_by',
        'restoration_reason',
        'deletion_ip',
        'user_agent',
        'is_admin_action',
        'moderator_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'post_snapshot' => 'array',
        'moderator_notes' => 'array',
        'was_restored' => 'boolean',
        'is_admin_action' => 'boolean',
        'original_created_at' => 'datetime',
        'deleted_at' => 'datetime',
        'restoration_deadline' => 'datetime',
        'restored_at' => 'datetime',
    ];

    /**
     * Get the user who originally created the post.
     */
    public function postUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_user_id');
    }

    /**
     * Get the user who deleted the post.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the user who restored the post.
     */
    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    /**
     * Check if the post can still be restored.
     */
    public function canBeRestored(): bool
    {
        if ($this->was_restored) {
            return false;
        }

        if ($this->restoration_deadline && now() > $this->restoration_deadline) {
            return false;
        }

        return true;
    }

    /**
     * Get time remaining for restoration.
     */
    public function getRestorationTimeRemaining(): ?array
    {
        if ($this->was_restored || !$this->restoration_deadline) {
            return null;
        }

        $remaining = now()->diffInHours($this->restoration_deadline, false);

        if ($remaining <= 0) {
            return ['expired' => true];
        }

        return [
            'expired' => false,
            'hours_remaining' => $remaining,
            'deadline' => $this->restoration_deadline->toISOString(),
        ];
    }

    /**
     * Get deletion summary information.
     */
    public function getDeletionSummary(): array
    {
        $snapshot = $this->post_snapshot;
        
        return [
            'post_id' => $this->post_id,
            'content_preview' => substr($snapshot['content'] ?? '', 0, 100),
            'post_type' => $snapshot['type'] ?? 'unknown',
            'had_media' => !empty($snapshot['media_attachments']),
            'interaction_count' => [
                'likes' => $snapshot['likes_count'] ?? 0,
                'comments' => $snapshot['comments_count'] ?? 0,
                'shares' => $snapshot['shares_count'] ?? 0,
            ],
            'was_edited' => !empty($snapshot['edit_history']),
            'deletion_type' => $this->deletion_type,
            'is_admin_action' => $this->is_admin_action,
        ];
    }

    /**
     * Get human-readable deletion type.
     */
    public function getDeletionTypeAttribute(): string
    {
        return match ($this->attributes['deletion_type']) {
            'soft' => 'User Deletion',
            'admin' => 'Administrative Deletion',
            'auto' => 'Automatic Deletion',
            'permanent' => 'Permanent Deletion',
            'violation' => 'Policy Violation',
            default => ucfirst($this->attributes['deletion_type']),
        };
    }

    /**
     * Scope for restorable deletions.
     */
    public function scopeRestorable($query)
    {
        return $query->where('was_restored', false)
                    ->where('restoration_deadline', '>', now());
    }

    /**
     * Scope for expired deletions.
     */
    public function scopeExpired($query)
    {
        return $query->where('was_restored', false)
                    ->where('restoration_deadline', '<=', now());
    }

    /**
     * Scope for admin actions.
     */
    public function scopeAdminActions($query)
    {
        return $query->where('is_admin_action', true);
    }

    /**
     * Scope for user deletions.
     */
    public function scopeUserDeletions($query)
    {
        return $query->where('is_admin_action', false);
    }

    /**
     * Scope for specific deletion type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('deletion_type', $type);
    }

    /**
     * Scope for deletions by specific user.
     */
    public function scopeDeletedBy($query, int $userId)
    {
        return $query->where('deleted_by', $userId);
    }

    /**
     * Scope for restored posts.
     */
    public function scopeRestored($query)
    {
        return $query->where('was_restored', true);
    }

    /**
     * Add moderator note.
     */
    public function addModeratorNote(string $note, User $moderator): void
    {
        $notes = $this->moderator_notes ?? [];
        $notes[] = [
            'note' => $note,
            'added_by' => $moderator->id,
            'added_at' => now()->toISOString(),
        ];

        $this->update(['moderator_notes' => $notes]);
    }

    /**
     * Get formatted moderator notes.
     */
    public function getFormattedModeratorNotes(): array
    {
        if (!$this->moderator_notes) {
            return [];
        }

        return collect($this->moderator_notes)->map(function ($note) {
            $user = User::find($note['added_by']);
            return [
                'note' => $note['note'],
                'added_by' => $user ? $user->name : 'Unknown User',
                'added_at' => $note['added_at'],
            ];
        })->toArray();
    }
} 