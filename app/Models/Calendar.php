<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Calendar extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'slug',
    ];

    protected static function booted()
    {
        static::creating(function ($calendar) {
            $calendar->slug = (string) Str::slug($calendar->name);
        });
        static::updating(function ($calendar) {
            $calendar->slug = (string) Str::slug($calendar->name);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ServiceAddress::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CollaboratorCalendarRate::class);
    }
}
