<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjCtaDefault extends Model
{
    protected $table = 'sj_cta_defaults';

    protected $fillable = [
        'team_id',
        'entity_type_id',
        'cta_type',
        'cta_label',
        'cta_icon',
        'priority',
    ];

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(SjEntityType::class, 'entity_type_id');
    }
}
