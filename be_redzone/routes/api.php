<?php

use App\Http\Controllers\Api\AddonController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ServiceCreditController;
use App\Http\Controllers\Api\SOAController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\SubscriptionHistoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;

use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\BillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);

Route::get('/subscribers', [SubscriberController::class, 'getSubscribers']);
Route::get('/subscribers/search', [SubscriberController::class, 'search']);
Route::get('/subscribers-with-dues', [SubscriberController::class, 'getSubscribersWithDues']);
// Route::get('/subscriptions', [SubscriptionController::class, 'index']);
// Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);


// Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::apiResource('plans', PlanController::class);
Route::apiResource('subscribers', SubscriberController::class);
Route::apiResource('subscriptions', SubscriptionController::class);

Route::apiResource('addons', AddonController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('serviceCredits', ServiceCreditController::class);



Route::get('/dues', [BillingController::class, 'subscribersWithDues']);
// Route::get('/subscriptions/{subscription}/soa-json', [BillingController::class, 'soaJson']);
// Route::get('/subscriptions/{subscription}/soa', [BillingController::class, 'soaPdf']);
// Route::post('/subscriptions/{subscription}/send-soa', [BillingController::class, 'sendSoa']);
Route::get('/subscriptions/{subscription}/soa', [SOAController::class, 'download'])->name('subscriptions.soa.download');
Route::post('/subscriptions/{subscription}/soa/email', [SOAController::class, 'email'])->name('subscriptions.soa.email');

Route::get('/subscriptions/{subscription}/soa-json', [BillingController::class, 'soaJson']);
Route::get('/subscriptions/{subscription}/soa',      [BillingController::class, 'soaPdf']);
Route::post('/subscriptions/{subscription}/send-soa', [BillingController::class, 'sendSoa']);

Route::get('/subscriptions/{subscription}/history', [SubscriptionHistoryController::class, 'index']);

