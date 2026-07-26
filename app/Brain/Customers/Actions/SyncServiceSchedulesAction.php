<?php

declare(strict_types=1);

namespace App\Brain\Customers\Actions;

use App\Models\ServiceAddress;
use Brain\Action;

/**
 * Action SyncServiceSchedulesAction
 *
 * @property-read int $resolvedAddressId
 * @property-read array $schedules
 */
class SyncServiceSchedulesAction extends Action
{
    public function rules(): array
    {
        return [
            'resolvedAddressId' => 'required|exists:service_addresses,id',
            'schedules' => 'required|array',
            'schedules.*.recurrence_type' => 'required|in:once,weekly,fortnightly,monthly',
            'schedules.*.start_date' => 'required|date',
            'schedules.*.start_time' => 'required',
        ];
    }

    public function handle(): self
    {
        $address = ServiceAddress::findOrFail($this->resolvedAddressId);
        $scheduleIds = [];

        foreach ($this->schedules as $scheduleData) {
            $schedule = $address->schedules()->updateOrCreate(
                ['id' => $scheduleData['id'] ?? null],
                [
                    'service_type_id' => $scheduleData['service_type_id'],
                    'description' => $scheduleData['description'] ?? null,
                    'recurrence_type' => $scheduleData['recurrence_type'],
                    'days_of_week' => $scheduleData['days_of_week'] ?? null,
                    'day_of_month' => $scheduleData['day_of_month'] ?? null,
                    'start_date' => $scheduleData['start_date'],
                    'start_time' => $scheduleData['start_time'],
                ]
            );

            $schedule->users()->sync($scheduleData['user_ids'] ?? []);
            $scheduleIds[] = $schedule->id;
        }

        // Clean up and delete any schedules that are no longer present in the payload
        $address->schedules()->whereNotIn('id', $scheduleIds)->delete();

        return $this;
    }
}
