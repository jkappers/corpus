<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AppMeta;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Reference', 'Labels and limits the app uses, such as enum values.', weight: 8)]
class MetaController extends Controller
{
    /**
     * Get reference data.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => AppMeta::all()]);
    }
}
