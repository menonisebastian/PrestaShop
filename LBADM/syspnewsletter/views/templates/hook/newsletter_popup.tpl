{*
 * SYSPROVIDER Newsletter Popup Template
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 *}

{if $syspnl_google_font}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="{$syspnl_google_font}" rel="stylesheet">
{/if}

{* ── OVERLAY ── *}
<div id="syspnl-overlay"
     class="syspnl-overlay syspnl-pos-{$syspnl_position}"
     style="background:{$syspnl_color_overlay};"
     data-frequency="{$syspnl_frequency}"
     data-frequency-val="{$syspnl_frequency_val}"
     data-delay="{$syspnl_delay}"
     data-animation="{$syspnl_animation}"
     data-ajax="{$syspnl_ajax_url}"
     aria-hidden="true"
     role="dialog"
     aria-modal="true"
     aria-labelledby="syspnl-title">

  {* ── CARD ── *}
  <div id="syspnl-card"
       class="syspnl-card syspnl-anim-{$syspnl_animation}"
       style="{$syspnl_bg_style}
              max-width:{$syspnl_width}px;
              border-radius:{$syspnl_border_radius}px;
              font-family:{$syspnl_font_family};">

    {* Botón cerrar *}
    <button class="syspnl-close" id="syspnl-close" aria-label="Cerrar">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.5"
           stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"/>
        <line x1="6"  y1="6" x2="18" y2="18"/>
      </svg>
    </button>

    {* ── PASO 1: Formulario ── *}
    <div id="syspnl-step-form" class="syspnl-step">

      {* Icono decorativo *}
      <div class="syspnl-icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none"
             xmlns="http://www.w3.org/2000/svg">
          <rect width="48" height="48" rx="14"
                fill="{$syspnl_color_btn_bg}" fill-opacity="0.12"/>
          <path d="M8 14a2 2 0 012-2h18a2 2 0 012 2v14a2 2 0 01-2 2H10a2 2 0 01-2-2V14z"
                stroke="{$syspnl_color_btn_bg}" stroke-width="2"
                stroke-linejoin="round"/>
          <path d="M8 14l11 9 11-9"
                stroke="{$syspnl_color_btn_bg}" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <h2 id="syspnl-title" class="syspnl-title"
          style="color:{$syspnl_color_title};
                 font-size:{$syspnl_font_size_title}px;">
        {$syspnl_title|escape:'html':'UTF-8'}
      </h2>

      <p class="syspnl-subtitle"
         style="color:{$syspnl_color_subtitle};
                font-size:{$syspnl_font_size_subtitle}px;">
        {$syspnl_subtitle|escape:'html':'UTF-8'}
      </p>

      {if $syspnl_discount_active}
        <div class="syspnl-discount-teaser"
             style="border-color:{$syspnl_color_btn_bg};
                    color:{$syspnl_color_btn_bg};">
          🎁 ¡Suscríbete y obtén tu cupón de descuento!
        </div>
      {/if}

      <div class="syspnl-form-group">
        <input type="email"
               id="syspnl-email"
               class="syspnl-input"
               placeholder="{$syspnl_placeholder|escape:'html':'UTF-8'}"
               autocomplete="email"
               style="border-color:{$syspnl_color_input_border};"
               required>
        <p class="syspnl-error" id="syspnl-error" role="alert"></p>
      </div>

      <button id="syspnl-submit"
              class="syspnl-btn"
              style="background:{$syspnl_color_btn_bg};
                     color:{$syspnl_color_btn_text};">
        <span class="syspnl-btn-label">{$syspnl_btn_text|escape:'html':'UTF-8'}</span>
        <span class="syspnl-btn-spinner" aria-hidden="true"></span>
      </button>

      <button class="syspnl-skip" id="syspnl-skip" type="button">
        No gracias
      </button>

    </div>{* /syspnl-step-form *}

    {* ── PASO 2: Éxito ── *}
    <div id="syspnl-step-success" class="syspnl-step" style="display:none;">

      <div class="syspnl-success-icon" aria-hidden="true">✅</div>

      <h2 class="syspnl-title"
          style="color:{$syspnl_color_title};
                 font-size:{$syspnl_font_size_title}px;">
        {$syspnl_success_msg|escape:'html':'UTF-8'}
      </h2>

      {if $syspnl_discount_active}
        <div id="syspnl-coupon-block" class="syspnl-coupon-block" style="display:none;">
          <p class="syspnl-coupon-msg"
             id="syspnl-coupon-msg"
             style="color:{$syspnl_color_subtitle};">
          </p>
          <div class="syspnl-coupon-code-wrap"
               style="border-color:{$syspnl_color_btn_bg};">
            <span id="syspnl-coupon-code" class="syspnl-coupon-code"
                  style="color:{$syspnl_color_btn_bg};"></span>
            <button class="syspnl-copy-btn"
                    id="syspnl-copy-btn"
                    style="background:{$syspnl_color_btn_bg};
                           color:{$syspnl_color_btn_text};"
                    aria-label="Copiar código">
              Copiar
            </button>
          </div>
          <p class="syspnl-copied-msg" id="syspnl-copied-msg">¡Copiado!</p>
        </div>
      {/if}

      <button class="syspnl-btn syspnl-btn-close-success"
              style="background:{$syspnl_color_btn_bg};
                     color:{$syspnl_color_btn_text};">
        Continuar comprando
      </button>

    </div>{* /syspnl-step-success *}

  </div>{* /syspnl-card *}
</div>{* /syspnl-overlay *}
