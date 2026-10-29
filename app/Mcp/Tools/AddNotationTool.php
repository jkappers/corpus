<?php

namespace App\Mcp\Tools;

use App\Actions\CreateNotation;
use App\Enums\PageType;
use App\Http\Resources\V1\NotationResource;
use App\Models\Notation;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_notation')]
#[Description('Attach a note or task to a quoted range of a page body. from/to are character offsets of the quote in the markdown body. Assigned to the caller unless assignee_id is given.')]
class AddNotationTool extends CorpusTool
{
    public function __construct(private CreateNotation $createNotation) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
            'quote' => ['required', 'string', 'max:'.Notation::QUOTE_MAX],
            'from' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'integer', 'gte:from'],
            'body' => ['required', 'string', 'max:'.Notation::BODY_MAX],
            'assignee_id' => ['nullable', 'integer'],
        ]);
        $page = Page::where('type', PageType::Page)->findOrFail($validated['page_id']);

        unset($validated['page_id']);
        $notation = $this->createNotation->handle($page, $user, $validated);

        return Response::json((new NotationResource($notation->load(['author:id,name', 'assignee:id,name'])))->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->required(),
            'quote' => $schema->string()->description('The quoted text the note is anchored to.')->required(),
            'from' => $schema->integer()->description('Start offset of the quote.')->required(),
            'to' => $schema->integer()->description('End offset of the quote.')->required(),
            'body' => $schema->string()->description('The note text.')->required(),
            'assignee_id' => $schema->integer()->description('User to assign. Must be able to see the page.'),
        ];
    }
}
