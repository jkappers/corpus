<?php

namespace App\Http\Controllers;

use App\Http\Resources\V1\NotationResource;
use App\Queries\NotationListQuery;
use App\Support\PaginationProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InboxController extends Controller
{
    public function index(Request $request, NotationListQuery $listNotations): InertiaResponse
    {
        $completed = $request->boolean('completed');

        $paginator = $listNotations->inbox($request->user(), $completed, false, null, ! $completed)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('inbox/index', [
            'notations' => NotationResource::collection($paginator->items())->toProps(),
            'completed' => $completed,
            'counts' => $listNotations->inboxCounts($request->user(), false),
            'pagination' => PaginationProps::from($paginator),
        ]);
    }
}
