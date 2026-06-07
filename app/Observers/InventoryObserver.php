<?php

namespace App\Observers;

use App\Events\OperationsDashboardUpdated;
use App\Models\Inventory;

class InventoryObserver
{
    public function created(Inventory $inventory): void
    {
        event(new OperationsDashboardUpdated('inventory', $inventory->id, 'created'));
    }

    public function updated(Inventory $inventory): void
    {
        if (! $inventory->isDirty(['current_stock', 'reserved_stock', 'minimum_stock_level'])) {
            return;
        }

        event(new OperationsDashboardUpdated('inventory', $inventory->id, 'updated'));
    }

    public function deleted(Inventory $inventory): void
    {
        event(new OperationsDashboardUpdated('inventory', $inventory->id, 'deleted'));
    }
}
