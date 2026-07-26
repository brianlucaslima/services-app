<?php

declare(strict_types=1);

namespace App\Brain\Collaborators\Workflows;

use App\Brain\Collaborators\Actions\CreateOrUpdateCollaboratorAction;
use Brain\Workflow;

class SaveCollaboratorWorkflow extends Workflow
{
    protected array $actions = [
        CreateOrUpdateCollaboratorAction::class,
    ];
}
