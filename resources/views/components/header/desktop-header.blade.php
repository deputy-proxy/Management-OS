<div id="grve-main-header" 
    class="grve-layout-default grve-style-2 grve-megamenu-change-header-skin grve-skin-default grve-scrollup-sticky grve-menu-type-underline"
    data-sticky="scrollup" 
    data-sticky-height="86" 
    data-header-skin="default"
    data-megamenu-header-skin="default">
    
    <div class="grve-header-row">
        <!-- Logo -->
        <div class="grve-left-area">
            <div class="grve-logo">
                <div class="grve-logo-inner">
                    <a href="{{ url('/') }}" title="{{ config('app.name') }}">
                        <img src="{{ asset('images/logo-edventure-png.png') }}" 
                             class="grve-default" 
                             alt="{{ config('app.name') }}" 
                             decoding="async" />
                        <img src="{{ asset('images/logo-edventure-school-white.png') }}" 
                             class="grve-light" 
                             alt="{{ config('app.name') }}" 
                             decoding="async" />
                        <img src="{{ asset('images/logo-edventure-png.png') }}" 
                             class="grve-dark" 
                             alt="{{ config('app.name') }}" 
                             decoding="async" />
                    </a>
                </div>
            </div>

            <!-- Main Navigation -->
            @include('components.navigation.main-menu')
        </div>

        <!-- Right Area: Secondary Menu & Search -->
        <div class="grve-right-area">
            @include('components.navigation.secondary-menu')
        </div>
    </div>
</div>