<?php

use Platform\Syltjunkie\Livewire\Dashboard;
use Platform\Syltjunkie\Livewire\EntityIndex;
use Platform\Syltjunkie\Livewire\EntityDetail;
use Platform\Syltjunkie\Livewire\EntityTypeIndex;
use Platform\Syltjunkie\Livewire\TrendSignalIndex;
use Platform\Syltjunkie\Livewire\RankingIndex;
use Platform\Syltjunkie\Livewire\PageChangeIndex;
use Platform\Syltjunkie\Livewire\ReviewIndex;
use Platform\Syltjunkie\Livewire\MapIndex;
use Platform\Syltjunkie\Livewire\ImageIndex;
use Platform\Syltjunkie\Livewire\ChannelIndex;
use Platform\Syltjunkie\Livewire\ChannelPostIndex;
use Platform\Syltjunkie\Livewire\ChannelPostComposer;

Route::get('/', Dashboard::class)->name('syltjunkie.dashboard');
Route::get('/entities', EntityIndex::class)->name('syltjunkie.entities.index');
Route::get('/entities/{entity}', EntityDetail::class)->name('syltjunkie.entity.detail');
Route::get('/entity-types', EntityTypeIndex::class)->name('syltjunkie.entity-types.index');
Route::get('/trend-signals', TrendSignalIndex::class)->name('syltjunkie.trend-signals.index');
Route::get('/rankings', RankingIndex::class)->name('syltjunkie.rankings.index');
Route::get('/page-changes', PageChangeIndex::class)->name('syltjunkie.page-changes.index');
Route::get('/reviews', ReviewIndex::class)->name('syltjunkie.reviews.index');
Route::get('/map', MapIndex::class)->name('syltjunkie.map.index');
Route::get('/images', ImageIndex::class)->name('syltjunkie.images.index');
Route::get('/channels', ChannelIndex::class)->name('syltjunkie.channels.index');
Route::get('/posts', ChannelPostIndex::class)->name('syltjunkie.posts.index');
Route::get('/posts/create', ChannelPostComposer::class)->name('syltjunkie.posts.create');
