<?php

namespace App\Observers;

use App\Events\OperationsDashboardUpdated;
use App\Models\DeliveryRoute;

class DeliveryRouteObserver
{
    public function created(DeliveryRoute $deliveryRoute): void
    {
        event(new OperationsDashboardUpdated('delivery_route', $deliveryRoute->id, 'created'));
    }

    public function updated(DeliveryRoute $deliveryRoute): void
    {
        if (! $deliveryRoute->isDirty(['status', 'completed_stops', 'total_stops'])) {
            return;
        }

        event(new OperationsDashboardUpdated('delivery_route', $deliveryRoute->id, 'updated'));
    }

    public function deleted(DeliveryRoute $deliveryRoute): void
    {
        event(new OperationsDashboardUpdated('delivery_route', $deliveryRoute->id, 'deleted'));
    }
}
