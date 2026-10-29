<?php

namespace App\Http\Requests;

use App\Concerns\PageValidationRules;
use App\Enums\PageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The API names board content `board`; it maps to the shared `data` rules, so errors are reported under `data.*`.
 */
class StoreBoardRequest extends FormRequest
{
    use PageValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'space_id' => $this->editableSpaceRules($this->user()),
            ...$this->pageContentRules(PageType::Board),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('board')) {
            $this->merge(['data' => $this->input('board')]);
        }
    }
}
