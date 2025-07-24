<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountDeletionController extends Controller
{
    public function __construct(
        protected AccountDeletionService $deletionService
    ) {}

    /**
     * Request account deletion
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'confirmation' => ['required', 'boolean', 'accepted'],
        ], [
            'password.required' => 'Password confirmation is required for account deletion.',
            'confirmation.accepted' => 'You must confirm that you understand account deletion is irreversible.',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $result = $this->deletionService->requestDeletion(
            $user,
            $request->reason,
            $request->password
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Cancel account deletion request
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->deletionService->cancelDeletion($user);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Get account deletion status
     */
    public function getDeletionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->deletionService->getDeletionStatus($user);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Export user data for GDPR compliance
     */
    public function exportData(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $result = $this->deletionService->exportUserData($user);

        if ($result['success']) {
            // For large exports, you might want to queue this and send via email
            // For now, return the data directly (consider size limits)
            return response()->json([
                'success' => true,
                'message' => 'User data exported successfully',
                'data' => $result['data'],
            ]);
        }

        return response()->json($result, 500);
    }

    /**
     * Download user data export as JSON file
     */
    public function downloadDataExport(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $result = $this->deletionService->exportUserData($user);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        // Create temporary file
        $filename = "user_data_export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . '.json';
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Write data to file
        file_put_contents($tempPath, json_encode($result['data'], JSON_PRETTY_PRINT));

        // Return file download and schedule cleanup
        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/json',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get deletion information and options
     */
    public function getDeletionInfo(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'grace_period_days' => AccountDeletionService::GRACE_PERIOD_DAYS,
                'what_gets_deleted' => [
                    'Profile information and settings',
                    'All posts and comments',
                    'Friend connections and messages',
                    'Uploaded files and media',
                    'Reading preferences and history',
                    'Privacy settings',
                    'API tokens and sessions',
                ],
                'what_happens' => [
                    'Account is immediately deactivated',
                    'Profile becomes hidden from other users',
                    'You can still login during grace period',
                    'Deletion request can be cancelled anytime during grace period',
                    'After grace period, deletion becomes permanent and irreversible',
                ],
                'before_deletion' => [
                    'Export your data if you want to keep a copy',
                    'Inform friends you want to stay in touch with',
                    'Download any important files or media',
                    'Consider deactivation instead of permanent deletion',
                ],
                'alternatives' => [
                    'account_deactivation' => 'Temporarily disable your account',
                    'privacy_settings' => 'Make your profile completely private',
                    'take_a_break' => 'Disable notifications and logout',
                ],
            ],
        ]);
    }

    /**
     * Immediately deactivate account (alternative to deletion)
     */
    public function deactivateAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Check if account is already scheduled for deletion
        if ($user->deletion_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'Account is scheduled for deletion. Cancel deletion first.',
                'errors' => ['account' => ['Account deletion is pending.']],
            ], 422);
        }

        // Deactivate account
        $user->update([
            'is_active' => false,
        ]);

        // Revoke all current sessions
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account has been deactivated successfully',
            'data' => [
                'deactivated_at' => now(),
                'can_reactivate' => true,
                'reactivation_method' => 'Login with your credentials',
            ],
        ]);
    }

    /**
     * Reactivate a deactivated account
     */
    public function reactivateAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if account was scheduled for deletion
        if ($user->deletion_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reactivate - account is scheduled for deletion',
                'errors' => ['account' => ['Cancel deletion request first.']],
            ], 422);
        }

        // Reactivate account
        $user->update([
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account has been reactivated successfully',
            'data' => [
                'reactivated_at' => now(),
                'is_active' => true,
            ],
        ]);
    }
} 