<?php

namespace App\Mcp\Tools;

use App\Enums\PageType;
use App\Http\Resources\V1\SearchResultResource;
use App\Models\User;
use App\Queries\PageListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search')]
#[Description('Search pages and boards the user can see by title, newest first. Each result has a type (page or board) and an id; use fetch for full content.')]
#[IsReadOnly]
class SearchTool extends CorpusTool
{
    public function __construct(private PageListQuery $listPages) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(PageType::class)],
            'space_id' => ['nullable', 'integer'],
            'trashed' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $results = $this->listPages->handle($user, [
            'type' => $validated['type'] ?? null,
            'space_id' => $validated['space_id'] ?? null,
            'q' => $validated['query'] ?? null,
            'trashed' => (bool) ($validated['trashed'] ?? false),
        ])
            ->limit($validated['limit'] ?? 20)
            ->get();

        return Response::json(SearchResultResource::collection($results)->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match in titles.'),
            'type' => $schema->string()->enum(['page', 'board'])->description('Only pages or only boards.'),
            'space_id' => $schema->integer()->description('Only items in this space.'),
            'trashed' => $schema->boolean()->description('List trashed items instead.'),
            'limit' => $schema->integer()->description('Maximum results, 1-50. Default 20.'),
        ];
    }
}
