<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddSpaceMember;
use App\Actions\ChangeSpaceMemberRole;
use App\Actions\RemoveSpaceMember;
use App\Enums\SpaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\V1\SpaceMemberResource;
use App\Models\Space;
use App\Models\User;
use App\Queries\SpaceMembersQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Spaces', weight: 6)]
class SpaceMemberController extends Controller
{
    /**
     * List space members.
     */
    public function index(Space $space, SpaceMembersQuery $listMembers): AnonymousResourceCollection
    {
        Gate::authorize('update', $space);

        return SpaceMemberResource::collection($listMembers->handle($space)->get());
    }

    /**
     * Add a space member.
     */
    public function store(InviteMemberRequest $request, Space $space, AddSpaceMember $addMember): JsonResponse
    {
        $user = $addMember->handle(
            $space,
            $request->user(),
            $request->validated('email'),
            SpaceRole::tryFrom((string) $request->validated('role')) ?? SpaceRole::Editor,
        );

        return $this->member($space, $user)->response()->setStatusCode(201);
    }

    /**
     * Change a member's role.
     */
    public function update(UpdateMemberRoleRequest $request, Space $space, User $user, ChangeSpaceMemberRole $changeRole): SpaceMemberResource
    {
        $changeRole->handle($space, $request->user(), $user, SpaceRole::from($request->validated('role')));

        return $this->member($space, $user);
    }

    /**
     * Remove a space member.
     */
    public function destroy(Request $request, Space $space, User $user, RemoveSpaceMember $removeMember): Response
    {
        $removeMember->handle($space, $request->user(), $user);

        return response()->noContent();
    }

    private function member(Space $space, User $user): SpaceMemberResource
    {
        return new SpaceMemberResource($space->users()->whereKey($user->id)->firstOrFail());
    }
}
