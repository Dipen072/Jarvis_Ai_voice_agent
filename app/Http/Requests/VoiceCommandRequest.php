<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoiceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by route middleware
    }

    public function rules(): array
    {
        return [
            'command' => [
                'required',
                'string',
                'min:1',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'command.required' => 'A command is required.',
            'command.max'      => 'Command must not exceed 500 characters.',
        ];
    }

    /**
     * Prepare the command for validation — strip dangerous characters.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('command')) {
            $this->merge([
                'command' => strip_tags($this->input('command')),
            ]);
        }
    }
}
