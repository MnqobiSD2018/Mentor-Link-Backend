<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EarningsController;
use App\Http\Controllers\Api\MenteeController;
use App\Http\Controllers\Api\MentorshipSessionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminVerificationController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\AdminAnalyticsController;
use App\Http\Controllers\Api\AdminSecurityController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SessionController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // User profile routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/dashboard/mentee', [DashboardController::class, 'mentee']);
    Route::get('/dashboard/mentor', [DashboardController::class, 'mentor']);
    Route::get('/dashboard/admin', [DashboardController::class, 'admin']);

    Route::get('/mentors', [MenteeController::class, 'mentors']);
    Route::get('/mentors/{id}', [MenteeController::class, 'showMentor']);
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [MentorshipSessionController::class, 'store']);
    Route::put('/sessions/{id}', [SessionController::class, 'update']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/ratings', [RatingController::class, 'store']);

    // Messaging routes
    Route::get('/messages/conversations', [MessageController::class, 'index']);
    Route::post('/conversations', [MessageController::class, 'createConversation']);
    Route::get('/messages/{conversationId}', [MessageController::class, 'show']);
    Route::post('/messages/{conversationId}', [MessageController::class, 'store']);

    // Mentor Availability routes
    Route::get('/availability/slots', [AvailabilityController::class, 'getSlots']);
    Route::post('/availability/slots', [AvailabilityController::class, 'addSlot']);
    Route::delete('/availability/slots/{id}', [AvailabilityController::class, 'deleteSlot']);

    Route::get('/availability/blocked', [AvailabilityController::class, 'getBlockedDates']);
    Route::post('/availability/blocked', [AvailabilityController::class, 'addBlockedDate']);
    Route::delete('/availability/blocked/{id}', [AvailabilityController::class, 'deleteBlockedDate']);

    // Reviews routes
    Route::get('/reviews/mentor', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/mentor/{mentorId}', [ReviewController::class, 'mentorReviews']);

    // Mentor Earnings routes
    Route::get('/earnings/mentor', [EarningsController::class, 'index']);
    Route::post('/earnings/withdraw', [EarningsController::class, 'withdraw']);

    // Admin User Management routes
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/admin/users/{id}/status', [AdminUserController::class, 'updateStatus']);
    Route::delete('/admin/users/{id}', [AdminUserController::class, 'destroy']);

    // Admin Verification Management routes
    Route::get('/admin/verifications', [AdminVerificationController::class, 'index']);
    Route::post('/admin/verifications/{id}/approve', [AdminVerificationController::class, 'approve']);
    Route::post('/admin/verifications/{id}/reject', [AdminVerificationController::class, 'reject']);

    // Admin Payment Management routes
    Route::get('/admin/payments', [AdminPaymentController::class, 'index']);

    // Admin Analytics routes
    Route::get('/admin/analytics', [AdminAnalyticsController::class, 'index']);

    // Admin Security routes
    Route::get('/admin/security/stats', [AdminSecurityController::class, 'stats']);
    Route::get('/admin/security/logs', [AdminSecurityController::class, 'logs']);
    Route::put('/admin/security/settings', [AdminSecurityController::class, 'updateSetting']);
});
