<?php

namespace App\Mcp\Tools;

use App\Actions\TrashPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('trash')]
#[Description('Move a page or board to the trash by id. It can be restored for '.Page::TRASH_RETENTION_DAYS.' days from the app.')]
#[IsDestructive]
#[IsIdempotent]
class TrashTool extends CorpusTool
{
    public function __construct(private TrashPage $trashPage) {}

    protected function execute(Request $request, User $user): Response
    {
        $item = Page::findOrFail($request->validate(['id' => ['required', 'integer']])['id']);

        $this->trashPage->handle($item, $user);

        return Response::text("Moved {$item->type->value} \"{$item->title}\" to the trash.");
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
