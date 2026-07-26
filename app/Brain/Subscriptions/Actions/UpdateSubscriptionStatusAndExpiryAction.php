<?php

declare(strict_types=1);

namespace App\Brain\Subscriptions\Actions;

use App\Models\Company;
use Brain\Action;

/**
 * Action UpdateSubscriptionStatusAndExpiryAction
 *
 * @property-read int $companyId
 * @property-read string $status
 * @property-read int|null $daysToExtend
 */
class UpdateSubscriptionStatusAndExpiryAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'status' => 'required|in:active,expired,trial',
            'daysToExtend' => 'nullable|integer',
        ];
    }

    public function handle(): self
    {
        $company = Company::findOrFail($this->companyId);

        if ($this->status === 'expired') {
            $company->update([
                'subscription_status' => 'expired',
                'subscription_ends_at' => now()->subDay(),
            ]);
        } else {
            $days = $this->daysToExtend ?? 0;
            $currentEndsAt = $company->subscription_ends_at;
            $baseDate = ($currentEndsAt && $currentEndsAt->isFuture()) ? $currentEndsAt : now();
            $newEndsAt = $baseDate->addDays($days);

            $company->update([
                'subscription_status' => $this->status,
                'subscription_ends_at' => $newEndsAt,
            ]);
        }

        return $this;
    }
}
