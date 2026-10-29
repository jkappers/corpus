<?php

namespace App\Mcp\Tools;

use App\Http\Resources\V1\PageVersionResource;
use App\Models\Page;
use App\Models\User;
use App\Queries\PageVersionListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_versions')]
#[Description('List saved versions of a page or board by id, newest first (up to 50), without content.')]
#[IsReadOnly]
class ListVersionsTool extends CorpusTool
{
    public function __construct(private PageVersionListQuery $listVersions) {}

    protected function execute(Request $request, User $user): Response
    {
        $item = Page::findOrFail($request->validate(['id' => ['required', 'integer']])['id']);

        Gate::authorize('view', $item);

        $versions = $this->listVersions->handle($item)->limit(50)->get();

        return Response::json(PageVersionResource::collection($versions)->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('A page or board id.')->required(),
        ];
    }
}
