<?php

declare(strict_types=1);

namespace App\Brain\Customers\Workflows;

use App\Brain\Customers\Actions\CreateOrUpdateCustomerAction;
use Brain\Workflow;

class SaveCustomerWorkflow extends Workflow
{
    protected array $actions = [
        CreateOrUpdateCustomerAction::class,
    ];
}
