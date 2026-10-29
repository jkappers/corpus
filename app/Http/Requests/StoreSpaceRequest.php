<?php

namespace App\Http\Requests;

use App\Enums\SpaceVisibility;
use App\Models\Space;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Space::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::enum(SpaceVisibility::class)],
        ];
    }
}
