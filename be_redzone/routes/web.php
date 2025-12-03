<?php

use App\Http\Controllers\StatementController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});


// Route::get('/statements/{subscriptionId}', [StatementController::class, 'index']);

// Route::get('/subscriptions/{subscription}/soa', [SubscriptionController::class, 'downloadSoa']);

require __DIR__.'/auth.php';
