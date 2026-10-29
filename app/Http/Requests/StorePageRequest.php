<?php

namespace App\Http\Requests;

use App\Concerns\PageValidationRules;
use App\Enums\PageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
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
            ...$this->pageContentRules(PageType::Page),
        ];
    }
}
