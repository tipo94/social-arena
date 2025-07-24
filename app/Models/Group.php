<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'slug',
        'cover_image',
        'icon',
        'type',
        'privacy',
        'join_policy',
        'members_count',
        'posts_count',
        'pending_requests_count',
        'last_activity_at',
        'last_post_at',
        'rules',
        'requires_admin_approval',
        'allow_member_posts',
        'allow_member_invites',
        'current_books',
        'reading_schedule',
        'next_meeting_date',
        'is_active',
        'is_featured',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_books' => 'array',
        'reading_schedule' => 'array',
        'next_meeting_date' => 'date',
        'last_activity_at' => 'datetime',
        'last_post_at' => 'datetime',
        'requires_admin_approval' => 'boolean',
        'allow_member_posts' => 'boolean',
        'allow_member_invites' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the user that owns the group.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the memberships for the group.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /**
     * Get the posts for the group.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
