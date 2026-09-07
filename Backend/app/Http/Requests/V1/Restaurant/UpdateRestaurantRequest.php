<?php

namespace App\Http\Requests\V1\Restaurant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
    $restaurant = $this->route('restaurant');

    return $user?->role === 'restaurant_manager'
        && $restaurant instanceof \App\Models\Restaurant
        && $restaurant->manager_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'latitude' => [
    'nullable',
    'numeric',
    'between:-90,90',
],

'longitude' => [
    'nullable',
    'numeric',
    'between:-180,180',
],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(09|07)\d{8}$/',
            ],
            'logo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }
}