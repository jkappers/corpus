<?php

namespace App\Mcp\Tools;

use App\Enums\PageType;
use App\Http\Resources\V1\BoardResource;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('fetch')]
#[Description('Read one page (markdown body) or board (JSON nodes and edges) in full by id. The result\'s type says which. Keep a page\'s updated_at to pass as base_updated_at to update_page.')]
#[IsReadOnly]
class FetchTool extends CorpusTool
{
    protected function execute(Request $request, User $user): Response
    {
        $item = Page::with('space:id,name,visibility')->findOrFail($request->validate(['id' => ['required', 'integer']])['id']);

        Gate::authorize('view', $item);

        $resource = $item->type === PageType::Board ? new BoardResource($item) : new PageResource($item);

        return Response::json([
            'type' => $item->type,
            ...$resource->toProps(),
            'can_edit' => $item->space->isEditableBy($user),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('A page or board id from search.')->required(),
        ];
    }
}
