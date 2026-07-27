<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
#[Fillable(['name', 'email', 'password', 'company_id', 'role', 'hourly_rate', 'hourly_rate_house', 'hourly_rate_office', 'locale'])]
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

    public ?float $tempHourlyRateHouse = null;

    public ?float $tempHourlyRateOffice = null;

    protected static function booted()
    {
        static::saving(function ($user) {
            if (array_key_exists('hourly_rate', $user->getAttributes())) {
                $user->tempHourlyRate = (float) $user->getAttributes()['hourly_rate'];
                unset($user['hourly_rate']);
            }
            if (array_key_exists('hourly_rate_house', $user->getAttributes())) {
                $user->tempHourlyRateHouse = (float) $user->getAttributes()['hourly_rate_house'];
                unset($user['hourly_rate_house']);
            }
            if (array_key_exists('hourly_rate_office', $user->getAttributes())) {
                $user->tempHourlyRateOffice = (float) $user->getAttributes()['hourly_rate_office'];
                unset($user['hourly_rate_office']);
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
                if ($user->tempHourlyRateHouse !== null) {
                    $pivotData['hourly_rate_house'] = $user->tempHourlyRateHouse;

                    // Sync to collaborator_calendar_rates
                    $calendar = $user->company?->calendars()->where('slug', 'house')->first();
                    if ($calendar) {
                        CollaboratorCalendarRate::updateOrCreate([
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'calendar_id' => $calendar->id,
                        ], [
                            'hourly_rate' => $user->tempHourlyRateHouse,
                        ]);
                    }

                    $user->tempHourlyRateHouse = null;
                }
                if ($user->tempHourlyRateOffice !== null) {
                    $pivotData['hourly_rate_office'] = $user->tempHourlyRateOffice;

                    // Sync to collaborator_calendar_rates
                    $calendar = $user->company?->calendars()->where('slug', 'office')->first();
                    if ($calendar) {
                        CollaboratorCalendarRate::updateOrCreate([
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'calendar_id' => $calendar->id,
                        ], [
                            'hourly_rate' => $user->tempHourlyRateOffice,
                        ]);
                    }

                    $user->tempHourlyRateOffice = null;
                }

                // If role was explicitly changed during this save, sync it to the pivot
                if ($user->isDirty('role')) {
                    $pivotData['role'] = $user->role;
                }

                // If no pivot record exists at all for this company, create one with default role
                $pivotExists = $user->companies()->where('companies.id', $companyId)->exists();
                if (! $pivotExists) {
                    if (! isset($pivotData['role'])) {
                        $pivotData['role'] = $user->role ?? 'collaborator';
                    }
                }

                if (! empty($pivotData) || ! $pivotExists) {
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
            ->withPivot(['role', 'hourly_rate', 'hourly_rate_house', 'hourly_rate_office'])
            ->withTimestamps();
    }

    public function getRoleAttribute()
    {
        // Try to get company-specific role first
        $companyRole = $this->companies()->where('companies.id', $this->company_id)->first()?->pivot->role;

        // Fallback to the global role in users table
        return $companyRole ?? ($this->attributes['role'] ?? 'collaborator');
    }

    public function calendarRates(): HasMany
    {
        return $this->hasMany(CollaboratorCalendarRate::class);
    }

    public function getHourlyRateAttribute()
    {
        return (float) ($this->companies()->where('companies.id', $this->company_id)->first()?->pivot->hourly_rate ?? 0.00);
    }

    public function getHourlyRateHouseAttribute()
    {
        return $this->hourlyRateFor('house');
    }

    public function getHourlyRateOfficeAttribute()
    {
        return $this->hourlyRateFor('office');
    }

    public function hourlyRateFor(int|string|null $calendarId): float
    {
        if (empty($calendarId)) {
            return 0.00;
        }

        // If it is a string (legacy 'house'/'office'), we resolve the correct ID matching that slug
        if (is_string($calendarId)) {
            $calendar = $this->company?->calendars()->where('slug', $calendarId)->first();
            $calendarId = $calendar?->id;
        }

        if (empty($calendarId)) {
            return 0.00;
        }

        return (float) ($this->calendarRates()->where('calendar_id', $calendarId)->first()?->hourly_rate ?? 0.00);
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
