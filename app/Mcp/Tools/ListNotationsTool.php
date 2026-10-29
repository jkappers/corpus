<?php

namespace App\Mcp\Tools;

use App\Enums\PageType;
use App\Http\Resources\V1\NotationResource;
use App\Models\Page;
use App\Models\User;
use App\Queries\NotationListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_notations')]
#[Description('List notations (inline notes and tasks). With page_id: every notation on that page. Without: the user\'s inbox, open by default.')]
#[IsReadOnly]
class ListNotationsTool extends CorpusTool
{
    public function __construct(private NotationListQuery $listNotations) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'page_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:open,completed'],
        ]);

        if (isset($validated['page_id'])) {
            $page = Page::where('type', PageType::Page)->findOrFail($validated['page_id']);

            Gate::authorize('view', $page);

            $notations = $this->listNotations->forPage($page)->get();
        } else {
            $notations = $this->listNotations
                ->inbox($user, ($validated['status'] ?? 'open') === 'completed')
                ->limit(50)
                ->get();
        }

        return Response::json(NotationResource::collection($notations)->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('List notations on this page instead of the inbox.'),
            'status' => $schema->string()->enum(['open', 'completed'])->description('Inbox filter. Default open.'),
        ];
    }
}
