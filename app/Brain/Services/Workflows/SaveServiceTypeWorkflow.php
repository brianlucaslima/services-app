<?php

declare(strict_types=1);

namespace App\Brain\Services\Workflows;

use App\Brain\Services\Actions\CreateOrUpdateServiceTypeAction;
use Brain\Workflow;

class SaveServiceTypeWorkflow extends Workflow
{
    protected array $actions = [
        CreateOrUpdateServiceTypeAction::class,
    ];
}
