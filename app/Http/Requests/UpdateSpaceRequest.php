<?php

namespace App\Http\Requests;

use App\Enums\SpaceVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only. UpdateSpace refuses visibility changes on personal spaces.
 */
class UpdateSpaceRequest extends FormRequest
{
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'visibility' => ['sometimes', 'required', Rule::enum(SpaceVisibility::class)],
        ];
    }
}
