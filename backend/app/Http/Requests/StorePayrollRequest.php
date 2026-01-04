<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'accountant']);
    }

    public function rules(): array
    {
        return [
            'period_start_date' => ['required', 'date', 'before_or_equal:period_end_date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'payment_date' => ['required', 'date', 'after_or_equal:period_end_date'],
            'pay_period_number' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_start_date.before_or_equal' => 'Period start date must be before or equal to end date',
            'period_end_date.after_or_equal' => 'Period end date must be after or equal to start date',
            'payment_date.after_or_equal' => 'Payment date must be after or equal to period end date',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
