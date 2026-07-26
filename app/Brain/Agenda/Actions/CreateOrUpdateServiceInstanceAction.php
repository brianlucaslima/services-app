<?php

declare(strict_types=1);

namespace App\Brain\Agenda\Actions;

use App\Models\ServiceInstance;
use App\Models\ServiceSchedule;
use Brain\Action;

/**
 * Action CreateOrUpdateServiceInstanceAction
 *
 * @property-read int $scheduleId
 * @property-read string $originalDate
 * @property-read int $companyId
 * @property-read string|null $date
 * @property-read string|null $time
 * @property-read string $status
 * @property-read string|null $notes
 * @property int $instanceId
 */
class CreateOrUpdateServiceInstanceAction extends Action
{
    public function rules(): array
    {
        return [
            'scheduleId' => 'required|exists:service_schedules,id',
            'originalDate' => 'required|date',
            'companyId' => 'required|exists:companies,id',
            'status' => 'required|in:completed,skipped,scheduled',
        ];
    }

    public function handle(): self
    {
        $schedule = ServiceSchedule::with(['address', 'type', 'users'])->findOrFail($this->scheduleId);

        // Find existing instance for this original date of that schedule
        $instance = ServiceInstance::where('company_id', $this->companyId)
            ->where('service_schedule_id', $this->scheduleId)
            ->where('original_date', $this->originalDate)
            ->first();

        $description = $schedule->description;
        if (empty($description) && $schedule->type) {
            $description = $schedule->type->name;
        }
        if (empty($description)) {
            $description = __('Service at').' '.$schedule->address->label;
        }

        $completedInstance = ServiceInstance::updateOrCreate(
            ['service_schedule_id' => $this->scheduleId, 'original_date' => $this->originalDate],
            [
                'company_id' => $this->companyId,
                'customer_id' => $schedule->address->customer_id,
                'service_address_id' => $schedule->service_address_id,
                'service_type_id' => $schedule->service_type_id,
                'description' => $description,
                'date' => $this->date ?? ($instance->date ?? $this->originalDate),
                'time' => $this->time ?? ($instance->time ?? $schedule->start_time),
                'duration_hours' => $schedule->address->duration_hours,
                'hourly_rate' => $schedule->address->hourly_rate,
                'status' => $this->status,
                'notes' => $this->notes,
            ]
        );

        $completedInstance->users()->sync($schedule->users()->pluck('users.id')->toArray());

        $this->instanceId = $completedInstance->id;

        return $this;
    }
}
