<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/about', [\App\Http\Controllers\AboutController::class, 'index'])->name('about');
Route::get('/contact', [\App\Http\Controllers\ContactController::class, 'index'])->name('contact');
Route::get('/courses', [\App\Http\Controllers\CourseController::class, 'index'])->name('courses.index');

// Course routes
Route::prefix('courses')->group(function () {
    Route::get('/english-kids', [\App\Http\Controllers\CourseController::class, 'englishKids'])->name('courses.english-kids');
    Route::get('/english-adults', [\App\Http\Controllers\CourseController::class, 'englishAdults'])->name('courses.english-adults');
    Route::get('/english-individual', [\App\Http\Controllers\CourseController::class, 'englishIndividual'])->name('courses.english-individual');
    Route::get('/english-conversation', [\App\Http\Controllers\CourseController::class, 'englishConversation'])->name('courses.english-conversation');
    Route::get('/english', [\App\Http\Controllers\CourseController::class, 'english'])->name('courses.english');
    Route::get('/kids', [\App\Http\Controllers\CourseController::class, 'kids'])->name('courses.kids');
    Route::get('/schools', [\App\Http\Controllers\CourseController::class, 'kids'])->name('courses.schools');
    Route::get('/german-kids', [\App\Http\Controllers\CourseController::class, 'kids'])->name('courses.german-kids');
    Route::get('/french-kids', [\App\Http\Controllers\CourseController::class, 'kids'])->name('courses.french-kids');
    Route::get('/japanese-kids', [\App\Http\Controllers\CourseController::class, 'kids'])->name('courses.japanese-kids');
    Route::get('/corporate', [\App\Http\Controllers\CourseController::class, 'corporate'])->name('courses.corporate');
});

// Other routes
Route::get('/blog', [\App\Http\Controllers\PageController::class, 'privacy'])->name('blog');
Route::get('/search', [\App\Http\Controllers\PageController::class, 'privacy'])->name('search');
Route::get('/privacy', [\App\Http\Controllers\PageController::class, 'privacy'])->name('privacy');
Route::get('/cookies', [\App\Http\Controllers\PageController::class, 'cookies'])->name('cookies');
Route::get('/cariere', [\App\Http\Controllers\PageController::class, 'careers'])->name('careers');