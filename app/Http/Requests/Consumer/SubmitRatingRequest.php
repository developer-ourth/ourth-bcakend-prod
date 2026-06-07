<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitRatingRequest extends FormRequest
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
            'ratable_type'   => ['required', 'string', 'in:vendor,product'],
            'ratable_id'     => ['required', 'integer'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'review'         => ['nullable', 'string', 'max:1000'],
            'review_photos'  => ['nullable', 'array', 'max:5'],
            'review_photos.*' => ['string', 'url'],
        ];
    }
}
