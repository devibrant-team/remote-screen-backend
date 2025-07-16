<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
                'name' => 'required|string|max:255',
            'screen_number' => 'required|integer|min:1',
            'storage' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'offer' => 'required|numeric|min:0',
            'plan_time' => 'required|integer|min:1',
            'is_recommended' => 'required|boolean',
            'access_num' => 'required|integer|min:0',
        ];
    }
}
