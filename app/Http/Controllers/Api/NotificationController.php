<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['sometimes', 'string', Rule::in(Notification::getTypes())],
            'unread_only' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $user = Auth::user();
        $options = [
            'type' => $request->input('type'),
            'unread_only' => $request->boolean('unread_only'),
            'per_page' => $request->input('per_page', 20),
            'page' => $request->input('page', 1),
        ];

        $notifications = $this->notificationService->getUserNotifications($user, $options);

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->notificationService->getNotificationStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();
        $count = Notification::getCountForUser($user, true);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Get specific notification.
     */
    public function show(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if (!$notification->isFor($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification->load(['actor', 'notifiable'])),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if (!$notification->isFor($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $this->notificationService->markAsRead($notification, $user);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if (!$notification->isFor($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $notification->markAsUnread();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification marked as unread' : 'Failed to mark notification as unread',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * Dismiss notification.
     */
    public function dismiss(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if (!$notification->isFor($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $this->notificationService->dismissNotification($notification, $user);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification dismissed' : 'Failed to dismiss notification',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Delete notification.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if (!$notification->isFor($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $success = $notification->delete();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification deleted' : 'Failed to delete notification',
        ]);
    }

    /**
     * Bulk mark notifications as read.
     */
    public function bulkMarkAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|min:1|max:100',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        $user = Auth::user();
        $notificationIds = $request->input('notification_ids');
        
        // Get notifications that belong to the user
        $notifications = Notification::whereIn('id', $notificationIds)
                                   ->where('user_id', $user->id)
                                   ->unread()
                                   ->get();

        $count = 0;
        foreach ($notifications as $notification) {
            if ($this->notificationService->markAsRead($notification, $user)) {
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'data' => [
                'marked_count' => $count,
                'requested_count' => count($notificationIds),
            ],
        ]);
    }

    /**
     * Bulk dismiss notifications.
     */
    public function bulkDismiss(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|min:1|max:100',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        $user = Auth::user();
        $notificationIds = $request->input('notification_ids');
        
        // Get notifications that belong to the user
        $notifications = Notification::whereIn('id', $notificationIds)
                                   ->where('user_id', $user->id)
                                   ->where('is_dismissed', false)
                                   ->get();

        $count = 0;
        foreach ($notifications as $notification) {
            if ($this->notificationService->dismissNotification($notification, $user)) {
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Dismissed {$count} notifications",
            'data' => [
                'dismissed_count' => $count,
                'requested_count' => count($notificationIds),
            ],
        ]);
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|min:1|max:100',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        $user = Auth::user();
        $notificationIds = $request->input('notification_ids');
        
        // Delete only notifications that belong to the user
        $count = Notification::whereIn('id', $notificationIds)
                           ->where('user_id', $user->id)
                           ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$count} notifications",
            'data' => [
                'deleted_count' => $count,
                'requested_count' => count($notificationIds),
            ],
        ]);
    }

    /**
     * Get notification types and their counts.
     */
    public function types(): JsonResponse
    {
        $user = Auth::user();
        
        $typeCounts = Notification::where('user_id', $user->id)
                                ->active()
                                ->selectRaw('type, count(*) as total, count(case when read_at is null then 1 end) as unread')
                                ->groupBy('type')
                                ->get()
                                ->keyBy('type');

        $types = collect(Notification::getTypes())->map(function ($type) use ($typeCounts) {
            $data = $typeCounts->get($type);
            return [
                'type' => $type,
                'total' => $data?->total ?? 0,
                'unread' => $data?->unread ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $types->values(),
        ]);
    }

    /**
     * Clear old notifications.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'days_old' => 'sometimes|integer|min:1|max:365',
        ]);

        $user = Auth::user();
        $daysOld = $request->input('days_old', 30);
        
        $count = Notification::cleanupOldNotifications($user, $daysOld);

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$count} old notifications",
            'data' => [
                'cleaned_count' => $count,
                'days_old' => $daysOld,
            ],
        ]);
    }

    /**
     * Test notification system (for development/admin).
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(Notification::getTypes())],
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:500',
        ]);

        $user = Auth::user();
        $type = $request->input('type');
        $title = $request->input('title', 'Test Notification');
        $message = $request->input('message', 'This is a test notification.');

        $notification = $this->notificationService->createNotification(
            $user,
            $type,
            $title,
            $message,
            $user, // Actor is the user themselves for testing
            null,
            ['test' => true],
            Notification::PRIORITY_NORMAL
        );

        return response()->json([
            'success' => true,
            'message' => 'Test notification created',
            'data' => new NotificationResource($notification),
        ]);
    }
} 