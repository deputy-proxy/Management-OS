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

            <div class="grve-header-elements grve-position-right">
                <div class="grve-header-element">
                    <a href="#grve-search-modal" class="grve-toggle-modal" title="Search">
                        <span class="grve-item">
                            <span class="screen-reader-text">Toggle search modal</span>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" stroke-width="0">
                                <path d="M11,3 C15.418278,3 19,6.581722 19,11 C19,13.1038245 18.1879088,15.0179749 16.860099,16.4460786 L20.2071068,19.7928932 C20.5976311,20.1834175 20.5976311,20.8165825 20.2071068,21.2071068 C19.8466228,21.5675907 19.2793918,21.5953203 18.8871006,21.2902954 L18.7928932,21.2071068 L15.3199029,17.7345448 C14.0739277,18.5354376 12.5912161,19 11,19 C6.581722,19 3,15.418278 3,11 C3,6.581722 6.581722,3 11,3 Z M11,5 C7.6862915,5 5,7.6862915 5,11 C5,14.3137085 7.6862915,17 11,17 C14.3137085,17 17,14.3137085 17,11 C17,7.6862915 14.3137085,5 11,5 Z"></path>
                            </svg>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
