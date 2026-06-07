<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialDailySnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_revenue',
        'product_revenue',
        'subscription_revenue',
        'service_revenue',
        'daily_burn_rate',
        'cash_balance',
        'runway_days',
        'cac',
        'ltv',
        'avg_revenue_per_vendor',
        'avg_revenue_per_order',
        'active_vendors',
        'active_consumers',
        'total_orders',
        'gross_margin_percent',
        'revenue_by_city',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_revenue' => 'decimal:2',
            'product_revenue' => 'decimal:2',
            'subscription_revenue' => 'decimal:2',
            'service_revenue' => 'decimal:2',
            'daily_burn_rate' => 'decimal:2',
            'cash_balance' => 'decimal:2',
            'cac' => 'decimal:2',
            'ltv' => 'decimal:2',
            'avg_revenue_per_vendor' => 'decimal:2',
            'avg_revenue_per_order' => 'decimal:2',
            'gross_margin_percent' => 'decimal:2',
            'revenue_by_city' => 'array',
        ];
    }
}
