<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
    <h1>Annual employee evaluation started</h1>
    <p>An annual evaluation has been created for {{ $evaluation->employee?->full_name ?? 'an employee' }} for {{ $evaluation->evaluation_year }}.</p>
    <p>Evaluator: {{ $evaluation->performedBy?->name ?? 'Assigned line manager' }}</p>
    <p>Target completion date: {{ $evaluation->due_by?->format('F j, Y') }}</p>
    <p>The evaluator can complete the evaluation in Management-OS. The employee will receive the completed report and an acknowledgment link when it is submitted.</p>
</body>
</html>
