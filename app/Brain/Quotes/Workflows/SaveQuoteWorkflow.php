<?php

declare(strict_types=1);

namespace App\Brain\Quotes\Workflows;

use App\Brain\Quotes\Actions\CreateQuoteAction;
use App\Brain\Quotes\Actions\CreateQuoteItemsAction;
use Brain\Workflow;

class SaveQuoteWorkflow extends Workflow
{
    protected array $actions = [
        CreateQuoteAction::class,
        CreateQuoteItemsAction::class,
    ];
}
