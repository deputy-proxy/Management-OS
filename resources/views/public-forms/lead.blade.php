<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Contact us') }}</title></head>
<body>
    <main>
        <h1>{{ __('Contact us') }}</h1>
        @include('public-forms._form')
        <form method="POST" action="{{ route('public.leads.store') }}">
            @csrf
            <label>{{ __('First name') }} <input name="first_name" value="{{ old('first_name') }}" required maxlength="100"></label>
            <label>{{ __('Middle name') }} <input name="middle_name" value="{{ old('middle_name') }}" maxlength="100"></label>
            <label>{{ __('Last name') }} <input name="last_name" value="{{ old('last_name') }}" required maxlength="100"></label>
            <label>{{ __('Email') }} <input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></label>
            <label>{{ __('Phone') }} <input name="phone" value="{{ old('phone') }}" required maxlength="50"></label>
            <label>{{ __('Topic of interest') }} <input name="topic_interest" value="{{ old('topic_interest') }}" required maxlength="255"></label>
            <label>{{ __('Product or service of interest') }} <input name="product_interest" value="{{ old('product_interest') }}" required maxlength="255"></label>
            <label hidden>Website <input name="website" tabindex="-1" autocomplete="off"></label>
            <label><input type="checkbox" name="privacy_consent" value="1" required> {{ __('I agree to the processing of my information to respond to this enquiry. See the privacy policy.') }}</label>
            <button type="submit">{{ __('Send enquiry') }}</button>
        </form>
    </main>
</body>
</html>
