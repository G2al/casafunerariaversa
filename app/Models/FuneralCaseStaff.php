<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class FuneralCaseStaff extends Pivot
{
    protected static function booted(): void
    {
        foreach (['created' => 'staff_assigned', 'deleted' => 'staff_unassigned'] as $event => $label) {
            static::$event(fn (self $assignment) => AuditEvent::create([
                'user_id' => auth()->id(), 'funeral_case_id' => $assignment->funeral_case_id,
                'event' => $label, 'changed_fields' => ['transport_staff'],
            ]));
        }
    }
}
