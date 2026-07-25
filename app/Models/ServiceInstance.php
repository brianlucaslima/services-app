<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceInstance extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'service_address_id',
        'service_schedule_id',
        'service_type_id',
        'description',
        'original_date',
        'date',
        'time',
        'status',
        'notes',
        'duration_hours',
        'hourly_rate',
    ];

    protected $casts = [
        'original_date' => 'date',
        'date' => 'date',
        'duration_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ServiceAddress::class, 'service_address_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ServiceSchedule::class, 'service_schedule_id');
    }

    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }
}
