<?php

declare(strict_types=1);

namespace App\Brain\Customers\Workflows;

use App\Brain\Customers\Actions\CreateOrUpdateServiceAddressAction;
use App\Brain\Customers\Actions\SyncServiceSchedulesAction;
use Brain\Workflow;

class SaveServiceAddressWorkflow extends Workflow
{
    protected array $actions = [
        CreateOrUpdateServiceAddressAction::class,
        SyncServiceSchedulesAction::class,
    ];
}
