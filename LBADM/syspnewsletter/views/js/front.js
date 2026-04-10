/**
 * SYSPROVIDER Newsletter Popup — Frontend JS (v1.2)
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

(function () {
  'use strict';

  var COOKIE_NAME = 'syspnl_shown';

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + encodeURIComponent(value) +
                      ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }
  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }
  function shouldShow(frequency, freqVal) {
    var cookie = getCookie(COOKIE_NAME);
    if (frequency === 'always') return true;
    if (frequency === 'once')   return !cookie;
    if (frequency === 'days' && cookie) {
      var daysSince = (Date.now() - parseInt(cookie, 10)) / 86400000;
      return daysSince >= parseInt(freqVal, 10);
    }
    return true;
  }
  function markShown(frequency, freqVal) {
    if (frequency === 'once') setCookie(COOKIE_NAME, '1', 3650);
    if (frequency === 'days') setCookie(COOKIE_NAME, String(Date.now()), parseInt(freqVal, 10) + 1);
  }

  document.addEventListener('DOMContentLoaded', function () {

    var overlay     = document.getElementById('syspnl-overlay');
    if (!overlay) return;

    var closeBtn    = document.getElementById('syspnl-close');
    var skipBtn     = document.getElementById('syspnl-skip');
    var submitBtn   = document.getElementById('syspnl-submit');
    var emailInput  = document.getElementById('syspnl-email');
    var errorEl     = document.getElementById('syspnl-error');
    var stepForm    = document.getElementById('syspnl-step-form');
    var stepSuccess = document.getElementById('syspnl-step-success');
    var copyBtn     = document.getElementById('syspnl-copy-btn');
    var copiedMsg   = document.getElementById('syspnl-copied-msg');

    var frequency = overlay.getAttribute('data-frequency') || 'once';
    var freqVal   = overlay.getAttribute('data-frequency-val') || '7';
    var delay     = parseInt(overlay.getAttribute('data-delay'), 10);
    if (isNaN(delay) || delay < 0) delay = 0;
    var ajaxUrl   = overlay.getAttribute('data-ajax') || '';

    if (!shouldShow(frequency, freqVal)) return;

    // ── Abrir / cerrar ──────────────────────────────────────────────────────
    function openPopup() {
      overlay.classList.add('syspnl-visible');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      markShown(frequency, freqVal);
      setTimeout(function () { if (emailInput) emailInput.focus(); }, 400);
    }
    function closePopup() {
      overlay.classList.remove('syspnl-visible');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    // ── Validación ──────────────────────────────────────────────────────────
    function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e); }
    function showError(msg) {
      if (errorEl)    errorEl.textContent = msg;
      if (emailInput) emailInput.classList.add('syspnl-input-error');
    }
    function clearError() {
      if (errorEl)    errorEl.textContent = '';
      if (emailInput) emailInput.classList.remove('syspnl-input-error');
    }

    // ── Envío AJAX ──────────────────────────────────────────────────────────
    function doSubmit() {
      if (!submitBtn || submitBtn.disabled) return;

      var email = emailInput ? emailInput.value.trim() : '';
      clearError();

      if (!email)              { showError('Por favor, introduce tu email.'); return; }
      if (!isValidEmail(email)){ showError('El formato del email no es válido.'); return; }

      submitBtn.disabled = true;
      submitBtn.classList.add('syspnl-loading');

      var body = 'email=' + encodeURIComponent(email) + '&ajax=1';

      // Construimos la URL manualmente asegurándonos de que lleva los parámetros correctos
      var url = ajaxUrl;
      // Si la URL ya tiene ? (URL amigable con query string), añadir &ajax=1 aparte
      // Si la URL es limpia, los parámetros van solo en el body POST

      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body
      })
      .then(function (response) {
        return response.text().then(function(text) {
          return { status: response.status, text: text };
        });
      })
      .then(function (result) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('syspnl-loading');

        var text = result.text.trim();

        // PrestaShop a veces añade output antes del JSON — extraemos el objeto JSON
        var jsonStart = text.indexOf('{');
        var jsonEnd   = text.lastIndexOf('}');
        if (jsonStart === -1 || jsonEnd === -1) {
          // No hay JSON en la respuesta — PS devolvió HTML de error
          console.error('[SyspNewsletter] Respuesta no-JSON:', text.substring(0, 300));
          showError('Error del servidor. Consulta la consola para más detalles.');
          return;
        }

        var jsonStr = text.substring(jsonStart, jsonEnd + 1);
        var data;
        try {
          data = JSON.parse(jsonStr);
        } catch (e) {
          console.error('[SyspNewsletter] JSON inválido:', jsonStr);
          showError('Respuesta inesperada del servidor.');
          return;
        }

        if (data.success) {
          if (stepForm)    stepForm.style.display    = 'none';
          if (stepSuccess) stepSuccess.style.display = 'block';

          var couponBlock = document.getElementById('syspnl-coupon-block');
          var couponCode  = document.getElementById('syspnl-coupon-code');
          var couponMsg   = document.getElementById('syspnl-coupon-msg');

          if (data.discount_code && couponBlock && couponCode) {
            couponCode.textContent = data.discount_code;
            if (couponMsg) couponMsg.textContent = data.discount_msg || '';
            couponBlock.style.display = 'block';
          }
        } else {
          showError(data.error || 'Error al procesar la solicitud.');
        }
      })
      .catch(function (err) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('syspnl-loading');
        console.error('[SyspNewsletter] Fetch error:', err);
        // Fallback: intentar con XMLHttpRequest (por si fetch está bloqueado)
        doSubmitXHR(email);
      });
    }

    // ── Fallback XHR ───────────────────────────────────────────────────────
    function doSubmitXHR(email) {
      submitBtn.disabled = true;
      submitBtn.classList.add('syspnl-loading');

      var xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        submitBtn.disabled = false;
        submitBtn.classList.remove('syspnl-loading');

        var text = (xhr.responseText || '').trim();
        var jsonStart = text.indexOf('{');
        var jsonEnd   = text.lastIndexOf('}');

        if (jsonStart === -1 || jsonEnd === -1) {
          showError('Error de conexión. Inténtalo de nuevo.');
          console.error('[SyspNewsletter] XHR sin JSON:', text.substring(0, 300));
          return;
        }
        try {
          var data = JSON.parse(text.substring(jsonStart, jsonEnd + 1));
          if (data.success) {
            if (stepForm)    stepForm.style.display    = 'none';
            if (stepSuccess) stepSuccess.style.display = 'block';
            var cb = document.getElementById('syspnl-coupon-block');
            var cc = document.getElementById('syspnl-coupon-code');
            var cm = document.getElementById('syspnl-coupon-msg');
            if (data.discount_code && cb && cc) {
              cc.textContent = data.discount_code;
              if (cm) cm.textContent = data.discount_msg || '';
              cb.style.display = 'block';
            }
          } else {
            showError(data.error || 'Error al procesar.');
          }
        } catch(e) { showError('Respuesta inesperada.'); }
      };
      xhr.send('email=' + encodeURIComponent(email) + '&ajax=1');
    }

    // ── Copiar cupón ───────────────────────────────────────────────────────
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var codeEl = document.getElementById('syspnl-coupon-code');
        if (!codeEl) return;
        var text = codeEl.textContent.trim();
        var doShow = function () {
          if (copiedMsg) {
            copiedMsg.classList.add('syspnl-show');
            setTimeout(function () { copiedMsg.classList.remove('syspnl-show'); }, 2500);
          }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(doShow).catch(function(){ fallbackCopy(text); doShow(); });
        } else { fallbackCopy(text); doShow(); }
      });
    }
    function fallbackCopy(text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
      document.body.appendChild(ta);
      ta.focus(); ta.select();
      try { document.execCommand('copy'); } catch(e) {}
      document.body.removeChild(ta);
    }

    // ── Listeners ──────────────────────────────────────────────────────────
    if (closeBtn)  closeBtn.addEventListener('click', closePopup);
    if (skipBtn)   skipBtn.addEventListener('click',  closePopup);
    if (submitBtn) submitBtn.addEventListener('click', doSubmit);
    if (emailInput) {
      emailInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doSubmit(); }
      });
    }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closePopup(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('syspnl-visible')) closePopup();
    });
    var sc = stepSuccess && stepSuccess.querySelector('.syspnl-btn-close-success');
    if (sc) sc.addEventListener('click', closePopup);

    // ── Lanzar ─────────────────────────────────────────────────────────────
    setTimeout(openPopup, delay * 1000);

  });

})();
