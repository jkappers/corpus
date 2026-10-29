<?php

namespace App\Concerns;

use App\Enums\PageType;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PageValidationRules
{
    /**
     * Shape rules for a page's title and content. Pages carry `body` (markdown); boards carry `data`.
     * The other type's field is rejected.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function pageContentRules(PageType $type): array
    {
        $rules = [
            'title' => ['sometimes', 'nullable', 'string', 'max:'.Page::TITLE_MAX],
        ];

        if ($type === PageType::Page) {
            return $rules + [
                'body' => ['sometimes', 'nullable', 'string', 'max:'.Page::BODY_MAX],
                'data' => ['prohibited'],
            ];
        }

        return $rules + [
            'body' => ['prohibited'],
            'data' => ['sometimes', 'array'],
            // `present`, not `required`: an empty nodes/edges list is valid.
            'data.nodes' => ['exclude_without:data', 'present', 'array', 'max:2000'],
            'data.edges' => ['exclude_without:data', 'present', 'array', 'max:5000'],
            'data.viewport' => ['exclude_without:data', 'present', 'array'],
            'data.viewport.x' => ['exclude_without:data', 'required', 'numeric'],
            'data.viewport.y' => ['exclude_without:data', 'required', 'numeric'],
            'data.viewport.zoom' => ['exclude_without:data', 'required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    /**
     * A space the user can create pages and boards in.
     *
     * @return array<int, mixed>
     */
    protected function editableSpaceRules(User $user): array
    {
        return ['nullable', 'integer', Rule::exists('spaces', 'id')->where(
            fn ($query) => $query->whereIn('id', Space::query()->editableBy($user)->select('id'))
        )];
    }
}
