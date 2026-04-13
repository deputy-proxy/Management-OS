<!DOCTYPE html>
<html class="no-js" lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="application-name" content="EdVenture">
    <title>{{ $title ?? 'EdVenture School - Școală de limbi străine' }}</title>
    <meta name="description" content="{{ $description ?? 'EdVenture School este o școală de limbi străine ce cultivă pasiunea pentru învățarea limbilor străine, de 20 ani.' }}">
    
    <!-- Open Graph & Meta Tags -->
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $og_title ?? 'EdVenture School - Școală de limbi străine' }}" />
    <meta property="og:description" content="{{ $og_description ?? 'EdVenture School este o școală de limbi străine ce cultivă pasiunea pentru învățarea limbilor străine, de 20 ani.' }}" />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:site_name" content="EdVenture" />
    @if(isset($og_image))
        <meta property="og:image" content="{{ $og_image }}" />
        <meta property="og:image:type" content="image/png" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    
    <link rel="canonical" href="{{ url('/') }}" />
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin />
    <link rel="dns-prefetch" href="//fonts.googleapis.com" />
    <link rel="dns-prefetch" href="//www.googletagmanager.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    
    <!-- Font Import -->
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    @stack('meta')
    @stack('styles')
</head>

<body class="home bg-black wp-singular page-template-default page grve-body grve-stretched grve-header-horizontal grve-header-is-sticky">
    @yield('content')
    
    <!-- Main App Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    
    <!-- CookieYes Cookie Consent (optional - uncomment to enable) -->
    {{-- <script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/YOUR_SITE_ID/script.js" defer></script> --}}
    
    <!-- Google Analytics (optional - add your GTM ID) -->
    {{-- <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript> --}}
    
    @stack('scripts')
</body>
</html>
