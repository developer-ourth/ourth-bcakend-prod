<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private order tracking channel — only the order's owner may subscribe
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    return Order::where('id', $orderId)->where('user_id', $user->id)->exists();
});
