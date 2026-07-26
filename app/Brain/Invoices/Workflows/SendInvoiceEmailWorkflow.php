<?php

declare(strict_types=1);

namespace App\Brain\Invoices\Workflows;

use App\Brain\Invoices\Actions\GenerateInvoicePdfAction;
use App\Brain\Invoices\Actions\SendInvoiceEmailAction;
use Brain\Workflow;

class SendInvoiceEmailWorkflow extends Workflow
{
    protected array $actions = [
        GenerateInvoicePdfAction::class,
        SendInvoiceEmailAction::class,
    ];
}
