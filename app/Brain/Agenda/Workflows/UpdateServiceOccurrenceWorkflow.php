<?php

declare(strict_types=1);

namespace App\Brain\Agenda\Workflows;

use App\Brain\Agenda\Actions\CreateOrUpdateServiceInstanceAction;
use Brain\Workflow;

class UpdateServiceOccurrenceWorkflow extends Workflow
{
    protected array $actions = [
        CreateOrUpdateServiceInstanceAction::class,
    ];
}
