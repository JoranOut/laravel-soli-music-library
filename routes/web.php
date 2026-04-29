<?php

use App\Http\Controllers\Admin\InstrumentAliasController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Muziekstukken\PartController;
use App\Http\Controllers\Muziekstukken\PieceController;
use App\Http\Middleware\EnsureUserHasMusicAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsEditor;
use App\Http\Middleware\EnsureUserIsEditorOrDirigent;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->middleware('auth');

Route::get('/auth/redirect', [AuthController::class, 'redirect'])->name('login');
Route::get('/auth/callback', [AuthController::class, 'callback']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout');

Route::post('locale/{locale}', function (string $locale) {
    if (in_array($locale, ['nl', 'en'])) {
        session()->put('locale', $locale);
    }

    return back();
})->name('locale.switch');

// Editor-only routes (CRUD) — registered first so /create matches before {piece}
Route::middleware(['auth', EnsureUserIsEditor::class])->group(function () {
    Route::get('/muziekstukken/create', [PieceController::class, 'create'])->name('muziekstukken.create');
    Route::post('/muziekstukken', [PieceController::class, 'store'])->name('muziekstukken.store');
    Route::post('/muziekstukken/{piece}/archive', [PieceController::class, 'archive'])->name('muziekstukken.archive');
    Route::post('/muziekstukken/{piece}/restore', [PieceController::class, 'restore'])->name('muziekstukken.restore')->withTrashed();
    Route::delete('/muziekstukken/{piece}', [PieceController::class, 'destroy'])->name('muziekstukken.destroy')->withTrashed();
    Route::post('/muziekstukken/{piece}/parts', [PartController::class, 'store'])->name('muziekstukken.parts.store');
    Route::put('/muziekstukken/{piece}/parts/{part}', [PartController::class, 'update'])->name('muziekstukken.parts.update');
    Route::delete('/muziekstukken/{piece}/parts/{part}', [PartController::class, 'destroy'])->name('muziekstukken.parts.destroy');
    Route::post('/muziekstukken/{piece}/audio', [PieceController::class, 'updateAudio'])->name('muziekstukken.audio.update');
    Route::delete('/muziekstukken/{piece}/audio', [PieceController::class, 'deleteAudio'])->name('muziekstukken.audio.destroy');
});

// Edit/update — editors AND dirigent (field restrictions enforced in controller)
Route::middleware(['auth', EnsureUserIsEditorOrDirigent::class])->group(function () {
    Route::get('/muziekstukken/{piece}/edit', [PieceController::class, 'edit'])->name('muziekstukken.edit')->withTrashed();
    Route::put('/muziekstukken/{piece}', [PieceController::class, 'update'])->name('muziekstukken.update')->withTrashed();
    Route::post('/muziekstukken/{piece}/usages', [PieceController::class, 'storeUsage'])->name('muziekstukken.usages.store');
});

// Read routes (editors + dirigent + members with assignments)
Route::middleware(['auth', EnsureUserHasMusicAccess::class])->group(function () {
    Route::get('/muziekstukken/suggestions', [PieceController::class, 'suggestions'])->name('muziekstukken.suggestions');
    Route::get('/muziekstukken', [PieceController::class, 'index'])->name('muziekstukken.index');
    Route::get('/muziekstukken/{piece}', [PieceController::class, 'show'])->name('muziekstukken.show');
    Route::get('/parts/{part}/download', [PartController::class, 'download'])
        ->name('parts.download')
        ->middleware('signed');
    Route::get('/parts/{part}/download-url', [PartController::class, 'downloadUrl'])
        ->name('parts.download-url');
    Route::get('/parts/{part}/view', [PartController::class, 'view'])
        ->name('parts.view')
        ->middleware('signed');
    Route::get('/parts/{part}/view-url', [PartController::class, 'viewUrl'])
        ->name('parts.view-url');
    Route::get('/muziekstukken/{piece}/audio', [PieceController::class, 'streamAudio'])
        ->name('muziekstukken.audio.stream')
        ->middleware('signed');
});

Route::middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/admin/roles', [RolePermissionController::class, 'index'])->name('admin.roles');
    Route::put('/admin/roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');
});

Route::middleware(['auth', 'can:manage-instrument-aliases'])->group(function () {
    Route::get('/admin/instrument-aliases', [InstrumentAliasController::class, 'index'])->name('admin.instrument-aliases');
    Route::put('/admin/instrument-aliases/{instrumentType}', [InstrumentAliasController::class, 'update'])->name('admin.instrument-aliases.update');
});
