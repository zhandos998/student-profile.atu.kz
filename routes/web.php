<?php

use App\Http\Controllers\ExtracurricularAchievementController;
use App\Http\Controllers\PortfolioItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/student-profile', [StudentProfileController::class, 'edit'])->name('student-profile.edit');
    Route::post('/student-profile', [StudentProfileController::class, 'update'])->name('student-profile.update');
    Route::post('/student-profile/achievements', [ExtracurricularAchievementController::class, 'store'])->name('student-profile.achievements.store');
    Route::delete('/student-profile/achievements/{achievement}', [ExtracurricularAchievementController::class, 'destroy'])->name('student-profile.achievements.destroy');
    Route::post('/student-profile/portfolio', [PortfolioItemController::class, 'store'])->name('student-profile.portfolio.store');
    Route::delete('/student-profile/portfolio/{portfolioItem}', [PortfolioItemController::class, 'destroy'])->name('student-profile.portfolio.destroy');
});

require __DIR__.'/auth.php';
