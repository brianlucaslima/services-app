<?php

declare(strict_types=1);

namespace App\Brain\Queries;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceInstance;
use App\Models\User;
use Brain\Query;

class GetDashboardMetricsQuery extends Query
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $role
    ) {}

    public function handle(): array
    {
        if ($this->role === 'management') {
            // 1. Monthly Revenue (Paid & Sent invoices this month)
            $monthlyRevenue = Invoice::where('company_id', $this->companyId)
                ->whereIn('status', ['paid', 'sent'])
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('total_amount');

            // 2. Pending Payouts (Unpaid completed services for all collaborators)
            $instances = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->where('payout_status', 'unpaid')
                ->with('users')
                ->get();

            $pendingPayout = 0;
            foreach ($instances as $inst) {
                $teamCount = $inst->users->count() ?: 1;
                $shareHours = $inst->duration_hours / $teamCount;
                foreach ($inst->users as $u) {
                    $pendingPayout += $u->hourly_rate * $shareHours;
                }
            }

            // 3. Active Customers
            $activeCustomers = Customer::where('company_id', $this->companyId)
                ->where('is_active', true)
                ->count();

            // 4. Completed Services This Month
            $completedServices = ServiceInstance::where('company_id', $this->companyId)
                ->where('status', 'completed')
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count();

            return [
                'monthlyRevenue' => $monthlyRevenue,
                'pendingPayout' => $pendingPayout,
                'activeCustomers' => $activeCustomers,
                'completedServices' => $completedServices,
            ];
        }

        // Collaborator metrics
        $collab = User::where('company_id', $this->companyId)->findOrFail($this->userId);

        // 1. Completed Hours this month
        $collabInstancesThisMonth = ServiceInstance::where('company_id', $this->companyId)
            ->where('status', 'completed')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->whereHas('users', fn ($q) => $q->where('users.id', $this->userId))
            ->with('users')
            ->get();

        $completedHours = 0;
        foreach ($collabInstancesThisMonth as $inst) {
            $teamCount = $inst->users->count() ?: 1;
            $completedHours += $inst->duration_hours / $teamCount;
        }

        // 2. Earnings this month
        $earningsThisMonth = $completedHours * $collab->hourly_rate;

        // 3. Pending Payout (Unpaid completed services)
        $unpaidInstances = ServiceInstance::where('company_id', $this->companyId)
            ->where('status', 'completed')
            ->where('payout_status', 'unpaid')
            ->whereHas('users', fn ($q) => $q->where('users.id', $this->userId))
            ->with('users')
            ->get();

        $pendingPayout = 0;
        foreach ($unpaidInstances as $inst) {
            $teamCount = $inst->users->count() ?: 1;
            $pendingPayout += $collab->hourly_rate * ($inst->duration_hours / $teamCount);
        }

        // 4. Assigned Schedules Count
        $assignedSchedules = $collab->schedules()->where('is_active', true)->count();

        return [
            'completedHours' => $completedHours,
            'earningsThisMonth' => $earningsThisMonth,
            'pendingPayout' => $pendingPayout,
            'assignedSchedules' => $assignedSchedules,
        ];
    }
}
