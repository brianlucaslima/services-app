<?php

declare(strict_types=1);

namespace App\Brain\Queries;

use App\Models\Company;
use App\Models\ServiceInstance;
use Brain\Query;

class GetTopCollaboratorsQuery extends Query
{
    public function __construct(
        public int $companyId
    ) {}

    public function handle(): array
    {
        $company = Company::findOrFail($this->companyId);
        $collaborators = $company->users()->get();

        $completedInstancesThisMonth = ServiceInstance::where('company_id', $this->companyId)
            ->where('status', 'completed')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->with(['users', 'address'])
            ->get();

        $collabHours = [];
        foreach ($collaborators as $collab) {
            $hours = 0;
            $payout = 0;
            foreach ($completedInstancesThisMonth as $inst) {
                if ($inst->users->contains($collab->id)) {
                    $shareHours = $inst->duration_hours / ($inst->users->count() ?: 1);
                    if (($inst->billing_type ?? 'hourly') === 'hourly') {
                        $hours += $shareHours;
                    }
                    $payout += $shareHours * $collab->hourlyRateFor($inst->address?->type);
                }
            }
            $collabHours[] = [
                'user' => $collab,
                'hours' => $hours,
                'payout' => $payout,
            ];
        }

        usort($collabHours, fn ($a, $b) => $b['hours'] <=> $a['hours']);

        return array_slice($collabHours, 0, 5);
    }
}
