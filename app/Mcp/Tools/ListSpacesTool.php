<?php

namespace App\Mcp\Tools;

use App\Http\Resources\V1\SpaceResource;
use App\Models\User;
use App\Queries\SpaceListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_spaces')]
#[Description('List spaces the user can see, with their role and whether they can edit.')]
#[IsReadOnly]
class ListSpacesTool extends CorpusTool
{
    public function __construct(private SpaceListQuery $listSpaces) {}

    protected function execute(Request $request, User $user): Response
    {
        $spaces = $this->listSpaces->handle($user)->get();

        return Response::json(SpaceResource::collection($spaces)->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
