<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $details['subject'] }}</title>
</head>
<body>
    <h1>{{ $details['subject'] }}</h1>
    <p>{{ __('A new comment was added to :record.', ['record' => $details['record_title']]) }}</p>
    <blockquote>{{ $details['comment_body'] }}</blockquote>
    <p><a href="{{ $details['url'] }}">{{ __('View record') }}</a></p>
</body>
</html>
