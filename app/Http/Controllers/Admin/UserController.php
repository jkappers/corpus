<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ManagedUserResource;
use App\Models\User;
use App\Queries\UserListQuery;
use App\Support\PaginationProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Renders the user admin screens. Writes go through the REST API (Api\V1\Admin\UserController).
 */
class UserController extends Controller
{
    public function index(Request $request, UserListQuery $listUsers): InertiaResponse
    {
        Gate::authorize('viewAny', User::class);

        $q = trim((string) $request->query('q', ''));

        $paginator = $listUsers->handle($q)->paginate(25)->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => ManagedUserResource::collection($paginator->getCollection())->toProps(),
            'pagination' => PaginationProps::from($paginator),
            'filters' => ['q' => $q],
        ]);
    }

    public function create(): InertiaResponse
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/form', ['managed' => null]);
    }

    public function edit(User $user): InertiaResponse
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/form', [
            'managed' => (new ManagedUserResource($user))->toProps(),
        ]);
    }
}
