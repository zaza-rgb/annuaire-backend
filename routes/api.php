<?php

use App\Http\Controllers\Api\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Api\Admin\ClaimController as AdminClaimController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\PhotoController as AdminPhotoController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Api\Admin\LanguageController as AdminLanguageController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\EventCategoryController as AdminEventCategoryController;
use App\Http\Controllers\Api\Admin\MessageReportController as AdminMessageReportController;
use App\Http\Controllers\Api\Admin\EventReportController as AdminEventReportController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\OpeningHourController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventRegistrationController;
use App\Http\Controllers\Api\EventReportController;
use App\Http\Controllers\Api\Organizer\EventRegistrationController as OrganizerRegistrationController;
use App\Http\Controllers\Api\EventConversationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MessageReportController;
use App\Http\Controllers\Api\EventMessageController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/languages', [LanguageController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/event-categories', [EventCategoryController::class, 'index']);
Route::get('/countries', [CountryController::class, 'index']);
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/{id}', [BusinessController::class, 'show']);
Route::get('/cities', [BusinessController::class, 'cities']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/conversations', [ConversationController::class, 'storeDirect']);
    Route::post('/conversations/{id}/block', [ConversationController::class, 'block']);
    Route::delete('/conversations/{id}/block', [ConversationController::class, 'unblock']);
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::post('/events/{id}/conversations', [EventConversationController::class, 'store']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store']);
    Route::post('/messages/{id}/report', [MessageReportController::class, 'store']);


    Route::post('/events', [EventController::class, 'store'])->middleware('role:organizer');

    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/businesses/{id}/favorite', [FavoriteController::class, 'store']);
    Route::delete('/businesses/{id}/favorite', [FavoriteController::class, 'destroy']);
    Route::post('/businesses/{id}/reviews', [ReviewController::class, 'store']);
    Route::post('/businesses/{id}/reports', [ReportController::class, 'store']);
    Route::post('/businesses/{id}/claims', [ClaimController::class, 'store']);
    Route::put('/businesses/{id}/opening-hours', [OpeningHourController::class, 'update']);
    Route::get('/my-businesses', [BusinessController::class, 'mine']);
    Route::put('/businesses/{id}', [BusinessController::class, 'update']);
    Route::patch('/reviews/{id}/reply', [ReviewController::class, 'reply']);
    Route::post('/businesses/{id}/photos', [PhotoController::class, 'store']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/businesses/{id}/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])->middleware('throttle:messages');
    Route::patch('/conversations/{id}/read', [MessageController::class, 'markAsRead']);

    // Événements : inscriptions et signalements (tout utilisateur connecté)
    Route::post('/events/{id}/registrations', [EventRegistrationController::class, 'store']);
    Route::delete('/events/{id}/registrations', [EventRegistrationController::class, 'destroy']);
    Route::get('/my-registrations', [EventRegistrationController::class, 'index']);
    Route::post('/events/{id}/reports', [EventReportController::class, 'store']);
    Route::post('/events/{id}/broadcast', [EventMessageController::class, 'store'])->middleware(['role:organizer', 'throttle:broadcast']);

    // Organisateur : réservé au rôle organizer
    Route::middleware('role:organizer')->prefix('organizer')->group(function () {
        Route::get('/events', [EventController::class, 'organized']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::patch('/events/{id}/cancel', [EventController::class, 'cancel']);
        Route::get('/events/{id}/registrations', [OrganizerRegistrationController::class, 'index']);
        Route::post('/events/{id}/check-in', [OrganizerRegistrationController::class, 'checkIn']);
    });

    // Administration : réservée au rôle admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/businesses', [AdminBusinessController::class, 'index']);
        Route::patch('/businesses/{id}/status', [AdminBusinessController::class, 'updateStatus']);
        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::patch('/reviews/{id}/status', [AdminReviewController::class, 'updateStatus']);
        Route::get('/claims', [AdminClaimController::class, 'index']);
        Route::patch('/claims/{id}', [AdminClaimController::class, 'update']);
        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::patch('/reports/{id}', [AdminReportController::class, 'update']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::patch('/users/{id}/status', [AdminUserController::class, 'updateStatus']);
        Route::get('/photos', [AdminPhotoController::class, 'index']);
        Route::patch('/photos/{id}/status', [AdminPhotoController::class, 'updateStatus']);
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);
        Route::post('/countries', [AdminCountryController::class, 'store']);
        Route::put('/countries/{id}', [AdminCountryController::class, 'update']);
        Route::delete('/countries/{id}', [AdminCountryController::class, 'destroy']);
        Route::post('/languages', [AdminLanguageController::class, 'store']);
        Route::put('/languages/{id}', [AdminLanguageController::class, 'update']);
        Route::delete('/languages/{id}', [AdminLanguageController::class, 'destroy']);
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::patch('/events/{id}/status', [AdminEventController::class, 'updateStatus']);
        Route::post('/event-categories', [AdminEventCategoryController::class, 'store']);
        Route::put('/event-categories/{id}', [AdminEventCategoryController::class, 'update']);
        Route::delete('/event-categories/{id}', [AdminEventCategoryController::class, 'destroy']);
        Route::get('/message-reports', [AdminMessageReportController::class, 'index']);
        Route::patch('/message-reports/{id}', [AdminMessageReportController::class, 'update']);
        Route::get('/event-reports', [AdminEventReportController::class, 'index']);
        Route::patch('/event-reports/{id}', [AdminEventReportController::class, 'update']);
    });
});