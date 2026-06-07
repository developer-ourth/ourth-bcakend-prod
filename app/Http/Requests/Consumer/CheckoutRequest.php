<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_address_line1' => ['required', 'string', 'max:255'],
            'delivery_address_line2' => ['nullable', 'string', 'max:255'],
            'delivery_city'         => ['required', 'string', 'max:100'],
            'delivery_state'        => ['required', 'string', 'max:100'],
            'delivery_postal_code'  => ['required', 'string', 'max:10'],
            'delivery_country'      => ['nullable', 'string', 'max:100'],
            'delivery_phone'        => ['required', 'string', 'max:15'],
            'customer_notes'        => ['nullable', 'string', 'max:500'],
            'payment_method'        => ['required', 'string', 'in:cod,online,wallet,upi,card,netbanking'],
            'order_type'            => ['nullable', 'string', 'in:b2c,b2b'],
            'buyer_gstin'           => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
        ];
    }
}
