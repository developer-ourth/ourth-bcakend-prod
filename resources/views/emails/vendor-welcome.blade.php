<x-mail::message>
# Welcome to Ourth, {{ $ownerName }}! 🎉

Thank you for registering **{{ $businessName }}** on the Ourth platform. Your vendor application has been received and is currently under review by our team.

## Your Vendor ID

<x-mail::panel>
# {{ $vendorCode }}
</x-mail::panel>

**Keep this ID safe.** You will need it every time you log in to the Ourth Vendor App.

### What happens next?

1. Our team will review your KYC documents (usually within 1–2 business days).
2. You will receive a follow-up email once your account is approved or if we need additional information.
3. Once approved, use your **Vendor ID `{{ $vendorCode }}`** and your password to log in.

If you have any questions, reply to this email or contact our support team.

Thanks,<br>
**The Ourth Team**
</x-mail::message>
