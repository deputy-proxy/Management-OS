@if (session('status'))
    <p role="status">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <div role="alert">
        <p>{{ __('Please correct the following errors:') }}</p>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
