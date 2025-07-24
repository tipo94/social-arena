# Real-Time Notification System

This document provides comprehensive information about the real-time notification system implemented for the AI-Book Social Networking Platform.

## 🏗️ Architecture Overview

The real-time notification system consists of several interconnected components:

### Backend Components

1. **Broadcasting Events**
   - `NotificationCreated` - Fired when new notifications are created
   - `NotificationRead` - Fired when notifications are marked as read
   - `NotificationBulkRead` - Fired when multiple notifications are marked as read
   - `UserOnlineStatusChanged` - Fired when user presence changes

2. **Services**
   - `NotificationService` - Manages notification creation, delivery, and broadcasting
   - `UserPresenceService` - Tracks user online status and activity
   - `RealTimeService` (Frontend) - Handles WebSocket connections and events

3. **Middleware**
   - `TrackUserActivity` - Automatically tracks user activity and updates presence

4. **Broadcasting Channels**
   - Private user notification channels
   - Presence channels for online status
   - Post interaction channels
   - Admin monitoring channels

### Frontend Components

1. **Services**
   - `realTimeService.ts` - WebSocket connection management
   - `notificationService.ts` - Notification handling and browser notifications

2. **Composables**
   - `useRealTime.ts` - Vue composable for real-time features

3. **Vue Components**
   - `NotificationCenter.vue` - Notification display and management
   - `NotificationItem.vue` - Individual notification rendering
   - Social interaction components with real-time updates

## 🔧 Configuration

### Environment Variables

Add these to your `.env` file:

```bash
# Broadcasting Configuration
BROADCAST_DRIVER=pusher

# Pusher Configuration (for WebSocket)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https

# Alternative: Redis for broadcasting (if using Redis instead of Pusher)
# BROADCAST_DRIVER=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379

# Queue Configuration (for background notification processing)
QUEUE_CONNECTION=redis

# Mail Configuration (for email notifications)
MAIL_MAILER=smtp
# ... other mail settings
```

### Frontend Configuration

Add these to your `.env` file in the frontend:

```bash
# Pusher Configuration (must match backend)
VITE_PUSHER_APP_KEY=your_app_key
VITE_PUSHER_APP_CLUSTER=your_cluster
```

## 📦 Dependencies

### Backend Dependencies (Laravel)

The following packages are required and should be in your `composer.json`:

```json
{
  "require": {
    "pusher/pusher-php-server": "^7.2",
    "laravel/framework": "^10.0"
  }
}
```

### Frontend Dependencies (NPM)

Add these to your `package.json`:

```json
{
  "dependencies": {
    "pusher-js": "^8.4.0-rc2",
    "laravel-echo": "^1.15.3",
    "@heroicons/vue": "^2.0.18",
    "date-fns": "^3.0.6"
  }
}
```

## 🚀 Installation & Setup

### 1. Install Dependencies

```bash
# Backend
composer install

# Frontend
npm install
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Configure Broadcasting Routes

Ensure `routes/channels.php` is loaded by adding this to your `routes/web.php`:

```php
require __DIR__.'/channels.php';
```

### 4. Register Middleware

Add the activity tracking middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ... other middleware
        \App\Http\Middleware\TrackUserActivity::class,
    ],
];
```

### 5. Configure Queue Worker

For production, run queue workers to handle background notification processing:

```bash
php artisan queue:work --queue=notifications,default
```

### 6. Set Up Scheduled Tasks

Add this to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up offline users every 5 minutes
    $schedule->command('users:cleanup-offline')->everyFiveMinutes();
    
    // Clean up old notifications daily
    $schedule->call(function () {
        app(\App\Services\NotificationService::class)->cleanupOldNotifications();
    })->daily();
}
```

## 🎮 Usage Examples

### Backend Usage

#### Creating Notifications

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Create a like notification
$notificationService->createLikeNotification($liker, $post);

// Create a custom notification
$notificationService->createNotification(
    $user,
    'custom',
    'Custom Title',
    'Custom message',
    $actor,
    $relatedModel
);
```

#### Tracking User Presence

```php
use App\Services\UserPresenceService;

$presenceService = app(UserPresenceService::class);

// Mark user online
$presenceService->markUserOnline($user);

// Check if user is online
$isOnline = $presenceService->isUserOnline($user);

// Get online friends
$onlineFriends = $presenceService->getOnlineFriends($user);
```

### Frontend Usage

#### Initialize Real-Time Service

```typescript
import { useRealTime } from '@/composables/useRealTime'

const {
  initializeRealTime,
  notifications,
  unreadCount,
  onlineUsers,
  subscribeToPost
} = useRealTime()

// Initialize with auth token
await initializeRealTime(authToken, currentUser)
```

#### Subscribe to Post Updates

```typescript
// Subscribe to real-time post interactions
subscribeToPost(postId, {
  onLikeUpdate: (data) => {
    console.log('Post liked:', data)
    // Update UI
  },
  onCommentUpdate: (data) => {
    console.log('New comment:', data)
    // Update UI
  }
})
```

#### Handle Notifications

```vue
<template>
  <div>
    <NotificationCenter
      :notifications="notifications"
      :unread-count="unreadCount"
      @notification-read="handleNotificationRead"
    />
  </div>
</template>

<script setup>
import { useRealTime } from '@/composables/useRealTime'

const { notifications, unreadCount, handleNotificationRead } = useRealTime()
</script>
```

## 🔒 Security Features

### Channel Authorization

All private channels are properly authorized:

```php
// User can only access their own notification channel
Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Post interaction channels check visibility permissions
Broadcast::channel('post.{postId}.interactions', function ($user, $postId) {
    $post = \App\Models\Post::find($postId);
    return $post && $visibilityService->isPostVisibleTo($post, $user);
});
```

### Privacy Controls

- Users can control their online status visibility
- Notification preferences are respected
- Content visibility rules apply to real-time updates

## 📊 Monitoring & Analytics

### Performance Monitoring

```bash
# Monitor queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Monitor WebSocket connections (if using Pusher)
# Check your Pusher dashboard for connection statistics
```

### User Presence Analytics

```php
use App\Services\UserPresenceService;

$presenceService = app(UserPresenceService::class);

// Get current online user count
$onlineCount = $presenceService->getOnlineUserCount();

// Get activity patterns for a user
$patterns = $presenceService->getUserActivityPatterns($user, 7);
```

## 🐛 Troubleshooting

### Common Issues

1. **WebSocket Connection Failed**
   - Check Pusher credentials
   - Verify CORS settings
   - Ensure HTTPS in production

2. **Notifications Not Broadcasting**
   - Verify queue worker is running
   - Check broadcasting driver configuration
   - Ensure channels are properly authorized

3. **User Presence Not Updating**
   - Verify middleware is registered
   - Check cache configuration
   - Ensure user activity tracking is working

### Debug Commands

```bash
# Test notification creation
php artisan tinker
>>> $user = \App\Models\User::first();
>>> app(\App\Services\NotificationService::class)->createNotification($user, 'test', 'Test', 'Test message');

# Clean up offline users manually
php artisan users:cleanup-offline --dry-run

# Test broadcasting
php artisan tinker
>>> broadcast(new \App\Events\UserOnlineStatusChanged(\App\Models\User::first(), true));
```

## 🚀 Production Deployment

### Checklist

- [ ] Configure proper broadcasting driver (Pusher/Redis)
- [ ] Set up queue workers with supervisor
- [ ] Configure scheduled tasks (cron)
- [ ] Set up monitoring for WebSocket connections
- [ ] Configure proper cache backend (Redis)
- [ ] Test notification delivery end-to-end
- [ ] Set up error logging and monitoring
- [ ] Configure rate limiting for API endpoints
- [ ] Test real-time features under load

### Performance Recommendations

1. Use Redis for caching and queues
2. Configure proper queue workers for notification processing
3. Set up connection pooling for database
4. Monitor WebSocket connection limits
5. Implement proper error handling and fallbacks
6. Use CDN for static assets
7. Configure proper cache headers
8. Monitor and optimize notification query performance

## 📚 API Reference

### Notification Endpoints

```
GET    /api/notifications                 # Get user notifications
GET    /api/notifications/statistics      # Get notification stats
GET    /api/notifications/unread-count    # Get unread count
PATCH  /api/notifications/{id}/read       # Mark as read
PATCH  /api/notifications/mark-all-read   # Mark all as read
POST   /api/notifications/test            # Send test notification
```

### Broadcasting Channels

```
user.{userId}.notifications              # Private user notifications
user.{userId}.presence                   # Private user presence
user-presence                           # Public presence channel
post.{postId}.interactions              # Post interaction updates
post.{postId}.typing                    # Typing indicators
admin.notifications                     # Admin notifications
```

## 🤝 Contributing

When adding new real-time features:

1. Create appropriate broadcasting events
2. Add channel authorization in `routes/channels.php`
3. Update frontend services and composables
4. Add proper error handling
5. Write tests for new functionality
6. Update this documentation

---

*This real-time notification system provides a robust foundation for building engaging, interactive social features with proper security, performance, and scalability considerations.* 