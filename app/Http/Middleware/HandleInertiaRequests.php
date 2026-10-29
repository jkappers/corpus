<?php

namespace App\Http\Middleware;

use App\Http\Resources\V1\UserResource;
use App\Models\Space;
use App\Queries\InboxCountQuery;
use App\Support\AppMeta;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? (new UserResource($request->user()))->resolve($request) : null,
            ],
            ...AppMeta::all(),
            'spaces' => fn () => $request->user()
                ? Space::query()
                    ->visibleTo($request->user())
                    ->with('owner:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'visibility', 'owner_id'])
                    ->map(fn (Space $space) => [
                        ...$space->only(['id', 'name', 'visibility']),
                        'is_personal' => $space->isPersonal(),
                        'is_mine' => $space->owner_id === $request->user()->id,
                        'owner_name' => $space->isPersonal() && $request->user()->is_admin ? $space->owner?->name : null,
                        ...$space->abilitiesFor($request->user()),
                    ])
                    ->sortByDesc('is_mine')
                    ->values()
                : [],
            'inboxCount' => fn () => $request->user()
                ? app(InboxCountQuery::class)->handle($request->user())
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
