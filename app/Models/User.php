<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'company_id', 'role', 'hourly_rate', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public ?float $tempHourlyRate = null;

    protected static function booted()
    {
        static::saving(function ($user) {
            if (array_key_exists('hourly_rate', $user->attributes)) {
                $user->tempHourlyRate = (float) $user->attributes['hourly_rate'];
                unset($user->attributes['hourly_rate']);
            }
        });

        static::saved(function ($user) {
            $companyId = $user->company_id;

            if ($companyId) {
                $pivotData = [];
                if ($user->tempHourlyRate !== null) {
                    $pivotData['hourly_rate'] = $user->tempHourlyRate;
                    $user->tempHourlyRate = null;
                }

                // If role is set on the user model, sync it to the pivot
                if (array_key_exists('role', $user->attributes)) {
                    $pivotData['role'] = $user->attributes['role'];
                }

                if (! empty($pivotData)) {
                    $user->companies()->syncWithPivotValues([$companyId], $pivotData, false);
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['role', 'hourly_rate'])
            ->withTimestamps();
    }

    public function getRoleAttribute()
    {
        // Try to get company-specific role first
        $companyRole = $this->companies()->where('companies.id', $this->company_id)->first()?->pivot->role;

        // Fallback to the global role in users table
        return $companyRole ?? ($this->attributes['role'] ?? 'collaborator');
    }

    public function getHourlyRateAttribute()
    {
        return (float) ($this->companies()->where('companies.id', $this->company_id)->first()?->pivot->hourly_rate ?? 0.00);
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(ServiceSchedule::class, 'service_schedule_user');
    }

    public function instances(): BelongsToMany
    {
        return $this->belongsToMany(ServiceInstance::class, 'service_instance_user');
    }
}
