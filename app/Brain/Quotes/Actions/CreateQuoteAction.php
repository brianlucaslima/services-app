<?php

declare(strict_types=1);

namespace App\Brain\Quotes\Actions;

use App\Models\Company;
use App\Models\Quote;
use Brain\Action;

/**
 * Action CreateQuoteAction
 *
 * @property-read int $companyId
 * @property-read int|null $quoteId
 * @property-read int $customerId
 * @property-read string $quoteDate
 * @property-read string $expiryDate
 * @property-read string|null $notes
 * @property int $resolvedQuoteId
 */
class CreateQuoteAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'quoteId' => 'nullable|exists:quotes,id',
            'customerId' => 'required|exists:customers,id',
            'quoteDate' => 'required|date',
            'expiryDate' => 'required|date|after_or_equal:quoteDate',
        ];
    }

    public function handle(): self
    {
        $company = Company::findOrFail($this->companyId);

        if ($this->quoteId) {
            $quote = Quote::findOrFail($this->quoteId);
            $quote->update([
                'customer_id' => $this->customerId,
                'date' => $this->quoteDate,
                'expiry_date' => $this->expiryDate,
                'notes' => $this->notes,
            ]);
        } else {
            // Find the last quote number or generate a new one
            $lastQuote = Quote::where('company_id', $company->id)->latest('id')->first();
            $lastNum = $lastQuote ? (int) filter_var($lastQuote->number, FILTER_SANITIZE_NUMBER_INT) : 0;
            $nextNum = $lastNum + 1;

            // Safety loop to prevent duplicate quote numbers
            do {
                $number = 'Q'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
                $exists = Quote::where('company_id', $company->id)->where('number', $number)->exists();
                if ($exists) {
                    $nextNum++;
                }
            } while ($exists);

            $quote = Quote::create([
                'company_id' => $company->id,
                'customer_id' => $this->customerId,
                'number' => $number,
                'date' => $this->quoteDate,
                'expiry_date' => $this->expiryDate,
                'status' => 'draft',
                'total_amount' => 0,
                'notes' => $this->notes,
            ]);
        }

        $this->resolvedQuoteId = $quote->id;
        $this->quoteId = $quote->id;

        return $this;
    }
}
