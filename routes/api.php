<?php

use Illuminate\Support\Facades\Route;
use Platform\Syltjunkie\Http\Controllers\Api\ContentApiController;
use Platform\Syltjunkie\Http\Controllers\Api\EntityApiController;
use Platform\Syltjunkie\Http\Controllers\Api\EntityTypeApiController;
use Platform\Syltjunkie\Http\Controllers\Api\LandingApiController;
use Platform\Syltjunkie\Http\Controllers\Api\MapApiController;
use Platform\Syltjunkie\Http\Controllers\Api\WeatherApiController;
use Platform\Syltjunkie\Http\Controllers\Api\ShopApiController;
use Platform\Syltjunkie\Http\Controllers\Api\ImageApiController;
use Platform\Syltjunkie\Http\Controllers\Api\OwnerAuthController;
use Platform\Syltjunkie\Http\Controllers\Api\OwnerApiController;

// Owner Auth (Sanctum-Token vom Frontend reicht)
Route::post('/auth/request', [OwnerAuthController::class, 'requestLink']);
Route::post('/auth/verify', [OwnerAuthController::class, 'verify']);

// Owner API (zusätzlich eigenes Bearer-Token via sj.owner.auth)
Route::middleware('sj.owner.auth')->group(function () {
    Route::get('/owner/me', [OwnerApiController::class, 'me']);
    Route::get('/owner/entities/{slug}', [OwnerApiController::class, 'entity']);
    Route::put('/owner/entities/{slug}', [OwnerApiController::class, 'updateEntity']);
});

Route::get('/entities', [EntityApiController::class, 'index']);
Route::get('/entities/{slug}', [EntityApiController::class, 'show']);
Route::get('/entities/{slug}/events', [EntityApiController::class, 'events']);
Route::get('/entities/{slug}/keywords', [EntityApiController::class, 'keywords']);
Route::get('/entity-types', [EntityTypeApiController::class, 'index']);
Route::get('/groups', [EntityTypeApiController::class, 'groups']);
Route::get('/landing', [LandingApiController::class, 'index']);
Route::get('/content', [ContentApiController::class, 'index']);
Route::get('/content/{slug}', [ContentApiController::class, 'show']);
Route::get('/map', [MapApiController::class, 'index']);
Route::get('/weather', [WeatherApiController::class, 'index']);
Route::get('/weather/{slug}', [WeatherApiController::class, 'show']);
Route::get('/images', [ImageApiController::class, 'index']);
Route::get('/images/{uuid}', [ImageApiController::class, 'show']);
Route::get('/shop/products', [ShopApiController::class, 'products']);
Route::get('/shop/products/{slug}', [ShopApiController::class, 'product']);
Route::post('/shop/orders', [ShopApiController::class, 'createOrder']);
