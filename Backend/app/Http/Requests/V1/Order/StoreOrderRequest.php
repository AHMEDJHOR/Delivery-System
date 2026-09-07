<?php

namespace App\Http\Requests\V1\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to create an order.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_address' => [
                'required',
                'string',
            ],
            'delivery_latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'delivery_longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
             'phone' => [
        'required',
        'string',
        'regex:/^(09|07)\d{8}$/',
    ],
        ];
    }
}