
/*
 * Custom code goes here.
 * A template should always ship with an empty custom.js
 */

// FUNCIÓN DE INYECCIÓN CSS PARA CARD DE ENVIO GRATIS
(function () {

    // ── Umbral de euros para envío gratis ──────────────────────────────────────
    var THRESHOLD = 100;
  
  
    // ── parsePrice ─────────────────────────────────────────────────────────────
    // Recibe un elemento del DOM y extrae su valor numérico.
    // Elimina todo excepto dígitos, comas y puntos, luego convierte la coma
    // decimal española (56,90) a punto flotante inglés (56.90).
    // Devuelve null si el elemento no existe o el resultado no es un número.
    function parsePrice(el) {
      if (!el) return null;
      var raw = el.textContent.replace(/[^\d,\.]/g, '').replace(',', '.');
      var val = parseFloat(raw);
      return isNaN(val) ? null : val;
    }
  
  
    // ── buildNotice ─────────────────────────────────────────────────────────────
    // Construye y devuelve un <div> con el mensaje del aviso.
    // - Si total >= THRESHOLD: mensaje de envío gratis conseguido (fondo verde)
    // - Si total < THRESHOLD: mensaje con los euros que faltan (fondo amarillo)
    // extraClass es opcional — se usa para la versión compacta del mini-cart
    // (.lbadm-minicart-shipping), que comparte estilos base con .free-shipping-notice
    function buildNotice(total, extraClass) {
      var div = document.createElement('div');
      div.className = 'free-shipping-notice' + (extraClass ? ' ' + extraClass : '');
      if (total >= THRESHOLD) {
        div.classList.add('free-shipping-achieved');
        div.innerHTML = '🎉 ¡Tienes envío gratis!';
      } else {
        var remaining = (THRESHOLD - total).toFixed(2).replace('.', ',');
        div.innerHTML = '🚚 ¡Te faltan <strong>' + remaining + '€</strong> para envío gratis!';
      }
      return div;
    }
  
  
    // ── updateFullCart ──────────────────────────────────────────────────────────
    // Actualiza el aviso en la página del carrito completo (/carrito).
    // Busca el contenedor principal de totales (.cart-detailed-totals) y dentro
    // de él el subtotal de productos (#cart-subtotal-products .value).
    // IMPORTANTE: se usa el subtotal de productos, NO el total final, para que
    // el transporte no compute en el cálculo del umbral.
    // El aviso se inserta como primer hijo del contenedor, encima de las líneas
    // de subtotal/transporte/total.
    function updateFullCart() {
      var container = document.querySelector('.cart-detailed-totals');
      if (!container) return; // no estamos en la página del carrito, salir
  
      var priceEl = container.querySelector('#cart-subtotal-products .value');
      var total = parsePrice(priceEl);
      if (total === null) return; // precio no encontrado, salir
  
      // Eliminar aviso anterior si existe (evita duplicados al actualizar)
      var existing = container.querySelector('.free-shipping-notice');
      if (existing) existing.remove();
  
      container.insertBefore(buildNotice(total), container.firstChild);
    }
  
  
    // ── updateMiniCart ──────────────────────────────────────────────────────────
    // Actualiza el aviso en el mini-cart lateral de Elementor.
    // El contenedor es .elementor-cart__summary y el primer
    // .elementor-cart__summary-value que contiene es el subtotal de productos.
    // El aviso se inserta como primer hijo del summary, antes de las líneas
    // de artículos/transporte/total.
    function updateMiniCart() {
      var summary = document.querySelector('.elementor-cart__summary');
      if (!summary) return; // mini-cart no presente en esta página, salir
  
      // Primer valor = subtotal productos (los siguientes son transporte y total)
      var priceEl = summary.querySelector('.elementor-cart__summary-value');
      var total = parsePrice(priceEl);
      if (total === null) return;
  
      var existing = summary.querySelector('.free-shipping-notice');
      if (existing) existing.remove();
  
      // Se pasa 'lbadm-minicart-shipping' como extraClass para aplicar
      // los estilos compactos definidos en custom.css
      summary.insertBefore(buildNotice(total, 'lbadm-minicart-shipping'), summary.firstChild);
    }
  
  
    // ── updateShippingNotices ───────────────────────────────────────────────────
    // Función principal que llama a ambas actualizaciones a la vez.
    // Es el único punto de entrada para refrescar los avisos, tanto en la
    // carga inicial como tras cada cambio AJAX del carrito.
    function updateShippingNotices() {
      updateFullCart();
      updateMiniCart();
    }
  
  
    // ── Init ────────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
  
      // Ejecución inicial al cargar la página
      updateShippingNotices();
  
      // Escuchar las llamadas AJAX que PrestaShop hace al modificar el carrito.
      // Se filtra por URL para no reaccionar a todas las peticiones de la página
      // (PayPal, analytics, etc.) sino solo a las del carrito:
      //   - action=refresh  → PrestaShop refresca el HTML del carrito completo
      //   - carrito?add=    → cliente añade un producto (desde listado o ficha)
      //   - carrito?update= → cliente cambia la cantidad en el carrito
      //   - carrito?delete= → cliente elimina un producto del carrito
      // El setTimeout de 200ms da tiempo a que PrestaShop actualice el DOM
      // antes de que nuestro script lea los nuevos precios.
      jQuery(document).ajaxSuccess(function (event, xhr, settings) {
        if (settings.url && (
          settings.url.indexOf('action=refresh') !== -1 ||
          settings.url.indexOf('carrito?add=')    !== -1 ||
          settings.url.indexOf('carrito?update=') !== -1 ||
          settings.url.indexOf('carrito?delete=') !== -1
        )) {
          setTimeout(updateShippingNotices, 200);
        }
      });
  
    });
  
  })();