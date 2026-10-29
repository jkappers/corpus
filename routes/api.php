<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserPasswordResetController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\BoardVersionController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotationController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PageVersionController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SpaceController;
use App\Http\Controllers\Api\V1\SpaceMemberController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

// Unverified users can still correct their email address.
Route::middleware(['auth', 'throttle:api', 'api.session'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
});

// Session users (the web app) are unscoped; partner access tokens need the scope named on each group.
Route::middleware(['auth', 'verified', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('api.scope:profile:read')->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::get('meta', MetaController::class)->name('meta');
    });

    Route::put('me/password', [PasswordController::class, 'update'])
        ->middleware(['api.session', 'throttle:6,1'])
        ->name('me.password.update');

    Route::middleware('api.session')->prefix('me/tokens')->name('tokens.')->group(function () {
        Route::get('/', [TokenController::class, 'index'])->name('index');
        Route::post('/', [TokenController::class, 'store'])->middleware('password.confirm')->name('store');
        Route::delete('{token}', [TokenController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('api.scope:pages:read')->group(function () {
        Route::get('search', SearchController::class)->name('search');
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');
        Route::get('pages/{page}/versions', [PageVersionController::class, 'index'])->name('pages.versions.index');
        Route::get('pages/{page}/versions/{version}', [PageVersionController::class, 'show'])->name('pages.versions.show');

        Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
        Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
        Route::get('boards/{board}/versions', [BoardVersionController::class, 'index'])->name('boards.versions.index');
        Route::get('boards/{board}/versions/{version}', [BoardVersionController::class, 'show'])->name('boards.versions.show');
    });
    Route::middleware('api.scope:pages:write')->group(function () {
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::match(['put', 'patch'], 'pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{id}/restore', [PageController::class, 'restore'])->whereNumber('id')->name('pages.restore');
        Route::delete('pages/{id}/force', [PageController::class, 'forceDestroy'])->whereNumber('id')->name('pages.force-destroy');
        Route::post('pages/{page}/versions/{version}/restore', [PageVersionController::class, 'restore'])->name('pages.versions.restore');

        Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
        Route::match(['put', 'patch'], 'boards/{board}', [BoardController::class, 'update'])->name('boards.update');
        Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
        Route::post('boards/{id}/restore', [BoardController::class, 'restore'])->whereNumber('id')->name('boards.restore');
        Route::delete('boards/{id}/force', [BoardController::class, 'forceDestroy'])->whereNumber('id')->name('boards.force-destroy');
        Route::post('boards/{board}/versions/{version}/restore', [BoardVersionController::class, 'restore'])->name('boards.versions.restore');
    });

    Route::middleware('api.scope:notations:read')->group(function () {
        Route::get('notations', [NotationController::class, 'index'])->name('notations.index');
        Route::get('notations/count', [NotationController::class, 'count'])->name('notations.count');
        Route::get('pages/{page}/notations', [NotationController::class, 'forPage'])->name('pages.notations.index');
        Route::get('pages/{page}/assignable-users', [NotationController::class, 'assignableUsers'])->name('pages.assignable-users');
    });
    Route::middleware('api.scope:notations:write')->group(function () {
        Route::post('pages/{page}/notations', [NotationController::class, 'store'])->name('pages.notations.store');
        Route::patch('notations/{notation}', [NotationController::class, 'update'])->name('notations.update');
        Route::delete('notations/{notation}', [NotationController::class, 'destroy'])->name('notations.destroy');
        Route::post('notations/{notation}/replies', [NotationController::class, 'reply'])->name('notations.replies.store');
        Route::delete('notation-replies/{reply}', [NotationController::class, 'destroyReply'])->name('notation-replies.destroy');
        Route::put('notations/{notation}/completion', [NotationController::class, 'complete'])->name('notations.complete');
        Route::delete('notations/{notation}/completion', [NotationController::class, 'reopen'])->name('notations.reopen');
    });

    Route::middleware('api.scope:attachments:read')->group(function () {
        Route::get('attachments', [AttachmentController::class, 'index'])->name('attachments.index');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('attachments/{attachment}/content', [AttachmentController::class, 'content'])->name('attachments.content');
    });
    Route::post('pages/{page}/attachments', [AttachmentController::class, 'store'])
        ->middleware(['api.scope:attachments:write', 'throttle:60,1'])
        ->name('pages.attachments.store');

    Route::middleware('api.scope:spaces:read')->group(function () {
        Route::get('spaces', [SpaceController::class, 'index'])->name('spaces.index');
        Route::get('spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
        Route::get('spaces/{space}/members', [SpaceMemberController::class, 'index'])->name('spaces.members.index');
    });
    Route::middleware('api.scope:spaces:write')->group(function () {
        Route::post('spaces', [SpaceController::class, 'store'])->name('spaces.store');
        Route::match(['put', 'patch'], 'spaces/{space}', [SpaceController::class, 'update'])->name('spaces.update');
        Route::delete('spaces/{space}', [SpaceController::class, 'destroy'])->name('spaces.destroy');
        Route::delete('spaces/{space}/content', [SpaceController::class, 'destroyContent'])->name('spaces.content.destroy');
        Route::post('spaces/{space}/content/transfer', [SpaceController::class, 'transferContent'])->name('spaces.content.transfer');
        Route::post('spaces/{space}/members', [SpaceMemberController::class, 'store'])->name('spaces.members.store');
        Route::patch('spaces/{space}/members/{user}', [SpaceMemberController::class, 'update'])->name('spaces.members.update');
        Route::delete('spaces/{space}/members/{user}', [SpaceMemberController::class, 'destroy'])->name('spaces.members.destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('api.scope:admin:read')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        });
        Route::middleware('api.scope:admin:write')->group(function () {
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/password-reset', [UserPasswordResetController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('users.password-reset');
        });
    });
});
