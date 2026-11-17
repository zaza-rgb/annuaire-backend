<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'category_id' => 'required|exists:categories,id',
            'country_id'  => 'required|exists:countries,id',
            'city'        => 'required|string|max:100',
            'address'     => 'required|string|max:255',
            'latitude'    => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'   => 'nullable|numeric|between:-180,180|required_with:latitude',
            'phone'       => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'pricing'     => 'nullable|string',
        ];
    }
}