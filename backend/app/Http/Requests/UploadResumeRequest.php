<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadResumeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'resume' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240', // 10MB max
                function ($attribute, $value, $fail) {
                    // Additional MIME type verification
                    $allowedMimes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'image/jpeg',
                        'image/png',
                    ];
                    
                    if ($value && !in_array($value->getMimeType(), $allowedMimes)) {
                        $fail('The file type is not allowed. Only PDF, DOC, DOCX, JPG, and PNG files are accepted.');
                    }

                    // Check file size again as extra protection
                    if ($value && $value->getSize() > 10485760) { // 10MB in bytes
                        $fail('The file size exceeds the maximum allowed size of 10MB.');
                    }

                    // Verify file extension matches MIME type
                    $extension = strtolower($value->getClientOriginalExtension());
                    $mimeType = $value->getMimeType();
                    
                    $validCombinations = [
                        'pdf' => 'application/pdf',
                        'doc' => 'application/msword',
                        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                    ];

                    if (isset($validCombinations[$extension]) && $validCombinations[$extension] !== $mimeType) {
                        $fail('The file extension does not match the file content.');
                    }

                    // Check for suspicious content in filename
                    $filename = $value->getClientOriginalName();
                    if (preg_match('/[<>:"|?*\x00-\x1F]/', $filename)) {
                        $fail('The filename contains invalid characters.');
                    }

                    // Prevent double extensions (e.g., file.pdf.exe)
                    $parts = explode('.', $filename);
                    if (count($parts) > 2) {
                        $fail('Multiple file extensions are not allowed.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'resume.required' => 'Please select a file to upload',
            'resume.file' => 'The uploaded file is invalid',
            'resume.mimes' => 'Only PDF, DOC, DOCX, JPG, and PNG files are allowed',
            'resume.max' => 'The file size must not exceed 10MB',
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
                'message' => 'File validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Additional security: Check if file was actually uploaded via HTTP POST
        if ($this->hasFile('resume')) {
            $file = $this->file('resume');
            if (!$file->isValid()) {
                throw new HttpResponseException(
                    response()->json([
                        'success' => false,
                        'message' => 'File upload failed. Please try again.',
                    ], 400)
                );
            }
        }
    }
}
