<?php

use Illuminate\Support\Facades\Route;
use Platform\Syltjunkie\Http\Controllers\Api\ContentApiController;
use Platform\Syltjunkie\Http\Controllers\Api\EntityApiController;
use Platform\Syltjunkie\Http\Controllers\Api\EntityTypeApiController;
use Platform\Syltjunkie\Http\Controllers\Api\LandingApiController;
use Platform\Syltjunkie\Http\Controllers\Api\MapApiController;

Route::get('/entities', [EntityApiController::class, 'index']);
Route::get('/entities/{slug}', [EntityApiController::class, 'show']);
Route::get('/entity-types', [EntityTypeApiController::class, 'index']);
Route::get('/groups', [EntityTypeApiController::class, 'groups']);
Route::get('/landing', [LandingApiController::class, 'index']);
Route::get('/content', [ContentApiController::class, 'index']);
Route::get('/content/{slug}', [ContentApiController::class, 'show']);
Route::get('/map', [MapApiController::class, 'index']);
