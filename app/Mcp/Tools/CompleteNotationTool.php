<?php

namespace App\Mcp\Tools;

use App\Actions\SetNotationCompleted;
use App\Http\Resources\V1\NotationResource;
use App\Models\Notation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('complete_notation')]
#[Description('Mark a notation completed, or reopen it with completed=false. Only its author or assignee can.')]
#[IsIdempotent]
class CompleteNotationTool extends CorpusTool
{
    public function __construct(private SetNotationCompleted $setCompleted) {}

    protected function execute(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'notation_id' => ['required', 'integer'],
            'completed' => ['nullable', 'boolean'],
        ]);
        $notation = Notation::findOrFail($validated['notation_id']);

        $this->setCompleted->handle($notation, $user, $validated['completed'] ?? true);

        return Response::json((new NotationResource($notation))->toProps());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'notation_id' => $schema->integer()->required(),
            'completed' => $schema->boolean()->description('Default true. False reopens.'),
        ];
    }
}
