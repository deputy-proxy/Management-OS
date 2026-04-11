<div class="grve-area-item-sub-menu grve-sub-menu">
    <div class="grve-area-item-sub-menu-inner">
        <div class="grve-section grve-row-section grve-fullwidth-background grve-padding-top-1x grve-padding-bottom-1x grve-headings-white grve-link-inherit grve-link-hover-primary-3 grve-with-bg-color"
            data-header-color="light"
            style="background-color:#001C4C;color:#ffffff;">
            
            <div class="grve-container">
                <div class="grve-row grve-bookmark grve-columns-gap-60 grve-mobile-vertical-gap-30">
                    
                    @if(!empty($title))
                    <div class="grve-column wpb_column grve-bookmark grve-column-1-3 grve-with-bg-transparent grve-change-order" data-mobile-order="1">
                        <div class="grve-column-wrapper">
                            <div class="grve-column-content">
                                <h3 class="grve-element grve-title grve-align-inherit grve-h6 grve-with-line grve-bottom-line">
                                    <span>{{ $title }}</span>
                                    <span class="grve-title-line grve-bg-primary-1" style="width: 120px;height: 5px;"></span>
                                </h3>
                            </div>
                        </div>
                    </div>
                    @endif

                    @foreach($columns as $column)
                    <div class="grve-column wpb_column grve-bookmark grve-column-1-3 grve-with-bg-transparent">
                        <div class="grve-column-wrapper">
                            <div class="grve-column-content">
                                <h3 class="grve-element grve-title grve-align-inherit grve-h6" style="margin-bottom: 18px;">
                                    <span>{{ $column['title'] }}</span>
                                </h3>
                                
                                @if(!empty($column['links']))
                                <div class="grve-element grve-text">
                                    @foreach($column['links'] as $link)
                                    <p><a href="{{ $link['url'] }}">{{ $link['text'] }}</a></p>
                                    @endforeach
                                </div>
                                @elseif(!empty($column['content']))
                                <div class="grve-element grve-text">
                                    <p>{!! $column['content'] !!}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                    
                </div>
            </div>
        </div>
    </div>
</div>
