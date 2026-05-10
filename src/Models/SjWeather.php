<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjWeather extends Model
{
    protected $table = 'sj_weather';

    protected $fillable = [
        'team_id', 'entity_id', 'date', 'record_type',
        'temperature_min', 'temperature_max', 'temperature_avg',
        'precipitation_mm', 'wind_speed_avg', 'wind_gust_max', 'wind_direction',
        'cloud_cover_avg', 'pressure_msl', 'sunshine_hours',
        'visibility_avg', 'relative_humidity_avg',
        'condition', 'icon', 'hourly_data', 'dwd_station_id',
    ];

    protected $casts = [
        'date' => 'date',
        'hourly_data' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeForecast($query)
    {
        return $query->where('record_type', 'forecast')
            ->where('date', '>=', today());
    }

    public function scopeCurrent($query)
    {
        return $query->where('record_type', 'current');
    }
}
