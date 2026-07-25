<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSchedule extends Model
{
    protected $fillable = [
        'service_address_id',
        'service_type_id',
        'recurrence_type',
        'days_of_week',
        'day_of_month',
        'start_date',
        'start_time',
        'is_active',
        'description',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'start_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_schedule_user');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ServiceInstance::class, 'service_schedule_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ServiceAddress::class, 'service_address_id');
    }
}
