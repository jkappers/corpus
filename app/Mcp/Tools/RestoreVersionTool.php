<?php

namespace App\Mcp\Tools;

use App\Actions\RestorePageVersion;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('restore_version')]
#[Description('Overwrite a page or board with one of its saved versions. The current state is saved first as a "pre-restore" version.')]
#[IsDestructive(false)]
class RestoreVersionTool extends CorpusTool
{
    public function __construct(private RestorePageVersion $restoreVersion) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'version_id' => ['required', 'integer'],
        ]);
        $item = Page::findOrFail($validated['id']);
        $version = $item->versions()->findOrFail($validated['version_id']);

        $this->restoreVersion->handle($item, $version, $user);

        return Response::json($item->only(['id', 'type', 'title', 'updated_at']));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('A page or board id.')->required(),
            'version_id' => $schema->integer()->required(),
        ];
    }
}
