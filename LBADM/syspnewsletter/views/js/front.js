/**
 * SYSPROVIDER Newsletter Popup — Frontend JS
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

(function () {
  'use strict';

  var COOKIE_NAME = 'syspnl_shown';

  // ── Cookie helpers ──────────────────────────────────────────────────────

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/';
  }

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  // ── Should we show? ─────────────────────────────────────────────────────

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
    if (frequency === 'once')  setCookie(COOKIE_NAME, '1', 3650);
    if (frequency === 'days')  setCookie(COOKIE_NAME, Date.now().toString(), parseInt(freqVal, 10) + 1);
  }

  // ── Main ────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {

    var overlay   = document.getElementById('syspnl-overlay');
    if (!overlay) return;

    var card      = document.getElementById('syspnl-card');
    var closeBtn  = document.getElementById('syspnl-close');
    var skipBtn   = document.getElementById('syspnl-skip');
    var submitBtn = document.getElementById('syspnl-submit');
    var emailInput= document.getElementById('syspnl-email');
    var errorEl   = document.getElementById('syspnl-error');
    var stepForm  = document.getElementById('syspnl-step-form');
    var stepSuccess = document.getElementById('syspnl-step-success');
    var copyBtn   = document.getElementById('syspnl-copy-btn');
    var copiedMsg = document.getElementById('syspnl-copied-msg');

    var frequency = overlay.getAttribute('data-frequency');
    var freqVal   = overlay.getAttribute('data-frequency-val');
    var delay     = parseInt(overlay.getAttribute('data-delay'), 10) || 0;
    var ajaxUrl   = overlay.getAttribute('data-ajax');

    if (!shouldShow(frequency, freqVal)) return;

    // ── Abrir ──
    function openPopup() {
      overlay.classList.add('syspnl-visible');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      markShown(frequency, freqVal);
      setTimeout(function () { emailInput && emailInput.focus(); }, 350);
    }

    // ── Cerrar ──
    function closePopup() {
      overlay.classList.remove('syspnl-visible');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    // ── Validar email ──
    function isValidEmail(e) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    }

    function showError(msg) {
      errorEl.textContent = msg;
      emailInput.classList.add('syspnl-input-error');
    }
    function clearError() {
      errorEl.textContent = '';
      emailInput.classList.remove('syspnl-input-error');
    }

    // ── Enviar ──
    function doSubmit() {
      var email = emailInput.value.trim();
      clearError();

      if (!email) {
        showError('Por favor, introduce tu email.');
        return;
      }
      if (!isValidEmail(email)) {
        showError('El formato del email no es válido.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.classList.add('syspnl-loading');

      var formData = new FormData();
      formData.append('email', email);
      formData.append('ajax', '1');

      fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('syspnl-loading');

          if (data.success) {
            // Mostrar paso éxito
            stepForm.style.display = 'none';
            stepSuccess.style.display = 'block';

            // Cupón
            var couponBlock = document.getElementById('syspnl-coupon-block');
            var couponCode  = document.getElementById('syspnl-coupon-code');
            var couponMsg   = document.getElementById('syspnl-coupon-msg');

            if (data.discount_code && couponBlock) {
              couponCode.textContent = data.discount_code;
              couponMsg.textContent  = data.discount_msg || '';
              couponBlock.style.display = 'block';
            }
          } else {
            showError(data.error || 'Error al procesar la solicitud.');
          }
        })
        .catch(function () {
          submitBtn.disabled = false;
          submitBtn.classList.remove('syspnl-loading');
          showError('Error de conexión. Inténtalo de nuevo.');
        });
    }

    // ── Copiar cupón ──
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var code = document.getElementById('syspnl-coupon-code');
        if (code && navigator.clipboard) {
          navigator.clipboard.writeText(code.textContent).then(function () {
            if (copiedMsg) {
              copiedMsg.classList.add('syspnl-show');
              setTimeout(function () { copiedMsg.classList.remove('syspnl-show'); }, 2000);
            }
          });
        } else if (code) {
          // Fallback
          var ta = document.createElement('textarea');
          ta.value = code.textContent;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          if (copiedMsg) {
            copiedMsg.classList.add('syspnl-show');
            setTimeout(function () { copiedMsg.classList.remove('syspnl-show'); }, 2000);
          }
        }
      });
    }

    // ── Listeners ──
    closeBtn && closeBtn.addEventListener('click', closePopup);
    skipBtn  && skipBtn.addEventListener('click',  closePopup);

    submitBtn && submitBtn.addEventListener('click', doSubmit);
    emailInput && emailInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') doSubmit();
    });

    // Cerrar al pulsar fuera de la card
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closePopup();
    });

    // Cerrar con ESC
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('syspnl-visible')) {
        closePopup();
      }
    });

    // Cerrar al pulsar el botón de éxito
    var successClose = stepSuccess && stepSuccess.querySelector('.syspnl-btn-close-success');
    if (successClose) {
      successClose.addEventListener('click', closePopup);
    }

    // ── Lanzar con delay ──
    setTimeout(openPopup, delay * 1000);

  });

})();
