<?php

namespace App\Observers;

use App\Events\OperationsDashboardUpdated;
use App\Models\Delivery;

class DeliveryObserver
{
    public function created(Delivery $delivery): void
    {
        event(new OperationsDashboardUpdated('delivery', $delivery->id, 'created'));
    }

    public function updated(Delivery $delivery): void
    {
        if (! $delivery->isDirty('delivery_status')) {
            return;
        }

        event(new OperationsDashboardUpdated('delivery', $delivery->id, 'updated'));
    }

    public function deleted(Delivery $delivery): void
    {
        event(new OperationsDashboardUpdated('delivery', $delivery->id, 'deleted'));
    }
}
