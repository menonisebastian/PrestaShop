<?php
/**
 * Plugin Name: PlaySat Trust Panel
 * Description: Panel de confianza. Desktop: shortcode en columna derecha. Móvil: barra inferior automática en todas las páginas.
 * Version: 1.6.0
 * Author: Sebastián/SysProviders
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $ptp_instance;
$ptp_instance = 0;

// ─── 1. VENTAJAS ──────────────────────────────────────────────────────────────

function playsat_trust_panel_items() {
    return array(
        array( 'id' => 'garantia',      'icon' => '🛡️', 'text' => '1 año de garantía en la reparación',                     'categories' => array( 'global' ) ),
        array( 'id' => 'presupuesto',   'icon' => '💬', 'text' => 'Presupuesto sin compromiso',                               'categories' => array( 'global' ) ),
        array( 'id' => 'recogida',      'icon' => '🚚', 'text' => 'Recogida y entrega a domicilio',                           'categories' => array( 'global' ) ),
        array( 'id' => 'clientes',      'icon' => '⭐', 'text' => 'Más de 10.000 clientes confían en nosotros',               'categories' => array( 'global' ) ),
        array( 'id' => 'oficial-apple', 'icon' => '🍎', 'text' => 'Servicio Técnico Oficial Apple',                           'categories' => array( 'reparaciones-apple', 'apple', 'iphone', 'ipad', 'mac', 'macbook', 'apple-watch' ) ),
        array( 'id' => 'tecnicos',      'icon' => '🔧', 'text' => 'Técnicos certificados Apple',                              'categories' => array( 'reparaciones-apple', 'apple', 'iphone', 'ipad', 'mac', 'macbook', 'apple-watch' ) ),
        array( 'id' => 'repuestos',     'icon' => '✅', 'text' => 'Repuestos y herramientas oficiales',                       'categories' => array( 'reparaciones-apple', 'apple', 'iphone', 'ipad', 'mac', 'macbook', 'apple-watch' ) ),
        array( 'id' => 'sostenible',    'icon' => '♻️', 'text' => 'Reparación sostenible (prolonga la vida del dispositivo)', 'categories' => array( 'global' ) ),
    );
}

// ─── 2. CATEGORÍAS ────────────────────────────────────────────────────────────

function playsat_get_current_categories() {
    $cats = array();

    if ( is_tax( 'product_cat' ) ) {
        $term = get_queried_object();
        if ( $term ) {
            $cats[] = $term->slug;
            if ( $term->parent ) {
                $parent = get_term( $term->parent, 'product_cat' );
                if ( $parent && ! is_wp_error( $parent ) ) $cats[] = $parent->slug;
            }
        }
    }

    if ( is_product() || is_singular( 'product' ) ) {
        $terms = get_the_terms( get_the_ID(), 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $cats[] = $t->slug;
                if ( $t->parent ) {
                    $p = get_term( $t->parent, 'product_cat' );
                    if ( $p && ! is_wp_error( $p ) ) $cats[] = $p->slug;
                }
            }
        }
    }

    $forced = apply_filters( 'playsat_force_categories', array() );
    if ( ! empty( $forced ) ) $cats = array_merge( $cats, $forced );

    return array_unique( $cats );
}

function playsat_get_active_items() {
    $current = playsat_get_current_categories();
    $active  = array();
    foreach ( playsat_trust_panel_items() as $item ) {
        if ( in_array( 'global', $item['categories'] ) ) { $active[] = $item; continue; }
        foreach ( $item['categories'] as $cat ) {
            if ( in_array( $cat, $current ) ) { $active[] = $item; break; }
        }
    }
    return $active;
}

// ─── 3. HTML PANEL COMPLETO ───────────────────────────────────────────────────

function playsat_render_trust_panel( $force = false ) {
    global $ptp_instance;
    $ptp_instance++;
    $uid = $ptp_instance;

    $items = playsat_get_active_items();
    if ( empty( $items ) ) return;

    $json_items = array();
    foreach ( $items as $i ) $json_items[] = array( 'icon' => $i['icon'], 'text' => $i['text'] );
    $json = wp_json_encode( array_values( $json_items ) );

    $panel_id   = 'ptp-panel-'   . $uid;
    $bar_id     = 'ptp-bar-'     . $uid;
    $rot_id     = 'ptp-rot-'     . $uid;
    $btn_id     = 'ptp-btn-'     . $uid;
    $drawer_id  = 'ptp-drawer-'  . $uid;
    $close_id   = 'ptp-close-'   . $uid;
    $overlay_id = 'ptp-overlay-' . $uid;

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $panel_id ); ?>" class="ptp-widget" role="complementary" aria-label="Ventajas del servicio">
        <div class="ptp-header">
            <span class="ptp-header-icon">🏆</span>
            <span class="ptp-header-title">¿Por qué elegirnos?</span>
        </div>
        <ul class="ptp-list" role="list">
            <?php foreach ( $items as $item ) : ?>
            <li class="ptp-item">
                <span class="ptp-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
                <span class="ptp-text"><?php echo esc_html( $item['text'] ); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div id="<?php echo esc_attr( $bar_id ); ?>" class="ptp-bar" role="complementary">
        <div class="ptb-preview">
            <span id="<?php echo esc_attr( $rot_id ); ?>" class="ptb-rotating-text"></span>
        </div>
        <button class="ptb-toggle" id="<?php echo esc_attr( $btn_id ); ?>" aria-expanded="false">
            Ver ventajas <span class="ptb-arrow">&#9650;</span>
        </button>
    </div>

    <div id="<?php echo esc_attr( $drawer_id ); ?>" class="ptb-drawer" role="dialog" aria-modal="true" hidden>
        <div class="ptb-inner">
            <div class="ptb-drawer-header">
                <span>🏆 ¿Por qué elegirnos?</span>
                <button id="<?php echo esc_attr( $close_id ); ?>" class="ptb-close" aria-label="Cerrar">&#10005;</button>
            </div>
            <ul class="ptb-drawer-list">
                <?php foreach ( $items as $item ) : ?>
                <li>
                    <span aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
                    <?php echo esc_html( $item['text'] ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div id="<?php echo esc_attr( $overlay_id ); ?>" class="ptb-overlay" hidden></div>

    <script>
    (function() {
        var items     = <?php echo $json; ?>;
        var idx       = 0;
        var rotEl     = document.getElementById( '<?php echo esc_js( $rot_id ); ?>' );
        var toggleBtn = document.getElementById( '<?php echo esc_js( $btn_id ); ?>' );
        var closeBtn  = document.getElementById( '<?php echo esc_js( $close_id ); ?>' );
        var drawer    = document.getElementById( '<?php echo esc_js( $drawer_id ); ?>' );
        var overlay   = document.getElementById( '<?php echo esc_js( $overlay_id ); ?>' );

        if ( rotEl && items.length ) {
            rotEl.textContent = items[0].icon + ' ' + items[0].text;
            setInterval( function() {
                rotEl.classList.add( 'fade-out' );
                setTimeout( function() {
                    idx = ( idx + 1 ) % items.length;
                    rotEl.textContent = items[idx].icon + ' ' + items[idx].text;
                    rotEl.classList.remove( 'fade-out' );
                }, 300 );
            }, 5000 );
        }

        function openDrawer() {
            if ( ! drawer || ! overlay ) return;
            drawer.hidden  = false;
            overlay.hidden = false;
            toggleBtn.setAttribute( 'aria-expanded', 'true' );
            toggleBtn.querySelector( '.ptb-arrow' ).innerHTML = '&#9660;';
            document.body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            if ( ! drawer || ! overlay ) return;
            drawer.hidden  = true;
            overlay.hidden = true;
            toggleBtn.setAttribute( 'aria-expanded', 'false' );
            toggleBtn.querySelector( '.ptb-arrow' ).innerHTML = '&#9650;';
            document.body.style.overflow = '';
        }

        if ( toggleBtn ) toggleBtn.addEventListener( 'click', function( e ) { e.stopPropagation(); openDrawer(); } );
        if ( closeBtn )  closeBtn.addEventListener( 'click', closeDrawer );
        if ( overlay )   overlay.addEventListener( 'click', closeDrawer );
        document.addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Escape' && drawer && ! drawer.hidden ) closeDrawer();
        });
    })();
    </script>
    <?php
    echo ob_get_clean();
}

// ─── 4. HTML SOLO BARRA MÓVIL (footer automático) ────────────────────────────

function playsat_render_mobile_bar_only() {
    global $ptp_instance;
    if ( $ptp_instance > 0 ) return;

    $items = playsat_get_active_items();
    if ( empty( $items ) ) return;

    $json_items = array();
    foreach ( $items as $i ) $json_items[] = array( 'icon' => $i['icon'], 'text' => $i['text'] );
    $json = wp_json_encode( array_values( $json_items ) );

    ob_start();
    ?>
    <div id="ptp-bar-auto" class="ptp-bar" role="complementary">
        <div class="ptb-preview">
            <span id="ptp-rot-auto" class="ptb-rotating-text"></span>
        </div>
        <button class="ptb-toggle" id="ptp-btn-auto" aria-expanded="false">
            Ver ventajas <span class="ptb-arrow">&#9650;</span>
        </button>
    </div>

    <div id="ptp-drawer-auto" class="ptb-drawer" role="dialog" aria-modal="true" hidden>
        <div class="ptb-inner">
            <div class="ptb-drawer-header">
                <span>🏆 ¿Por qué elegirnos?</span>
                <button id="ptp-close-auto" class="ptb-close" aria-label="Cerrar">&#10005;</button>
            </div>
            <ul class="ptb-drawer-list">
                <?php foreach ( $items as $item ) : ?>
                <li>
                    <span aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
                    <?php echo esc_html( $item['text'] ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div id="ptp-overlay-auto" class="ptb-overlay" hidden></div>

    <script>
    (function() {
        var items     = <?php echo $json; ?>;
        var idx       = 0;
        var rotEl     = document.getElementById( 'ptp-rot-auto' );
        var toggleBtn = document.getElementById( 'ptp-btn-auto' );
        var closeBtn  = document.getElementById( 'ptp-close-auto' );
        var drawer    = document.getElementById( 'ptp-drawer-auto' );
        var overlay   = document.getElementById( 'ptp-overlay-auto' );

        if ( rotEl && items.length ) {
            rotEl.textContent = items[0].icon + ' ' + items[0].text;
            setInterval( function() {
                rotEl.classList.add( 'fade-out' );
                setTimeout( function() {
                    idx = ( idx + 1 ) % items.length;
                    rotEl.textContent = items[idx].icon + ' ' + items[idx].text;
                    rotEl.classList.remove( 'fade-out' );
                }, 300 );
            }, 5000 );
        }

        function openDrawer() {
            if ( ! drawer || ! overlay ) return;
            drawer.hidden  = false;
            overlay.hidden = false;
            toggleBtn.setAttribute( 'aria-expanded', 'true' );
            toggleBtn.querySelector( '.ptb-arrow' ).innerHTML = '&#9660;';
            document.body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            if ( ! drawer || ! overlay ) return;
            drawer.hidden  = true;
            overlay.hidden = true;
            toggleBtn.setAttribute( 'aria-expanded', 'false' );
            toggleBtn.querySelector( '.ptb-arrow' ).innerHTML = '&#9650;';
            document.body.style.overflow = '';
        }

        if ( toggleBtn ) toggleBtn.addEventListener( 'click', function( e ) { e.stopPropagation(); openDrawer(); } );
        if ( closeBtn )  closeBtn.addEventListener( 'click', closeDrawer );
        if ( overlay )   overlay.addEventListener( 'click', closeDrawer );
        document.addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Escape' && drawer && ! drawer.hidden ) closeDrawer();
        });
    })();
    </script>
    <?php
    echo ob_get_clean();
}

// ─── 5. CSS ───────────────────────────────────────────────────────────────────

function playsat_trust_panel_styles() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style id="playsat-trust-panel-css">

    .ptp-widget, .ptp-bar, .ptb-drawer {
        --ptp-bg:     #ffffff;
        --ptp-border: #e8e8e8;
        --ptp-dark:   #1a1a1a;
        --ptp-gold:   #f5a623;
        --ptp-text:   #2c2c2c;
        --ptp-muted:  #6b6b6b;
        --ptp-radius: 12px;
        --ptp-shadow: 0 4px 24px rgba(0,0,0,0.10);
        font-family:  'Segoe UI', system-ui, sans-serif;
        box-sizing:   border-box;
    }

    /* ── PANEL DESKTOP (usado via shortcode en columna derecha de Elementor) ── */
    .ptp-widget {
        background:    var(--ptp-bg);
        border:        1.5px solid var(--ptp-border);
        border-radius: var(--ptp-radius);
        box-shadow:    var(--ptp-shadow);
        padding:       20px;
        width:         100%;
        position:      sticky;
        top:           100px;
        align-self:    flex-start;
    }

    .ptp-header {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 16px; padding-bottom: 12px;
        border-bottom: 2px solid var(--ptp-gold);
    }
    .ptp-header-icon  { font-size: 1.3rem; }
    .ptp-header-title {
        font-size: 0.85rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.06em;
        color: var(--ptp-dark);
    }

    .ptp-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .ptp-item {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 8px 10px; border-radius: 8px; transition: background 0.2s;
        opacity: 0; transform: translateX(10px); animation: ptpIn 0.3s forwards;
    }
    .ptp-item:hover { background: #f8f8f8; }
    .ptp-item:nth-child(1) { animation-delay: .05s }
    .ptp-item:nth-child(2) { animation-delay: .10s }
    .ptp-item:nth-child(3) { animation-delay: .15s }
    .ptp-item:nth-child(4) { animation-delay: .20s }
    .ptp-item:nth-child(5) { animation-delay: .25s }
    .ptp-item:nth-child(6) { animation-delay: .30s }
    .ptp-item:nth-child(7) { animation-delay: .35s }
    .ptp-item:nth-child(8) { animation-delay: .40s }
    @keyframes ptpIn { to { opacity: 1; transform: translateX(0); } }
    .ptp-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
    .ptp-text { font-size: 0.82rem; line-height: 1.45; color: var(--ptp-text); }

    /* ── BARRA MÓVIL ── */
    .ptp-bar {
        display:     none;
        position:    fixed;
        bottom: 0; left: 0; right: 0;
        z-index:     9990;
        background:  var(--ptp-dark);
        color:       #fff;
        padding:     14px 20px;
        min-height:  90px;
        align-items: center;
        gap:         12px;
        box-shadow:  0 -2px 12px rgba(0,0,0,0.20);
    }
    .ptb-preview { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.92rem; line-height: 1.4; }
    .ptb-rotating-text { display: inline-block; transition: opacity 0.3s; }
    .ptb-rotating-text.fade-out { opacity: 0; }
    .ptb-toggle {
        background: var(--ptp-gold); color: #fff; border: none;
        border-radius: 24px; padding: 10px 18px;
        font-size: 0.85rem; font-weight: 700;
        cursor: pointer; white-space: nowrap;
        display: flex; align-items: center; gap: 5px;
        transition: opacity 0.2s, transform 0.2s;
    }
    .ptb-toggle:hover { opacity: .85; transform: scale(1.03); }
    .ptb-arrow { font-size: 0.65rem; }

    /* ── DRAWER ── */
    .ptb-drawer {
        position: fixed; bottom: 0; left: 0; right: 0;
        z-index: 9995; background: var(--ptp-bg);
        border-radius: 16px 16px 0 0;
        box-shadow: 0 -4px 30px rgba(0,0,0,0.18);
        max-height: 70vh; overflow-y: auto;
        animation: ptbUp 0.3s ease;
    }
    .ptb-drawer[hidden] { display: none; }
    @keyframes ptbUp { from { transform: translateY(100%) } to { transform: translateY(0) } }
    .ptb-inner { padding: 20px 20px 32px; }
    .ptb-drawer-header {
        display: flex; justify-content: space-between; align-items: center;
        font-weight: 700; font-size: 1rem; color: var(--ptp-dark);
        margin-bottom: 16px; padding-bottom: 12px;
        border-bottom: 2px solid var(--ptp-gold);
    }
    .ptb-close {
        background: none; border: none; font-size: 1.1rem;
        cursor: pointer; color: var(--ptp-muted);
        padding: 4px 8px; border-radius: 50%; transition: background 0.2s;
    }
    .ptb-close:hover { background: #f0f0f0; }
    .ptb-drawer-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .ptb-drawer-list li {
        display: flex; align-items: center; gap: 12px;
        font-size: 0.9rem; color: var(--ptp-text);
        padding: 6px 0; border-bottom: 1px solid #f2f2f2;
    }
    .ptb-drawer-list li:last-child { border-bottom: none; }
    .ptb-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.45); z-index: 9993;
        backdrop-filter: blur(2px);
    }
    .ptb-overlay[hidden] { display: none; }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
        .ptp-widget { display: none !important; }
        .ptp-bar    { display: flex !important; }
        body        { padding-bottom: 90px !important; }
        .arcontactus-widget,
        .arcontactus-message,
        [id*="arcontactus"],
        [class*="arcontactus"] { bottom: 100px !important; }
    }

    @media (min-width: 769px) {
        .ptp-bar, .ptb-drawer, .ptb-overlay { display: none !important; }
    }

    </style>
    <?php
}

// ─── 6. SHORTCODE ─────────────────────────────────────────────────────────────

function playsat_trust_panel_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'cats' => '' ), $atts, 'playsat_trust_panel' );
    if ( ! empty( $atts['cats'] ) ) {
        add_filter( 'playsat_force_categories', function() use ( $atts ) {
            return array_map( 'trim', explode( ',', $atts['cats'] ) );
        });
    }
    ob_start();
    playsat_render_trust_panel( true );
    return ob_get_clean();
}
add_shortcode( 'playsat_trust_panel', 'playsat_trust_panel_shortcode' );

// ─── 7. HOOKS ─────────────────────────────────────────────────────────────────

// CSS en todas las páginas
add_action( 'wp_head', 'playsat_trust_panel_styles', 20 );

// Barra móvil automática en footer (todas las páginas, sin duplicar si hay shortcode)
add_action( 'wp_footer', 'playsat_render_mobile_bar_only', 20 );
