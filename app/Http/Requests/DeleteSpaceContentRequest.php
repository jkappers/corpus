<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteSpaceContentRequest extends FormRequest
{
    public const CONFIRMATION = 'I accept the consequences';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('space'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', Rule::in([self::CONFIRMATION])],
        ];
    }
}
