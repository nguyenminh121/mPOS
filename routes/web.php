<?php

use App\Http\Controllers\Public\PageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::get('/home', [PageController::class, 'home'])->name('home.page');
require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
