<?php

declare(strict_types=1);

namespace App\Brain\Queries;

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
        public string $addressType = 'all'
    ) {}

    public function handle(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        if ($this->userId) {
            // Detailed report for a specific user
            $user = User::where('company_id', $this->companyId)->findOrFail($this->userId);

            $query = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereHas('users', fn ($q) => $q->where('users.id', $this->userId));

            if ($this->addressType !== 'all') {
                $query->whereHas('address', fn ($q) => $q->where('type', $this->addressType));
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
                'payout' => $user->hourly_rate * ($inst->duration_hours / ($inst->users->count() ?: 1)),
                'payout_status' => $inst->payout_status ?? 'unpaid',
            ])->toArray();
        }

        // Overview report of all users
        $users = User::where('company_id', $this->companyId)->orderBy('name')->get();
        $reports = [];

        foreach ($users as $user) {
            $query = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id));

            if ($this->addressType !== 'all') {
                $query->whereHas('address', fn ($q) => $q->where('type', $this->addressType));
            }

            if ($this->payoutStatus !== 'all') {
                $query->where('payout_status', $this->payoutStatus);
            }

            $instances = $query->with(['users'])->get();
            $totalHours = 0;
            $totalPayout = 0;

            foreach ($instances as $inst) {
                $assignedCount = $inst->users->count() ?: 1;
                $shareHours = $inst->duration_hours / $assignedCount;
                $totalHours += $shareHours;
                $totalPayout += $user->hourly_rate * $shareHours;
            }

            if ($totalHours > 0 || $user->role === 'collaborator') {
                $reports[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'hourly_rate' => $user->hourly_rate,
                    'hours' => $totalHours,
                    'payout' => $totalPayout,
                    'services_count' => $instances->count(),
                ];
            }
        }

        return $reports;
    }
}
