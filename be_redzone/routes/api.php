<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\{
    SubscriberController,
    PlanController,
    SubscriptionController,
    AddonController,
    PaymentController,
    ServiceCreditController
};


Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);

Route::get('/subscribers', [SubscriberController::class, 'getSubscribers']);
Route::get('/subscribers-with-dues', [SubscriberController::class, 'getSubscribersWithDues']);
Route::get('/subscriptions', [SubscriptionController::class, 'index']);
Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);


// Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::apiResource('plans', PlanController::class);
Route::apiResource('subscribers', SubscriberController::class);


Route::get('/subscribers/with-dues', [SubscriberController::class, 'subscribersWithDues'])->name('subscribers.with-dues');


// Breeze API already registers /user in routes/api.php

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('subscribers', SubscriberController::class);
    Route::apiResource('plans',       PlanController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::apiResource('addons',        AddonController::class);
    Route::apiResource('payments',      PaymentController::class);
    Route::apiResource('service-credits', ServiceCreditController::class);
});