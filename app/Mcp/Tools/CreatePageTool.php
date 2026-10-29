<?php

namespace App\Mcp\Tools;

use App\Actions\CreatePage;
use App\Enums\PageType;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_page')]
#[Description('Create a markdown page. Defaults to the user\'s personal space. Boards cannot be created here.')]
class CreatePageTool extends CorpusTool
{
    public function __construct(private CreatePage $createPage) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:'.Page::TITLE_MAX],
            'body' => ['nullable', 'string', 'max:'.Page::BODY_MAX],
            'space_id' => ['nullable', 'integer'],
        ]);

        $page = $this->createPage->handle(
            $user,
            PageType::Page,
            isset($validated['space_id']) ? Space::findOrFail($validated['space_id']) : null,
            array_intersect_key($validated, array_flip(['title', 'body'])),
        );

        return Response::json((new PageResource($page->refresh()))->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Defaults to "Untitled".'),
            'body' => $schema->string()->description('Initial markdown body.'),
            'space_id' => $schema->integer()->description('Space to create in. Needs edit access.'),
        ];
    }
}
