<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'rider_lat',
        'rider_lng',
        'bearing',
        'status_message',
    ];

    protected function casts(): array
    {
        return [
            'rider_lat' => 'float',
            'rider_lng' => 'float',
            'bearing'   => 'float',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
