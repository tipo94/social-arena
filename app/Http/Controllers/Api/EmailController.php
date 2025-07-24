<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    /**
     * Test email configuration
     */
    public function testConfiguration(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $result = $this->emailService->testEmailConfiguration($request->input('email'));

        return response()->json($result);
    }

    /**
     * Send welcome email
     */
    public function sendWelcome(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->emailService->sendWelcomeEmail($user);

        return response()->json($result);
    }

    /**
     * Send email verification
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->emailService->sendEmailVerification($user);

        return response()->json($result);
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:friend_request,comment,like,mention,group_invitation,message',
            'data' => 'sometimes|array',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $data = $request->input('data', []);

        // Default test data for each notification type
        $defaultData = [
            'friend_request' => [
                'sender_name' => 'John Doe',
            ],
            'comment' => [
                'commenter_name' => 'Jane Smith',
                'post_title' => 'Sample Post Title',
                'post_id' => 1,
                'preview' => 'This is a great post!',
            ],
            'like' => [
                'liker_name' => 'Mike Johnson',
                'post_title' => 'Sample Post Title',
                'post_id' => 1,
            ],
            'mention' => [
                'mentioner_name' => 'Sarah Wilson',
                'post_id' => 1,
                'preview' => 'Hey @' . $user->name . ', check this out!',
            ],
            'group_invitation' => [
                'group_name' => 'Photography Enthusiasts',
                'group_id' => 1,
            ],
            'message' => [
                'sender_name' => 'Alex Brown',
                'preview' => 'Hey! How are you doing?',
            ],
        ];

        $notificationData = array_merge($defaultData[$type] ?? [], $data);

        $result = $this->emailService->sendNotificationEmail($user, $type, $notificationData);

        return response()->json($result);
    }

    /**
     * Get email statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $period = $request->input('period', '24h');
        
        $stats = $this->emailService->getEmailStats($period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Send bulk email (admin only)
     */
    public function sendBulkEmail(Request $request): JsonResponse
    {
        // This would require admin middleware
        $request->validate([
            'recipients' => 'required|array',
            'recipients.*' => 'email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Implementation would depend on requirements
        return response()->json([
            'success' => false,
            'message' => 'Bulk email functionality not yet implemented',
        ], 501);
    }
} 