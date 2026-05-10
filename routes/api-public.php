<?php

use Illuminate\Support\Facades\Route;
use Platform\Syltjunkie\Http\Controllers\Api\OwnerAuthController;

// Owner Auth — öffentlich, kein Sanctum-Token nötig
Route::post('/auth/request', [OwnerAuthController::class, 'requestLink']);
Route::post('/auth/verify', [OwnerAuthController::class, 'verify']);
