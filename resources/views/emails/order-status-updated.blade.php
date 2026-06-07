<x-mail::message>
# Order Update, {{ $name }}

Your order **#{{ $orderId }}** has been updated.

<x-mail::panel>
**New Status:** {{ ucfirst(str_replace('_', ' ', $status)) }}
</x-mail::panel>

If you have any questions about your order, please contact our support team.

Thanks,<br>
**The Ourth Team**
</x-mail::message>
