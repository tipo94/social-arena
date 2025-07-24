<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'first_name',
        'last_name',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'last_login_ip',
        'last_activity_at',
        'is_online',
        'is_active',
        'is_banned',
        'banned_until',
        'ban_reason',
        'role',
        'permissions',
        'timezone',
        'locale',
        'theme',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'login_count',
        'last_password_change',
        'social_provider',
        'social_provider_id',
        'social_avatar_url',
        'account_type',
        'subscription_status',
        'subscription_expires_at',
        'deletion_requested_at',
        'deletion_reason',
        'will_be_deleted_at',
        'deletion_failed_at',
        'deletion_failure_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'banned_until' => 'datetime',
            'last_password_change' => 'date',
            'subscription_expires_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'will_be_deleted_at' => 'datetime',
            'deletion_failed_at' => 'datetime',
            'is_online' => 'boolean',
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'permissions' => 'array',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the groups owned by the user.
     */
    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * Get the friend requests sent by this user.
     */
    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Get the friend requests received by this user.
     */
    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    /**
     * Get accepted friends (sent requests).
     */
    public function acceptedFriends()
    {
        return $this->sentFriendRequests()->where('status', 'accepted');
    }

    /**
     * Get accepted friend requests (received requests).
     */
    public function acceptedFriendRequests()
    {
        return $this->receivedFriendRequests()->where('status', 'accepted');
    }

    /**
     * Get all notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's friends (both sent and received accepted friendships).
     */
    public function friends()
    {
        $sentFriends = $this->sentFriendRequests()
            ->where('status', 'accepted')
            ->with('friend');
            
        $receivedFriends = $this->receivedFriendRequests()
            ->where('status', 'accepted')
            ->with('user');

        // Combine both collections and extract user objects
        return $sentFriends->get()->pluck('friend')
            ->merge($receivedFriends->get()->pluck('user'));
    }

    /**
     * Check if the user is friends with another user.
     */
    public function isFriendsWith(User $user): bool
    {
        return $this->sentFriendRequests()
            ->where('friend_id', $user->id)
            ->where('status', 'accepted')
            ->exists() ||
            $this->receivedFriendRequests()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Check if there's a pending friend request between users.
     */
    public function hasPendingFriendRequestWith(User $user): bool
    {
        return $this->sentFriendRequests()
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->exists() ||
            $this->receivedFriendRequests()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get full name of the user.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }
        
        return $this->name;
    }

    /**
     * Get the user's display name (username or name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->username ?: $this->name;
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->profile && $this->profile->avatar_url) {
            return $this->profile->avatar_url;
        }
        
        if ($this->social_avatar_url) {
            return $this->social_avatar_url;
        }
        
        // Return default avatar or Gravatar
        return $this->getGravatarUrl();
    }

    /**
     * Get Gravatar URL for the user.
     */
    public function getGravatarUrl(int $size = 200): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
    }

    /**
     * Check if the user is online.
     */
    public function isOnline(): bool
    {
        if (!$this->last_activity_at) {
            return false;
        }
        
        return $this->last_activity_at->diffInMinutes(now()) < 5;
    }

    /**
     * Update the user's last activity.
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
            'is_online' => true,
        ]);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->permissions) {
            return false;
        }
        
        return in_array($permission, $this->permissions);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']) || $this->hasRole('admin') || $this->hasRole('super_admin');
    }

    /**
     * Check if the user is banned.
     */
    public function isBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }
        
        if ($this->banned_until && $this->banned_until->isPast()) {
            // Automatically unban if ban period has expired
            $this->update([
                'is_banned' => false,
                'banned_until' => null,
                'ban_reason' => null,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Ban the user.
     */
    public function ban(?string $reason = null, ?\Carbon\Carbon $until = null): void
    {
        $this->update([
            'is_banned' => true,
            'ban_reason' => $reason,
            'banned_until' => $until,
        ]);
    }

    /**
     * Unban the user.
     */
    public function unban(): void
    {
        $this->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_until' => null,
        ]);
    }

    /**
     * Check if user account is premium/subscribed.
     */
    public function isPremium(): bool
    {
        return $this->subscription_status === 'active' && 
               $this->subscription_expires_at && 
               $this->subscription_expires_at->isFuture();
    }

    /**
     * Get user's privacy preference for profile visibility.
     */
    public function isProfilePublic(): bool
    {
        return $this->profile ? !$this->profile->is_private_profile : true;
    }

    /**
     * Create profile automatically when user is created.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->profile()->create([
                'profile_completion_percentage' => 20, // Basic info is 20%
            ]);
        });
    }
}
