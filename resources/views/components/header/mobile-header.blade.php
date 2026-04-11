<div id="grve-main-responsive-header" data-sticky="simple" class="grve-simple-sticky">
    <div class="grve-header-row">
        <div class="grve-left-area">
            <div class="grve-logo">
                <div class="grve-logo-inner">
                    <a href="{{ url('/') }}" title="{{ config('app.name') }}">
                        <img src="{{ asset('images/logo-edventure-png.png') }}" 
                             class="grve-logo-responsive" 
                             alt="{{ config('app.name') }}" 
                             decoding="async" />
                    </a>
                </div>
            </div>
        </div>

        <div class="grve-right-area">
            <div class="grve-header-elements"></div>
            
            <!-- Mobile Menu Toggle -->
            <a href="#grve-responsive-menu-area" 
               class="grve-hidden-menu-icon grve-toggle-responsive-menu grve-position-right"
               title="Open Menu">
                <span class="screen-reader-text">Open Menu</span>
                <div class="grve-hidden-menu-inner">
                    <span class="grve-item grve-burger-icon grve-style-1 grve-with-close">
                        <svg width="24px" height="24px" viewBox="0 0 24 24" stroke-width="0">
                            <path d="M24,17 L24,19 L0,19 L0,17 L24,17 Z M24,11 L24,13 L0,13 L0,11 L24,11 Z M24,5 L24,7 L0,7 L0,5 L24,5 Z"></path>
                        </svg>
                        <svg width="24px" height="24px" viewBox="0 0 24 24" stroke-width="0" style="display:none;">
                            <path d="M6.61289944,5.20970461 L6.70710678,5.29289322 L12,10.585 L17.2928932,5.29289322 L17.3871006,5.20970461 C17.7793918,4.90467972 18.3466228,4.93240926 18.7071068,5.29289322 C19.0976311,5.68341751 19.0976311,6.31658249 18.7071068,6.70710678 L18.7071068,6.70710678 L13.415,12 L18.7071068,17.2928932 C19.0976311,17.6834175 19.0976311,18.3165825 18.7071068,18.7071068 C18.3466228,19.0675907 17.7793918,19.0953203 17.3871006,18.7902954 L17.2928932,18.7071068 L12,13.415 L6.70710678,18.7071068 L6.61289944,18.7902954 C6.22060824,19.0953203 5.65337718,19.0675907 5.29289322,18.7071068 C4.90236893,18.3165825 4.90236893,17.6834175 5.29289322,17.2928932 L5.29289322,17.2928932 L10.585,12 L5.29289322,6.70710678 C4.90236893,6.31658249 4.90236893,5.68341751 5.29289322,5.29289322 C5.65337718,4.93240926 6.22060824,4.90467972 6.61289944,5.20970461 Z"></path>
                        </svg>
                    </span>
                </div>
            </a>
        </div>
    </div>
</div>
