<?php

declare(strict_types=1);

namespace App\Brain\Quotes\Actions;

use App\Brain\Helpers\TimeHelper;
use App\Models\Quote;
use App\Models\QuoteItem;
use Brain\Action;

/**
 * Action CreateQuoteItemsAction
 *
 * @property-read int $quoteId
 * @property-read array $items
 */
class CreateQuoteItemsAction extends Action
{
    public function rules(): array
    {
        return [
            'quoteId' => 'required|exists:quotes,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.notes' => 'nullable|string',
            'items.*.quantity' => 'required',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function handle(): self
    {
        $quote = Quote::findOrFail($this->quoteId);

        // Clear any existing items first (useful for updates/resaves)
        $quote->items()->delete();

        $total = 0;

        foreach ($this->items as $item) {
            $billingType = $item['billing_type'] ?? 'hourly';
            $qty = $billingType === 'hourly' ? TimeHelper::humanToDecimal($item['quantity']) : (float) $item['quantity'];
            $amount = $qty * (float) $item['unit_price'];
            $total += $amount;

            QuoteItem::create([
                'quote_id' => $quote->id,
                'service_type_id' => ! empty($item['service_type_id']) ? $item['service_type_id'] : null,
                'description' => $item['description'],
                'notes' => $item['notes'] ?? null,
                'quantity' => $qty,
                'unit_price' => $item['unit_price'],
                'amount' => $amount,
                'billing_type' => $billingType,
            ]);
        }

        $quote->update(['total_amount' => $total]);

        return $this;
    }
}
