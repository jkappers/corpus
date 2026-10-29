<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\DeleteUser;
use App\Actions\InviteUserAsAdmin;
use App\Actions\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\V1\ManagedUserResource;
use App\Models\User;
use App\Queries\UserListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Admin', 'Administrators only.', weight: 9)]
class UserController extends Controller
{
    /**
     * List users.
     */
    public function index(Request $request, UserListQuery $listUsers): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $q = $request->validate(['q' => ['nullable', 'string', 'max:255']])['q'] ?? null;

        return ManagedUserResource::collection($listUsers->handle($q)->cursorPaginate(25)->withQueryString());
    }

    /**
     * Invite a user.
     */
    public function store(StoreUserRequest $request, InviteUserAsAdmin $inviteUser): JsonResponse
    {
        $user = $inviteUser->handle(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            $request->input('role') === 'admin',
        );

        return (new ManagedUserResource($user))->response()->setStatusCode(201);
    }

    /**
     * Get a user.
     */
    public function show(User $user): ManagedUserResource
    {
        Gate::authorize('update', $user);

        return new ManagedUserResource($user);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): ManagedUserResource
    {
        return new ManagedUserResource($updateUser->handle(
            $user,
            $request->user(),
            $request->safe()->only(['name', 'email']),
            $request->input('role'),
        ));
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user, DeleteUser $deleteUser): Response
    {
        $deleteUser->handle($user, $request->user());

        return response()->noContent();
    }
}
