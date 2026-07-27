<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'number',
        'date',
        'expiry_date',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'expiry_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function hasActiveInvoice(): bool
    {
        return $this->invoices()->where('status', '!=', 'cancelled')->exists();
    }
}
