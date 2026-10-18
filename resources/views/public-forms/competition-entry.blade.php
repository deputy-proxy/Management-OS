<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Enter competition') }}: {{ $competition->title }}</title></head>
<body>
    <main>
        <h1>{{ __('Enter competition') }}: {{ $competition->title }}</h1>
        <p>{{ $competition->description }}</p>
        @include('public-forms._form')
        <form method="POST" action="{{ route('public.competitions.entries.store', $competition) }}">
            @csrf
            <label>{{ __('First name') }} <input name="first_name" value="{{ old('first_name') }}" required maxlength="100"></label>
            <label>{{ __('Last name') }} <input name="last_name" value="{{ old('last_name') }}" required maxlength="100"></label>
            <label>{{ __('Email') }} <input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></label>
            <label>{{ __('Entry title') }} <input name="title" value="{{ old('title') }}" required maxlength="255"></label>
            <label>{{ __('Entry') }} <textarea name="content" required maxlength="10000">{{ old('content') }}</textarea></label>
            <label hidden>Website <input name="website" tabindex="-1" autocomplete="off"></label>
            <label><input type="checkbox" name="privacy_consent" value="1" required> {{ __('I agree to the processing of my entry according to the privacy policy and competition rules.') }}</label>
            <button type="submit">{{ __('Submit entry') }}</button>
        </form>
    </main>
</body>
</html>
