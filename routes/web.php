<?php

use App\Http\Controllers\EmployeeEvaluationAcknowledgmentController;
use App\Http\Controllers\Health\ReadinessController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/ready', ReadinessController::class)->name('health.readiness');
Route::middleware('auth')->get('/finance/issued-invoice-artifacts/{artifact}/download', IssuedInvoiceArtifactDownloadController::class)
    ->whereNumber('artifact')->name('finance.issued-invoice-artifacts.download');
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

Route::prefix('employee-evaluations/{evaluation}/acknowledge')
    ->name('employee-evaluations.acknowledge.')
    ->middleware('throttle:10,1')
    ->group(function (): void {
        Route::get('/{token}', [EmployeeEvaluationAcknowledgmentController::class, 'show'])->name('show');
        Route::post('/{token}', [EmployeeEvaluationAcknowledgmentController::class, 'store'])->name('store');
    });
