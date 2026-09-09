<?php

namespace App\Http\Requests\V1\Restaurant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'restaurant_manager';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       return [
    'name' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:2000'],
    'address' => ['required', 'string', 'max:500'],
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
        'required',
        'string',
        'regex:/^(09|07)\d{8}$/',
    ],
    'logo' => [
        'nullable',
        'image',
        'mimes:jpg,jpeg,png,webp',
        'max:2048',
    ],
];
    }
}