<?php

declare(strict_types=1);

namespace App\Brain\Services\Actions;

use App\Models\ServiceType;
use Brain\Action;
use Illuminate\Validation\Rule;

/**
 * Action CreateOrUpdateServiceTypeAction
 *
 * @property-read int $companyId
 * @property-read int|null $editingId
 * @property-read string $name
 * @property int $resolvedTypeId
 */
class CreateOrUpdateServiceTypeAction extends Action
{
    public function rules(): array
    {
        return [
            'companyId' => 'required|exists:companies,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_types', 'name')
                    ->ignore($this->editingId)
                    ->where('company_id', $this->companyId),
            ],
        ];
    }

    public function handle(): self
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->editingId) {
            $type = ServiceType::where('company_id', $this->companyId)->findOrFail($this->editingId);
            $type->update($data);
        } else {
            $type = ServiceType::create([
                'company_id' => $this->companyId,
                ...$data,
            ]);
        }

        $this->resolvedTypeId = $type->id;

        return $this;
    }
}
