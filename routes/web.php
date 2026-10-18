<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

Route::prefix('courses')->group(function () {
    Route::get('/english-kids', [CourseController::class, 'englishKids'])->name('courses.english-kids');
    Route::get('/english-adults', [CourseController::class, 'englishAdults'])->name('courses.english-adults');
    Route::get('/english', [CourseController::class, 'english'])->name('courses.english');
    Route::get('/kids', [CourseController::class, 'kids'])->name('courses.kids');
    Route::get('/corporate', [CourseController::class, 'corporate'])->name('courses.corporate');
});

Route::prefix('public')->name('public.')->middleware('throttle:5,1')->group(function () {
    Route::get('/enquiries', [PublicSubmissionController::class, 'leadForm'])->name('leads.create');
    Route::post('/enquiries', [PublicSubmissionController::class, 'submitLead'])->name('leads.store');
    Route::get('/competitions/{competition}/entries/create', [PublicSubmissionController::class, 'competitionForm'])->name('competitions.entries.create');
    Route::post('/competitions/{competition}/entries', [PublicSubmissionController::class, 'submitCompetitionEntry'])->name('competitions.entries.store');
    Route::get('/newsletter/subscribe', [PublicSubmissionController::class, 'newsletterForm'])->name('newsletter.create');
    Route::post('/newsletter/subscribe', [PublicSubmissionController::class, 'subscribeNewsletter'])->name('newsletter.store');
    Route::get('/reviews/create', [PublicSubmissionController::class, 'reviewForm'])->name('reviews.create');
    Route::post('/reviews', [PublicSubmissionController::class, 'submitReview'])->name('reviews.store');
});

// Other routes
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/cookies', [PageController::class, 'cookies'])->name('cookies');
Route::get('/cariere', [PageController::class, 'careers'])->name('careers');