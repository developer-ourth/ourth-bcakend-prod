<x-mail::message>
# Your order is confirmed, {{ $name }}!

Thank you for shopping with Ourth. We've received your order and it's being processed.

<x-mail::panel>
**Order Number:** #{{ $orderNumber }}
**Order Type:** {{ strtoupper($orderType) }}
**Items:** {{ $itemCount }}
**Total Amount:** ₹{{ $total }}
</x-mail::panel>

We'll send you another email when your order is dispatched.

Thanks,<br>
**The Ourth Team**
</x-mail::message>
