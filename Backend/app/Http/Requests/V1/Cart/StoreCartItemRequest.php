<?php

namespace App\Http\Requests\V1\Cart;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'menu_item_id' => [
                'required',
                'integer',
                'exists:menu_items,id',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:99',
            ],
        ];
    }
}
