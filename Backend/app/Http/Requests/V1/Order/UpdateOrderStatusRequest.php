<?php

namespace App\Http\Requests\V1\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to update order status.
     */
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['restaurant_manager', 'driver', 'admin'],
            true
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'pending',
                    'preparing',
                    'ready_for_pickup',
                    'in_transit',
                    'delivered',
                    'cancelled',
                    'rejected',
                ]),
            ],
        ];
    }
}