<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Acknowledge employee evaluation</title>
</head>
<body>
    <main>
        <h1>Acknowledge employee evaluation</h1>
        <p>This is the {{ $evaluation->evaluation_year }} evaluation for {{ $evaluation->employee?->full_name ?? 'you' }}.</p>
        <p>Final score: <strong>{{ number_format((float) $evaluation->final_score, 2) }}%</strong></p>
        <p>Acknowledgment confirms that you received the evaluation. It does not mean you agree with its content. You may optionally leave a comment below.</p>
        <p>The link expires at {{ $evaluation->approval_deadline_at?->timezone(config('app.timezone'))->format('F j, Y H:i T') }}. If you do not act within 7 days of the submission email, the status changes to Approved automatically without recording an employee acknowledgment.</p>
        <form method="POST" action="{{ route('employee-evaluations.acknowledge.store', ['evaluation' => $evaluation->id, 'token' => $token]) }}">
            @csrf
            <label for="comments">Optional comments</label>
            <textarea id="comments" name="comments" rows="6" maxlength="5000">{{ old('comments') }}</textarea>
            @error('comments') <p role="alert">{{ $message }}</p> @enderror
            <button type="submit">Acknowledge receipt</button>
        </form>
    </main>
</body>
</html>
