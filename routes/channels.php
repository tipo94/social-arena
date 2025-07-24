<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User's private notification channel
Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// User's private presence channel
Broadcast::channel('user.{userId}.presence', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Global notification channels by type (admin or high-priority notifications)
Broadcast::channel('notifications.{type}', function ($user, $type) {
    // Allow access to certain notification types for all authenticated users
    $allowedTypes = ['system', 'announcement', 'security'];
    return in_array($type, $allowedTypes);
});

// User presence channel (public channel for online status)
Broadcast::channel('user-presence', function ($user) {
    if ($user->profile?->show_online_status ?? true) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->profile?->avatar_url,
            'is_online' => true,
            'last_activity_at' => $user->last_activity_at,
        ];
    }
    return false;
});

// Friend activity channel (for friends' activity updates)
Broadcast::channel('user.{userId}.friends-activity', function ($user, $userId) {
    if ((int) $user->id !== (int) $userId) {
        return false;
    }
    
    // Check if user wants to see friends' activity
    return $user->profile?->show_friends_activity ?? true;
});

// Post interaction channel (for real-time likes, comments on user's posts)
Broadcast::channel('post.{postId}.interactions', function ($user, $postId) {
    // Check if user has permission to see this post's interactions
    $post = \App\Models\Post::find($postId);
    
    if (!$post) {
        return false;
    }
    
    // Allow if user is the post author
    if ($post->user_id === $user->id) {
        return true;
    }
    
    // Check visibility permissions
    $visibilityService = app(\App\Services\ContentVisibilityService::class);
    return $visibilityService->isPostVisibleTo($post, $user);
});

// Group/conversation channels (for future messaging features)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if user is part of this conversation
    // This will be implemented when messaging system is built
    return false;
});

// Admin channels (for admin notifications and monitoring)
Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasRole('admin') || $user->hasRole('moderator');
});

Broadcast::channel('admin.user-activity', function ($user) {
    return $user->hasRole('admin');
});

// Live feed updates (for real-time feed updates)
Broadcast::channel('user.{userId}.feed-updates', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Typing indicators for comments
Broadcast::channel('post.{postId}.typing', function ($user, $postId) {
    $post = \App\Models\Post::find($postId);
    
    if (!$post) {
        return false;
    }
    
    $visibilityService = app(\App\Services\ContentVisibilityService::class);
    return $visibilityService->isPostVisibleTo($post, $user);
});

// Follow notifications channel
Broadcast::channel('user.{userId}.follows', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Friend request notifications channel  
Broadcast::channel('user.{userId}.friend-requests', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}); 