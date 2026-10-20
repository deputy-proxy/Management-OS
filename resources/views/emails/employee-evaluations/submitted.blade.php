<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
    <h1>Your employee evaluation is ready</h1>
    <p>Hello {{ $evaluation->employee?->full_name ?? 'there' }},</p>
    <p>Your {{ $evaluation->evaluation_year }} employee evaluation has been submitted.</p>
    <p><strong>Final score:</strong> {{ number_format((float) $evaluation->final_score, 2) }}%</p>
    <h2>Evaluation details</h2>
    @foreach (($evaluation->criteria ?? []) as $criterion)
        <section>
            <h3>{{ $criterion['label'] ?? 'Criterion' }}</h3>
            @if (!empty($criterion['description']))
                <p>{{ $criterion['description'] }}</p>
            @endif
            @if (($criterion['type'] ?? null) === 'rated_criterion')
                <p>Score: {{ $criterion['score'] ?? 'Not provided' }} / 5 (weight {{ $criterion['weight'] ?? 0 }}%)</p>
            @elseif (($criterion['type'] ?? null) === 'yes_no')
                <p>Answer: {{ ucfirst($criterion['answer'] ?? 'Not provided') }} (weight {{ $criterion['weight'] ?? 0 }}%)</p>
            @else
                <p>{{ $criterion['notes'] ?? '' }}</p>
            @endif
            @if (!empty($criterion['notes']) && in_array($criterion['type'] ?? null, ['rated_criterion', 'yes_no'], true))
                <p>Evaluator notes: {{ $criterion['notes'] }}</p>
            @endif
        </section>
    @endforeach
    <p>You have <strong>7 days</strong> from this email to acknowledge receipt and optionally add a comment. Acknowledgment confirms receipt only; it does not mean you agree with the evaluation.</p>
    <p>If you do not take action within 7 days, the evaluation status will automatically change to Approved. The system will record that as automatic approval due to timeout, not as an acknowledgment from you.</p>
    <p><a href="{{ $acknowledgmentUrl }}">Review evaluation and acknowledge receipt</a></p>
    <p>If the link expires before you act, contact your administrator.</p>
</body>
</html>
