<?php

use Platform\Syltjunkie\Livewire\Dashboard;
use Platform\Syltjunkie\Livewire\EntityIndex;
use Platform\Syltjunkie\Livewire\EntityDetail;
use Platform\Syltjunkie\Livewire\EntityTypeIndex;

Route::get('/', Dashboard::class)->name('syltjunkie.dashboard');
Route::get('/entities', EntityIndex::class)->name('syltjunkie.entities.index');
Route::get('/entities/{entity}', EntityDetail::class)->name('syltjunkie.entity.detail');
Route::get('/entity-types', EntityTypeIndex::class)->name('syltjunkie.entity-types.index');
