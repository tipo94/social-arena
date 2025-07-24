<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'AI-Book API is running',
        'timestamp' => now()->toISOString(),
        'sanctum' => 'enabled',
    ]);
});

// CSRF cookie endpoint for SPA authentication
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Test authentication endpoint
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
        'authenticated' => true,
        'timestamp' => now()->toISOString(),
    ]);
});

// Test endpoint to verify Sanctum middleware is working
Route::middleware(['auth:sanctum'])->get('/test-auth', function (Request $request) {
    return response()->json([
        'message' => 'Authentication successful',
        'user_id' => $request->user()->id,
        'user_email' => $request->user()->email,
        'timestamp' => now()->toISOString(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Public authentication routes
    Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
    Route::post('/register', [App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/forgot-password', [App\Http\Controllers\Auth\AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [App\Http\Controllers\Auth\AuthController::class, 'resetPassword']);
    Route::post('/check-username', [App\Http\Controllers\Auth\AuthController::class, 'checkUsername']);
    Route::post('/check-email', [App\Http\Controllers\Auth\AuthController::class, 'checkEmail']);
    
    // Email verification routes (can be accessed without auth for verification links)
    Route::get('/verify-email/{id}/{hash}', [App\Http\Controllers\Auth\AuthController::class, 'verifyEmail'])
        ->name('verification.verify');
    
    // Social authentication routes
    Route::prefix('social')->group(function () {
        Route::get('/redirect/{provider}', [App\Http\Controllers\Auth\SocialAuthController::class, 'redirectToProvider']);
        Route::get('/callback/{provider}', [App\Http\Controllers\Auth\SocialAuthController::class, 'handleProviderCallback']);
    });
    
    // Protected authentication routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout']);
        Route::post('/logout-all', [App\Http\Controllers\Auth\AuthController::class, 'logoutAll']);
        Route::get('/me', [App\Http\Controllers\Auth\AuthController::class, 'me']);
        Route::post('/refresh', [App\Http\Controllers\Auth\AuthController::class, 'refresh']);
        Route::post('/change-password', [App\Http\Controllers\Auth\AuthController::class, 'changePassword']);
        Route::post('/resend-verification', [App\Http\Controllers\Auth\AuthController::class, 'resendVerification']);
        
        // Social account management (requires authentication)
        Route::prefix('social')->group(function () {
            Route::get('/account', [App\Http\Controllers\Auth\SocialAuthController::class, 'getSocialAccount']);
            Route::post('/link/{provider}', [App\Http\Controllers\Auth\SocialAuthController::class, 'linkAccount']);
            Route::delete('/unlink', [App\Http\Controllers\Auth\SocialAuthController::class, 'unlinkAccount']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Media Upload Routes
|--------------------------------------------------------------------------
*/
Route::prefix('media')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/upload-image', [App\Http\Controllers\Api\MediaController::class, 'uploadImage']);
    Route::post('/upload-file', [App\Http\Controllers\Api\MediaController::class, 'uploadFile']);
    Route::post('/file-info', [App\Http\Controllers\Api\MediaController::class, 'getFileInfo']);
    Route::post('/temporary-url', [App\Http\Controllers\Api\MediaController::class, 'createTemporaryUrl']);
    Route::delete('/file', [App\Http\Controllers\Api\MediaController::class, 'deleteFile']);
});

/*
|--------------------------------------------------------------------------
| Email Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('email')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/test-configuration', [App\Http\Controllers\Api\EmailController::class, 'testConfiguration']);
    Route::post('/send-welcome', [App\Http\Controllers\Api\EmailController::class, 'sendWelcome']);
    Route::post('/send-verification', [App\Http\Controllers\Api\EmailController::class, 'sendVerification']);
    Route::post('/send-test-notification', [App\Http\Controllers\Api\EmailController::class, 'sendTestNotification']);
    Route::get('/stats', [App\Http\Controllers\Api\EmailController::class, 'getStats']);
    Route::post('/bulk-email', [App\Http\Controllers\Api\EmailController::class, 'sendBulkEmail']);
});

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    // Profile management routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\UserProfileController::class, 'show']);
        Route::get('/{userId}', [App\Http\Controllers\Api\UserProfileController::class, 'show']);
        Route::put('/', [App\Http\Controllers\Api\UserProfileController::class, 'updateProfile']);
        Route::post('/avatar', [App\Http\Controllers\Api\UserProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [App\Http\Controllers\Api\UserProfileController::class, 'deleteAvatar']);
        Route::post('/cover-image', [App\Http\Controllers\Api\UserProfileController::class, 'updateCoverImage']);
        Route::delete('/cover-image', [App\Http\Controllers\Api\UserProfileController::class, 'deleteCoverImage']);
        Route::put('/interests', [App\Http\Controllers\Api\UserProfileController::class, 'updateInterests']);
        Route::get('/interests', [App\Http\Controllers\Api\UserProfileController::class, 'getInterests']);
        Route::get('/completion', [App\Http\Controllers\Api\UserProfileController::class, 'getCompletionStatus']);
    });

            // Privacy settings routes
        Route::prefix('privacy')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\PrivacyController::class, 'getPrivacySettings']);
            Route::put('/', [App\Http\Controllers\Api\PrivacyController::class, 'updatePrivacySettings']);
            Route::get('/options', [App\Http\Controllers\Api\PrivacyController::class, 'getPrivacyOptions']);
            Route::post('/check-access', [App\Http\Controllers\Api\PrivacyController::class, 'checkPrivacyAccess']);
            Route::get('/audit-log', [App\Http\Controllers\Api\PrivacyController::class, 'getPrivacyAuditLog']);
        });

        // Account deletion and data management routes
        Route::prefix('account')->group(function () {
            Route::get('/deletion/info', [App\Http\Controllers\Api\AccountDeletionController::class, 'getDeletionInfo']);
            Route::get('/deletion/status', [App\Http\Controllers\Api\AccountDeletionController::class, 'getDeletionStatus']);
            Route::post('/deletion/request', [App\Http\Controllers\Api\AccountDeletionController::class, 'requestDeletion']);
            Route::post('/deletion/cancel', [App\Http\Controllers\Api\AccountDeletionController::class, 'cancelDeletion']);
            Route::post('/export-data', [App\Http\Controllers\Api\AccountDeletionController::class, 'exportData']);
            Route::post('/download-export', [App\Http\Controllers\Api\AccountDeletionController::class, 'downloadDataExport']);
            Route::post('/deactivate', [App\Http\Controllers\Api\AccountDeletionController::class, 'deactivateAccount']);
            Route::post('/reactivate', [App\Http\Controllers\Api\AccountDeletionController::class, 'reactivateAccount']);
        });
    
    // User search and discovery
    Route::get('/search', [App\Http\Controllers\Api\UserProfileController::class, 'searchUsers']);
});

/*
|--------------------------------------------------------------------------
| Posts and Content Routes
|--------------------------------------------------------------------------
*/
Route::prefix('posts')->group(function () {
    // Content management routes will be implemented in task 3.0
});

/*
|--------------------------------------------------------------------------
| Social Interaction Routes
|--------------------------------------------------------------------------
*/
Route::prefix('social')->middleware(['auth:sanctum'])->group(function () {
    // Social features routes will be implemented in task 4.0
});

/*
|--------------------------------------------------------------------------
| Post Management Routes  
|--------------------------------------------------------------------------
*/
Route::prefix('posts')->middleware(['auth:sanctum'])->group(function () {
    // Main CRUD operations
    Route::get('/', [App\Http\Controllers\Api\PostController::class, 'index']); // Feed
    Route::post('/', [App\Http\Controllers\Api\PostController::class, 'store']); // Create post
    Route::get('/{post}', [App\Http\Controllers\Api\PostController::class, 'show']); // Get specific post
    Route::put('/{post}', [App\Http\Controllers\Api\PostController::class, 'update']); // Update post
    Route::delete('/{post}', [App\Http\Controllers\Api\PostController::class, 'destroy']); // Delete post
    
    // Post interactions
    Route::post('/{post}/like', [App\Http\Controllers\Api\PostController::class, 'toggleLike']); // Like/unlike
    Route::post('/{post}/report', [App\Http\Controllers\Api\PostController::class, 'report']); // Report post
    
    // Analytics and insights
    Route::get('/{post}/analytics', [App\Http\Controllers\Api\PostController::class, 'analytics']); // Post analytics
    
    // Specific feeds
    Route::get('/user/{user}', [App\Http\Controllers\Api\PostController::class, 'userPosts']); // User's posts
    Route::get('/group/{groupId}', [App\Http\Controllers\Api\PostController::class, 'groupPosts']); // Group posts
});

/*
|--------------------------------------------------------------------------
| Messaging Routes
|--------------------------------------------------------------------------
*/
Route::prefix('messages')->middleware(['auth:sanctum'])->group(function () {
    // Messaging routes will be implemented in task 5.0
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin routes will be implemented in task 6.0
}); 