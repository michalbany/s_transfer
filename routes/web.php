<?php

use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

Route::get('/', [PackageController::class, 'create'])->name('packages.create');

Route::get('/index', [PackageController::class, 'index'])->name('packages.index');
Route::get('/clear', [PackageController::class, 'clear'])->name('packages.clear');

// Chunk upload endpoints
Route::post('/init-upload', [PackageController::class, 'initUpload']);
Route::post('/upload-chunk', [PackageController::class, 'uploadChunk']);
Route::post('/finalize-upload', [PackageController::class, 'finalizeUpload']);

Route::get('/{token}', [PackageController::class, 'show'])->name('packages.show');
Route::get('/{token}/download', [PackageController::class, 'download'])->name('packages.download');


require __DIR__.'/auth.php';
