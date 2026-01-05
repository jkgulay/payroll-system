<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'accountant']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'middle_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1940-01-01'],
            'gender' => ['nullable', 'in:male,female,other'],
            'email' => ['nullable', 'email', 'max:255', 'unique:employees,email'],
            'mobile_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\+\-\(\)\s]+$/'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'worker_address' => ['nullable', 'string', 'max:500'],
            'position' => ['required', 'string', 'max:100'],
            'employment_status' => ['nullable', 'in:regular,probationary,contractual'],
            'employment_type' => ['nullable', 'in:regular,contractual,part_time'],
            'date_hired' => ['nullable', 'date', 'before_or_equal:today'],
            'basic_salary' => ['nullable', 'numeric', 'min:450', 'max:999999.99'],
            'salary_type' => ['nullable', 'in:daily,monthly,hourly'],

            // Government IDs
            'sss_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'philhealth_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'pagibig_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'tin_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, and periods',
            'last_name.required' => 'Last name is required',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, and periods',
            'email.unique' => 'This email is already registered',
            'email.email' => 'Please provide a valid email address',
            'mobile_number.regex' => 'Mobile number format is invalid',
            'date_of_birth.before' => 'Date of birth must be before today',
            'date_of_birth.after' => 'Date of birth must be after 1940',
            'date_hired.before_or_equal' => 'Date hired cannot be in the future',
            'position.required' => 'Position is required',
            'project_id.exists' => 'Selected project does not exist',
            'basic_salary.min' => 'Salary cannot be negative',
            'basic_salary.max' => 'Salary value is too large',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Unauthorized to create employees',
            ], 403)
        );
    }
}
