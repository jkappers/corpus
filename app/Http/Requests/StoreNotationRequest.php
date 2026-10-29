<?php

namespace App\Http\Requests;

use App\Models\Notation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape only. CreateNotation checks that the assignee can see the page.
 */
class StoreNotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Notation::class, $this->route('page')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:'.Notation::QUOTE_MAX],
            'from' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'integer', 'gte:from'],
            'body' => ['required', 'string', 'max:'.Notation::BODY_MAX],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
