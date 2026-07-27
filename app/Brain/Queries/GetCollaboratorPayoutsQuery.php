<?php

declare(strict_types=1);

namespace App\Brain\Queries;

use App\Models\Company;
use App\Models\ServiceInstance;
use App\Models\User;
use Brain\Query;
use Illuminate\Support\Carbon;

class GetCollaboratorPayoutsQuery extends Query
{
    public function __construct(
        public int $companyId,
        public string $startDate,
        public string $endDate,
        public ?int $userId = null,
        public string $payoutStatus = 'all',
        public string|int $calendarId = 'all'
    ) {}

    public function handle(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        if ($this->userId) {
            // Detailed report for a specific user
            $company = Company::findOrFail($this->companyId);
            $user = $company->users()->findOrFail($this->userId);

            $query = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereHas('users', fn ($q) => $q->where('users.id', $this->userId));

            if ($this->calendarId !== 'all') {
                $query->whereHas('address', fn ($q) => $q->where('calendar_id', $this->calendarId));
            }

            if ($this->payoutStatus !== 'all') {
                $query->where('payout_status', $this->payoutStatus);
            }

            $instances = $query->with(['address.customer', 'customer', 'users'])
                ->orderBy('date')
                ->orderBy('time')
                ->get();

            return $instances->map(fn ($inst) => [
                'id' => $inst->id,
                'date' => $inst->date->format('d/m/Y'),
                'time' => substr($inst->time, 0, 5),
                'customer_name' => $inst->customer?->name ?? ($inst->address?->customer?->name ?? 'N/A'),
                'location' => $inst->address?->label ?? '',
                'location_type' => $inst->address?->type ?? 'house',
                'description' => $inst->description,
                'total_duration' => $inst->duration_hours,
                'team_count' => $inst->users->count() ?: 1,
                'share_hours' => $inst->duration_hours / ($inst->users->count() ?: 1),
                'payout' => $user->hourlyRateFor($inst->address?->type) * ($inst->duration_hours / ($inst->users->count() ?: 1)),
                'payout_status' => $inst->payout_status ?? 'unpaid',
            ])->toArray();
        }

        // Overview report of all users
        $company = Company::findOrFail($this->companyId);
        $users = $company->users()->orderBy('name')->get();
        $reports = [];

        foreach ($users as $user) {
            $query = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id));

            if ($this->calendarId !== 'all') {
                $query->whereHas('address', fn ($q) => $q->where('calendar_id', $this->calendarId));
            }

            if ($this->payoutStatus !== 'all') {
                $query->where('payout_status', $this->payoutStatus);
            }

            $instances = $query->with(['users', 'address'])->get();
            $totalHours = 0;
            $totalPayout = 0;

            foreach ($instances as $inst) {
                $assignedCount = $inst->users->count() ?: 1;
                $shareHours = $inst->duration_hours / $assignedCount;
                $totalHours += $shareHours;
                $totalPayout += $user->hourlyRateFor($inst->address?->type) * $shareHours;
            }

            if ($totalHours > 0 || $user->role === 'collaborator') {
                $reports[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'hourly_rate' => $user->hourly_rate_house,
                    'hourly_rate_house' => $user->hourly_rate_house,
                    'hourly_rate_office' => $user->hourly_rate_office,
                    'hours' => $totalHours,
                    'payout' => $totalPayout,
                    'services_count' => $instances->count(),
                ];
            }
        }

        return $reports;
    }
}
