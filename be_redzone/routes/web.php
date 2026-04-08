<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});


require __DIR__.'/auth.php';
