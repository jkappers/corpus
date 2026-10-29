<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\PageValidationRules;
use App\Enums\PageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePageRequest extends FormRequest
{
    use PageValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('page'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->pageContentRules(PageType::Page),
            'base_updated_at' => ['sometimes', 'date'],
            'space_id' => ['sometimes', 'integer'],
            'mode' => ['sometimes', 'in:replace,append'],
        ];
    }
}
