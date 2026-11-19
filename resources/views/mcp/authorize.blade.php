<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize {{ $client->name }} · {{ config('app.name') }}</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-background text-foreground flex items-center justify-center p-6">
<main class="w-full max-w-lg rounded-xl border bg-card p-8 shadow-sm">
<h1 class="text-xl font-semibold">Authorize {{ $client->name }}</h1><p class="mt-3 text-sm">This application is requesting access to Management-OS through MCP.</p>
<p class="mt-4 text-sm">Signed in as <strong>{{ $user->email }}</strong></p>
@if (count($scopes) > 0)<h2 class="mt-6 text-sm font-semibold">Requested permissions</h2><ul class="mt-2 list-disc pl-5 text-sm">@foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach</ul>@endif
<div class="mt-8 flex gap-3">
<form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">@csrf @method('DELETE')<input type="hidden" name="client_id" value="{{ $client->id }}"><input type="hidden" name="auth_token" value="{{ $authToken }}"><button class="w-full rounded-md border px-4 py-2" type="submit">Deny</button></form>
<form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">@csrf<input type="hidden" name="client_id" value="{{ $client->id }}"><input type="hidden" name="auth_token" value="{{ $authToken }}"><button class="w-full rounded-md bg-primary px-4 py-2 text-primary-foreground" type="submit">Authorize</button></form>
</div></main></body></html>
