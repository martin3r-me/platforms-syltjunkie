<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjEntityCtaConfig extends Model
{
    protected $table = 'sj_entity_cta_config';

    protected $fillable = [
        'entity_id',
        'cta_type',
        'target_url',
        'phone',
        'is_active',
        'tracking_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }
}
