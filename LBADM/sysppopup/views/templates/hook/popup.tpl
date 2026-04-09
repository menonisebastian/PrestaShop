{*
* SYSPROVIDER Popup Template
*
* @author    SYSPROVIDER S.L.
* @copyright 2024 SYSPROVIDER S.L.
*}

<div id="sysp-popup-overlay" class="sysp-popup-overlay">
    <div id="sysp-popup-container" 
         class="sysp-popup-container 
                sysp-border-{$popup_border_style} 
                sysp-shadow-{$popup_shadow}
                {if $popup_fullscreen}sysp-fullscreen{/if}"
         data-frequency="{$popup_frequency}"
         data-frequency-value="{$popup_frequency_value}"
         data-delay="{$popup_delay}"
         data-animation="{$popup_animation}"
         {if !$popup_fullscreen}style="max-width: {if $popup_width != 'auto'}{$popup_width}px{else}90%{/if}; {if $popup_height != 'auto'}max-height: {$popup_height}px;{/if}"{/if}>
        
        <button class="sysp-popup-close" id="sysp-popup-close" aria-label="Cerrar popup">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        
        <div class="sysp-popup-content">
            {if $popup_link}
                <a href="{$popup_link}" class="sysp-popup-link">
                    <img src="{$popup_image}" alt="Popup" class="sysp-popup-image">
                </a>
            {else}
                <img src="{$popup_image}" alt="Popup" class="sysp-popup-image">
            {/if}
        </div>
    </div>
</div>
