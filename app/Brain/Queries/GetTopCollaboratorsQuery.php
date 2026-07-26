<?php

declare(strict_types=1);

namespace App\Brain\Queries;

use App\Models\ServiceInstance;
use App\Models\User;
use Brain\Query;

class GetTopCollaboratorsQuery extends Query
{
    public function __construct(
        public int $companyId
    ) {}

    public function handle(): array
    {
        $collaborators = User::where('company_id', $this->companyId)->get();

        $completedInstancesThisMonth = ServiceInstance::where('company_id', $this->companyId)
            ->where('status', 'completed')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->with('users')
            ->get();

        $collabHours = [];
        foreach ($collaborators as $collab) {
            $hours = 0;
            foreach ($completedInstancesThisMonth as $inst) {
                if ($inst->users->contains($collab->id)) {
                    $hours += $inst->duration_hours / ($inst->users->count() ?: 1);
                }
            }
            $collabHours[] = [
                'user' => $collab,
                'hours' => $hours,
                'payout' => $hours * $collab->hourly_rate,
            ];
        }

        usort($collabHours, fn ($a, $b) => $b['hours'] <=> $a['hours']);

        return array_slice($collabHours, 0, 5);
    }
}
