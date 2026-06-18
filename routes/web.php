<?php

use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtracurricularAchievementController;
use App\Http\Controllers\GroupSocialPassportController;
use App\Http\Controllers\HealthPassportController;
use App\Http\Controllers\PortfolioItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PsychologicalProfileController;
use App\Http\Controllers\StudentGroupController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/student-profile', [StudentProfileController::class, 'edit'])->name('student-profile.edit');
    Route::post('/student-profile', [StudentProfileController::class, 'update'])->name('student-profile.update');
    Route::post('/student-profile/submit', [StudentProfileController::class, 'submit'])->name('student-profile.submit');
    Route::post('/student-profile/achievements', [ExtracurricularAchievementController::class, 'store'])->name('student-profile.achievements.store');
    Route::delete('/student-profile/achievements/{achievement}', [ExtracurricularAchievementController::class, 'destroy'])->name('student-profile.achievements.destroy');
    Route::post('/student-profile/portfolio', [PortfolioItemController::class, 'store'])->name('student-profile.portfolio.store');
    Route::delete('/student-profile/portfolio/{portfolioItem}', [PortfolioItemController::class, 'destroy'])->name('student-profile.portfolio.destroy');

    Route::get('/student-profiles', [StudentProfileController::class, 'index'])->name('student-profiles.index');
    Route::get('/student-profiles/create', [StudentProfileController::class, 'createManaged'])->name('student-profiles.create');
    Route::post('/student-profiles', [StudentProfileController::class, 'storeManaged'])->name('student-profiles.store');
    Route::get('/student-profiles/{student}/edit', [StudentProfileController::class, 'editManaged'])->name('student-profiles.edit');
    Route::post('/student-profiles/{student}', [StudentProfileController::class, 'updateManaged'])->name('student-profiles.update');
    Route::post('/student-profiles/{student}/status', [StudentProfileController::class, 'updateStatus'])->name('student-profiles.status.update');
    Route::post('/student-profiles/{student}/review-block', [StudentProfileController::class, 'updateReviewBlock'])->name('student-profiles.review-block.update');
    Route::post('/student-profiles/{student}/health-passport', [HealthPassportController::class, 'updateForStudent'])->name('student-profiles.health-passport.update');
    Route::post('/student-profiles/{student}/achievements', [ExtracurricularAchievementController::class, 'storeForStudent'])->name('student-profiles.achievements.store');
    Route::delete('/student-profiles/{student}/achievements/{achievement}', [ExtracurricularAchievementController::class, 'destroyForStudent'])->name('student-profiles.achievements.destroy');
    Route::post('/student-profiles/{student}/portfolio', [PortfolioItemController::class, 'storeForStudent'])->name('student-profiles.portfolio.store');
    Route::delete('/student-profiles/{student}/portfolio/{portfolioItem}', [PortfolioItemController::class, 'destroyForStudent'])->name('student-profiles.portfolio.destroy');

    Route::get('/psychological-profile', [PsychologicalProfileController::class, 'index'])->name('psychological-profile.index');
    Route::post('/psychological-profile', [PsychologicalProfileController::class, 'update'])->name('psychological-profile.update');

    Route::get('/health-passport', [HealthPassportController::class, 'index'])->name('health-passport.index');
    Route::post('/health-passport', [HealthPassportController::class, 'update'])->name('health-passport.update');

    Route::get('/groups', [StudentGroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [StudentGroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{studentGroup}/social-passport', [GroupSocialPassportController::class, 'editGroup'])->name('groups.social-passport.edit');
    Route::post('/groups/{studentGroup}/social-passport', [GroupSocialPassportController::class, 'updateGroup'])->name('groups.social-passport.update');

    Route::get('/group-social-passport', [GroupSocialPassportController::class, 'edit'])->name('group-social-passport.edit');
    Route::post('/group-social-passport', [GroupSocialPassportController::class, 'update'])->name('group-social-passport.update');

    Route::get('/analytics-dashboard', [AnalyticsDashboardController::class, 'index'])->name('analytics-dashboard.index');
    Route::get('/analytics-dashboard/reports/{type}/export', [AnalyticsDashboardController::class, 'export'])
        ->whereIn('type', ['student', 'group', 'course', 'faculty', 'academic-risks', 'social-risks', 'psychological-risks', 'medical-risks'])
        ->name('analytics-dashboard.reports.export');
});

require __DIR__.'/auth.php';
