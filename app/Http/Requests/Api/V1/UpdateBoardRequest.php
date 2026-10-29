<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\PageValidationRules;
use App\Enums\PageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The API names board content `board`; it maps to the shared `data` rules, so errors are reported under `data.*`.
 */
class UpdateBoardRequest extends FormRequest
{
    use PageValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->pageContentRules(PageType::Board),
            'base_updated_at' => ['sometimes', 'date'],
            'space_id' => ['sometimes', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('board')) {
            $this->merge(['data' => $this->input('board')]);
        }
    }
}
