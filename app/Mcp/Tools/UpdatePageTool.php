<?php

namespace App\Mcp\Tools;

use App\Actions\UpdatePage;
use App\Enums\PageType;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_page')]
#[Description('Edit a page\'s title and markdown body. Boards are not pages: a board id is not found. Pass base_updated_at from fetch so edits made in the editor meanwhile are not overwritten.')]
class UpdatePageTool extends CorpusTool
{
    public function __construct(private UpdatePage $updatePage) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:'.Page::TITLE_MAX],
            'body' => ['nullable', 'string', 'max:'.Page::BODY_MAX],
            'mode' => ['nullable', 'in:replace,append'],
            'base_updated_at' => ['nullable', 'date'],
        ]);
        $page = Page::where('type', PageType::Page)->findOrFail($validated['page_id']);

        $changes = array_filter(array_intersect_key($validated, array_flip(['title', 'body'])), fn ($value) => $value !== null);

        if ($changes === []) {
            return Response::error('Provide a title or a body.');
        }

        $this->updatePage->handle(
            $page,
            $user,
            $changes,
            $validated['base_updated_at'] ?? null,
            ($validated['mode'] ?? 'replace') === 'append',
        );

        return Response::json($page->only(['id', 'title', 'updated_at']));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'body' => $schema->string()->description('Markdown. Replaces the body unless mode is append.'),
            'mode' => $schema->string()->enum(['replace', 'append'])->description('Default replace.'),
            'base_updated_at' => $schema->string()->description('updated_at from fetch, ISO 8601.'),
        ];
    }
}
