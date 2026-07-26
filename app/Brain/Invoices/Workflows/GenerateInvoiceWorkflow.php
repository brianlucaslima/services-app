<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Workflows;

use App\Brain\Invoices\Actions\CreateInvoiceAction;
use App\Brain\Invoices\Actions\CreateInvoiceItemsAction;
use Brain\Workflow;

class GenerateInvoiceWorkflow extends Workflow
{
    protected array $actions = [
        CreateInvoiceAction::class,
        CreateInvoiceItemsAction::class,
    ];
}
