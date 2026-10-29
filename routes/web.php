<?php

use App\Enums\PageType;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SpaceController;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Screens only: every write goes through the REST API in routes/api.php.
    Route::get('/', [PageController::class, 'home'])->name('home');

    // Content is browsed by space now; the old cross-space listings land on Home.
    Route::redirect('pages', '/');
    Route::redirect('boards', '/');

    Route::get('boards/{board}', [PageController::class, 'showBoard'])->name('boards.show');
    Route::get('trash', [PageController::class, 'trash'])->name('pages.trash');
    Route::redirect('pages/trash', '/trash');
    // Boards used to live at /pages/{id}; send old links there instead of 404ing.
    Route::resource('pages', PageController::class)->only(['show'])->missing(
        fn (Request $request) => Page::where('type', PageType::Board)->whereKey($request->route('page'))->exists()
            ? to_route('boards.show', $request->route('page'))
            : abort(404)
    );

    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('files', [FileController::class, 'index'])->name('files.index');

    Route::resource('spaces', SpaceController::class)->only(['index', 'create', 'show', 'edit']);
    Route::get('spaces/{space}/members', [SpaceController::class, 'members'])->name('spaces.members');
    Route::get('spaces/{space}/danger', [SpaceController::class, 'danger'])->name('spaces.danger');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log');
        Route::resource('users', UserController::class)->only(['index', 'create', 'edit']);
    });
});

require __DIR__.'/settings.php';
