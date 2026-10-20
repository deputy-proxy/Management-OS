<?php

namespace App\Http\Controllers;

use App\Models\EmployeeEvaluation;
use App\Services\EmployeeEvaluationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeEvaluationAcknowledgmentController extends Controller
{
    public function show(int $evaluation, string $token): View
    {
        $record = EmployeeEvaluation::query()->with('employee')->findOrFail($evaluation);

        if (! $this->validLink($record, $token)) {
            abort(410, 'This evaluation acknowledgment link is invalid, expired, or has already been used.');
        }

        return view('employee-evaluations.acknowledge', ['evaluation' => $record, 'token' => $token]);
    }

    public function store(Request $request, int $evaluation, string $token, EmployeeEvaluationWorkflowService $workflow): Response|RedirectResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $record = $workflow->acknowledge($evaluation, $token, $validated['comments'] ?? null);
        } catch (ValidationException $exception) {
            return response()->view('employee-evaluations.invalid-link', ['message' => 'This link is invalid, expired, or has already been used.'], 410);
        }

        return response()->view('employee-evaluations.acknowledged', ['evaluation' => $record]);
    }

    private function validLink(EmployeeEvaluation $evaluation, string $token): bool
    {
        $deadline = $evaluation->getAttribute('approval_deadline_at');
        $tokenExpiresAt = $evaluation->getAttribute('approval_token_expires_at');

        return $evaluation->status === 'Submitted'
            && $evaluation->submission_email_sent_at !== null
            && $deadline !== null
            && Carbon::parse($deadline)->isFuture()
            && $tokenExpiresAt !== null
            && Carbon::parse($tokenExpiresAt)->isFuture()
            && is_string($evaluation->approval_token_hash)
            && hash_equals($evaluation->approval_token_hash, hash('sha256', $token));
    }
}
