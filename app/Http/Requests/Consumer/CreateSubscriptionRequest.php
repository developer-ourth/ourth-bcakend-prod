<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
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
            'vendor_id'        => ['required', 'integer', 'exists:vendors,id'],
            'plan_name'        => ['required', 'string', 'max:100'],
            'frequency'        => ['required', 'string', 'in:daily,weekly,biweekly,monthly'],
            'plan_price'       => ['required', 'numeric', 'min:0'],
            'start_date'       => ['required', 'date', 'after_or_equal:today'],
            'delivery_address' => ['required', 'array'],
            'delivery_address.line1'       => ['required', 'string', 'max:255'],
            'delivery_address.city'        => ['required', 'string', 'max:100'],
            'delivery_address.state'       => ['required', 'string', 'max:100'],
            'delivery_address.postal_code' => ['required', 'string', 'max:10'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }
}
