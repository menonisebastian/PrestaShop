<?php
/**
 * Plugin Name: PlaySat Artículos Similares
 * Description: Muestra artículos relacionados basados en la categoría del post actual. Uso: [playsat_relacionados layout="grid" count="3"] o layout="list" para barra lateral. También se inyecta automáticamente al final de los posts si no se usa shortcode.
 * Version: 1.0.0
 * Author: Sebastián/SysProviders
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── 1. LÓGICA DE OBTENCIÓN DE POSTS ──────────────────────────────────────────

function playsat_get_related_posts( $count = 3 ) {
    global $post;
    
    // Si no estamos en un post, o no hay post, devolvemos vacío
    if ( empty( $post ) ) return false;

    // Obtenemos las categorías del post actual
    $categories = wp_get_post_categories( $post->ID );
    
    if ( empty( $categories ) ) return false;

    $args = array(
        'category__in'        => $categories,
        'post__not_in'        => array( $post->ID ), // Excluir el post actual
        'posts_per_page'      => $count,
        'ignore_sticky_posts' => 1,
        'orderby'             => 'rand' // Aleatorio para rotar el enlazado interno
    );

    $related_query = new WP_Query( $args );

    return $related_query;
}

// ─── 2. RENDER HTML (SHORTCODE Y WIDGET) ──────────────────────────────────────

function playsat_render_related_posts( $layout = 'grid', $count = 3, $title = 'También te puede interesar...' ) {
    $related = playsat_get_related_posts( $count );

    if ( ! $related || ! $related->have_posts() ) {
        return ''; // Si no hay relacionados, no mostramos nada
    }

    $wrap_class = 'psr-wrap psr-layout-' . esc_attr( $layout );

    ob_start();
    ?>
    <div class="<?php echo $wrap_class; ?>">
        <?php if ( ! empty( $title ) ) : ?>
            <h3 class="psr-main-title">🔥 <?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <div class="psr-container">
            <?php while ( $related->have_posts() ) : $related->the_post(); ?>
                
                <article class="psr-card">
                    <a href="<?php the_permalink(); ?>" class="psr-thumb-link" aria-label="<?php the_title_attribute(); ?>">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'medium', array( 'class' => 'psr-thumb' ) ); ?>
                        <?php else : ?>
                            <!-- Fallback si no hay imagen -->
                            <div class="psr-thumb psr-no-thumb">
                                <span>PlaySat</span>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <div class="psr-content">
                        <h4 class="psr-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h4>
                        <?php if ( $layout === 'grid' ) : ?>
                            <a href="<?php the_permalink(); ?>" class="psr-read-more">Leer artículo <span class="psr-arrow">&rarr;</span></a>
                        <?php endif; ?>
                    </div>
                </article>

            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ─── 3. REGISTRO DE SHORTCODE ─────────────────────────────────────────────────

function playsat_related_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'layout' => 'grid', // 'grid' o 'list'
        'count'  => 3,
        'title'  => 'También te puede interesar...'
    ), $atts, 'playsat_relacionados' );

    return playsat_render_related_posts( $atts['layout'], intval( $atts['count'] ), $atts['title'] );
}
add_shortcode( 'playsat_relacionados', 'playsat_related_shortcode' );

// ─── 4. INYECCIÓN AUTOMÁTICA AL FINAL DEL CONTENIDO (OPCIONAL) ────────────────
// Si usan Elementor Theme Builder, es mejor usar el shortcode, pero esto asegura
// que aparezca en el blog clásico si se olvidan de ponerlo.

function playsat_auto_append_related( $content ) {
    // Solo inyectar en posts individuales, en el loop principal, y si no estamos editando en Elementor
    if ( is_singular( 'post' ) && in_the_loop() && is_main_query() && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
        // Para evitar duplicados si ya usaron el shortcode manualmente en el texto
        if ( ! has_shortcode( $content, 'playsat_relacionados' ) ) {
            $related_html = playsat_render_related_posts( 'grid', 3, 'También te puede interesar...' );
            $content .= $related_html;
        }
    }
    return $content;
}
add_filter( 'the_content', 'playsat_auto_append_related', 99 );


// ─── 5. ESTILOS CSS (INLINE HEAD) ─────────────────────────────────────────────

function playsat_related_posts_styles() {
    // Solo cargamos el CSS si estamos en un single post (para optimizar)
    if ( ! is_singular('post') ) return;

    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style id="playsat-related-posts-css">
    
    .psr-wrap {
        --psr-bg:      #ffffff;
        --psr-border:  #e8e8e8;
        --psr-dark:    #1a1a1a;
        --psr-gold:    #f5a623;
        --psr-text:    #555555;
        --psr-radius:  12px;
        --psr-shadow:  0 4px 20px rgba(0,0,0,0.06);
        
        margin: 40px 0;
        font-family: 'Segoe UI', system-ui, sans-serif;
        box-sizing: border-box;
        clear: both;
    }

    .psr-wrap * { box-sizing: border-box; }

    .psr-main-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--psr-dark);
        margin-bottom: 24px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--psr-gold);
        display: inline-block;
    }

    /* ── LAYOUT: GRID (Final del artículo) ── */
    .psr-layout-grid .psr-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 24px;
    }

    .psr-layout-grid .psr-card {
        background: var(--psr-bg);
        border: 1px solid var(--psr-border);
        border-radius: var(--psr-radius);
        overflow: hidden;
        box-shadow: var(--psr-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .psr-layout-grid .psr-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        border-color: var(--psr-gold);
    }

    .psr-layout-grid .psr-thumb-link { display: block; overflow: hidden; }
    .psr-layout-grid .psr-thumb {
        width: 100%;
        height: 180px;
        object-fit: cover;
        transition: transform 0.5s ease;
        display: block;
    }
    .psr-layout-grid .psr-card:hover .psr-thumb { transform: scale(1.05); }

    .psr-layout-grid .psr-content {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .psr-layout-grid .psr-title {
        font-size: 1.1rem;
        line-height: 1.4;
        margin: 0 0 15px 0;
        font-weight: 600;
    }
    
    .psr-layout-grid .psr-title a {
        color: var(--psr-dark);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .psr-layout-grid .psr-title a:hover { color: var(--psr-gold); }

    .psr-layout-grid .psr-read-more {
        margin-top: auto;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--psr-gold);
        text-transform: uppercase;
        text-decoration: none;
        letter-spacing: 0.5px;
    }
    .psr-layout-grid .psr-arrow { transition: transform 0.2s; display: inline-block; }
    .psr-layout-grid .psr-read-more:hover .psr-arrow { transform: translateX(5px); }


    /* ── LAYOUT: LIST (Barra lateral / Sidebar) ── */
    .psr-layout-list .psr-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .psr-layout-list .psr-card {
        display: flex;
        align-items: center;
        gap: 16px;
        border-bottom: 1px solid var(--psr-border);
        padding-bottom: 16px;
        transition: background 0.2s;
    }
    
    .psr-layout-list .psr-card:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .psr-layout-list .psr-card:hover { background: #fafafa; border-radius: 8px; }

    .psr-layout-list .psr-thumb-link { flex-shrink: 0; }
    .psr-layout-list .psr-thumb {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }

    .psr-layout-list .psr-content { padding: 0; }
    .psr-layout-list .psr-title {
        font-size: 0.95rem;
        line-height: 1.3;
        margin: 0;
        font-weight: 600;
    }
    .psr-layout-list .psr-title a {
        color: var(--psr-dark);
        text-decoration: none;
        transition: color 0.2s;
    }
    .psr-layout-list .psr-title a:hover { color: var(--psr-gold); }


    /* ── FALLBACK NO-IMAGE ── */
    .psr-no-thumb {
        background: linear-gradient(135deg, #2b2b2b, #1a1a1a);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--psr-gold);
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
        /* En móvil, la grid pasa a 1 columna o 2 pequeñas automáticamente por el auto-fit */
        .psr-wrap { margin: 30px 0; }
        .psr-layout-list .psr-thumb { width: 70px; height: 70px; }
    }

    </style>
    <?php
}
add_action( 'wp_head', 'playsat_related_posts_styles', 20 );