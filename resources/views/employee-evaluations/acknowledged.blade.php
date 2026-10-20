<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acknowledgment recorded</title></head>
<body>
    <main>
        <h1>Acknowledgment recorded</h1>
        <p>Your receipt acknowledgment for the {{ $evaluation->evaluation_year }} evaluation has been recorded.</p>
        @if ($evaluation->approval_comments)
            <h2>Your comment</h2>
            <p>{{ $evaluation->approval_comments }}</p>
        @endif
    </main>
</body>
</html>
