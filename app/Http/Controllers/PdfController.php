<?php

namespace App\Http\Controllers;

use App\Brain\Queries\GetCollaboratorPayoutsQuery;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class PdfController extends Controller
{
    /**
     * Gera e baixa o PDF do relatório de pagamentos do colaborador.
     */
    public function collaboratorReport(int $id)
    {
        app()->setLocale('en'); // Garante que o PDF saia sempre em inglês

        $user = auth()->user()->company->users()->findOrFail($id);
        $startDate = request('start_date');
        $endDate = request('end_date');
        $addressType = request('address_type', 'all');
        $payoutStatus = request('payout_status', 'all');

        // Utiliza nossa Query centralizada do Brain
        $services = GetCollaboratorPayoutsQuery::run(
            companyId: $user->company_id,
            startDate: $startDate,
            endDate: $endDate,
            userId: (int) $id,
            payoutStatus: $payoutStatus,
            addressType: $addressType
        );

        $start = Carbon::parse($startDate)->startOfDay();

        $pdf = Pdf::loadView('pdf.collaborator-report', [
            'user' => $user,
            'services' => $services,
            'startDate' => $start->format('d/m/Y'),
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
            'company' => $user->company,
        ]);

        $fileName = 'payout-report-'.strtolower(str_replace(' ', '-', $user->name)).'-'.$start->format('d-m-Y').'.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Gera e baixa o PDF da fatura (invoice).
     */
    public function invoice(int $id)
    {
        app()->setLocale('en'); // Garante que o PDF saia sempre em inglês

        $invoice = Invoice::with(['customer', 'company', 'items'])->findOrFail($id);

        // Acesso seguro: a fatura deve pertencer à empresa do usuário logado
        if ($invoice->company_id !== auth()->user()->company->id) {
            abort(403);
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        $fileName = strtolower($invoice->number).($invoice->status === 'draft' ? '-draft' : '').'.pdf';

        return $pdf->download($fileName);
    }
}
