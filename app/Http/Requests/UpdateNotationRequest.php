<?php

namespace App\Http\Requests;

use App\Models\Notation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape only. UpdateNotation checks that a new assignee can see the page.
 */
class UpdateNotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('notation'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'required', 'string', 'max:'.Notation::BODY_MAX],
            'assignee_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ];
    }
}
