<div id="grve-feature-section" 
    data-height="85" 
    style="background-color: transparent;min-height:200px;"
    class="relative">
    
    <!-- Slider Navigation -->
    <div class="absolute inset-x-0 top-0 pointer-events-none">
        <div class="max-w-full mx-auto px-4">
            <div class="flex items-center justify-between pointer-events-auto">
                <div></div>
                <div class="flex items-center gap-4">
                    <div class="grve-counter"></div>
                    <div class="flex gap-2">
                        <a href="#" class="grve-slider-arrow grve-arrow-prev" title="Previous Slide">Previous</a>
                        <a href="#" class="grve-slider-arrow grve-arrow-next" title="Next Slide">Next</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slider Container -->
    <div class="grve-slider-wrapper grve-carousel-items">
        <div class="grve-carousel owl-carousel grve-carousel-items-wrapper" 
            data-columns="1" 
            data-columns-tablet="1" 
            data-columns-mobile="1"
            data-margin="0">
            
            @forelse($slides ?? [] as $slide)
            <div class="grve-carousel-item grve-carousel-item-wrapper">
                <div class="grve-slider-item" 
                    id="{{ $slide['id'] ?? 'slide-' . $loop->index }}"
                    style="background-image: url({{ $slide['image'] ?? asset('images/slider-default.png') }});">
                    
                    <div class="grve-bg-overlay" 
                        style="background-color:rgba({{ $slide['overlay_color'] ?? '248,82,1' }},{{ $slide['overlay_opacity'] ?? '0.10' }});"></div>
                    
                    <div class="grve-content">
                        <div class="grve-content-inner">
                            @if(!empty($slide['title']))
                            <h2 class="text-8xl font-bold font-jost">{{ $slide['title'] }}</h2>
                            @endif
                            
                            @if(!empty($slide['description']))
                            <p class="text-lg">{{ $slide['description'] }}</p>
                            @endif
                            
                            @if(!empty($slide['button']))
                            <a href="{{ $slide['button']['url'] }}" class="inline-block bg-primary-1 text-white py-3 px-6 rounded">
                                {{ $slide['button']['text'] }}
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="grve-carousel-item grve-carousel-item-wrapper">
                <div class="grve-slider-item" 
                    style="background-image: url({{ asset('images/slider-default.png') }});">
                    <div class="grve-bg-overlay" style="background-color:rgba(248,82,1,0.10);"></div>
                </div>
            </div>
            @endforelse
            
        </div>
    </div>
</div>
