<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Submit a review') }}</title></head>
<body>
    <main>
        <h1>{{ __('Submit a review') }}</h1>
        <p>{{ __('Reviews are limited to existing customers and are reviewed before publication.') }}</p>
        @include('public-forms._form')
        <form method="POST" action="{{ route('public.reviews.store') }}">
            @csrf
            <label>{{ __('Product type') }}
                <select name="product_type" required>
                    <option value="">{{ __('Choose a type') }}</option>
                    <option value="course" @selected(old('product_type') === 'course')>{{ __('Course') }}</option>
                    <option value="camp" @selected(old('product_type') === 'camp')>{{ __('Camp') }}</option>
                    <option value="workshop" @selected(old('product_type') === 'workshop')>{{ __('Workshop') }}</option>
                    <option value="event" @selected(old('product_type') === 'event')>{{ __('Event') }}</option>
                </select>
            </label>
            <label>{{ __('Product ID') }} <input type="number" name="product_id" min="1" value="{{ old('product_id') }}" required></label>
            <label>{{ __('Your name') }} <input name="reviewer_name" value="{{ old('reviewer_name') }}" required maxlength="255"></label>
            <label>{{ __('Customer email') }} <input type="email" name="reviewer_email" value="{{ old('reviewer_email') }}" required maxlength="255"></label>
            <label>{{ __('Rating') }}
                <select name="rating" required>
                    @foreach (range(1, 5) as $rating)
                        <option value="{{ $rating }}" @selected((string) old('rating') === (string) $rating)>{{ $rating }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Review') }} <textarea name="content" required maxlength="5000">{{ old('content') }}</textarea></label>
            <label hidden>Website <input name="website" tabindex="-1" autocomplete="off"></label>
            <label><input type="checkbox" name="privacy_consent" value="1" required> {{ __('I agree to the processing of my review according to the privacy policy.') }}</label>
            <button type="submit">{{ __('Submit review') }}</button>
        </form>
    </main>
</body>
</html>
