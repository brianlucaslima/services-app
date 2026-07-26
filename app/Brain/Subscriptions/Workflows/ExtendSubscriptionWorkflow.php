<?php

declare(strict_types=1);

namespace App\Brain\Subscriptions\Workflows;

use App\Brain\Subscriptions\Actions\UpdateSubscriptionStatusAndExpiryAction;
use Brain\Workflow;

class ExtendSubscriptionWorkflow extends Workflow
{
    protected array $actions = [
        UpdateSubscriptionStatusAndExpiryAction::class,
    ];
}
