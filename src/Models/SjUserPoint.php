<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjUserPoint extends Model
{
    protected $table = 'sj_user_points';

    protected $fillable = [
        'team_id',
        'sj_user_id',
        'action',
        'points',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(SjUser::class, 'sj_user_id');
    }
}
