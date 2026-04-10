/**
 * SYSPROVIDER Newsletter Popup — Backoffice JS
 * Live preview del popup en el formulario de configuración.
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

document.addEventListener('DOMContentLoaded', function () {

  // ── Crear bloque de preview ──────────────────────────────────────────

  var firstPanel = document.querySelector('.panel');
  if (firstPanel) {
    var previewHtml =
      '<div id="syspnl-preview-wrap">' +
      '<h4>👁 Vista previa en tiempo real</h4>' +
      '<div id="syspnl-preview-overlay">' +
      '<div id="syspnl-preview-card">' +

      '<div id="syspnl-preview-step-form">' +
      '<div id="syspnl-preview-discount-teaser" style="display:none;">¡Al suscribirte, recibirás un cupón de descuento! 🎁</div>' +
      '<div id="syspnl-preview-title">Título</div>' +
      '<div id="syspnl-preview-subtitle">Subtítulo del popup</div>' +
      '<input id="syspnl-preview-input" type="text" placeholder="Tu correo electrónico" readonly>' +
      '<div id="syspnl-preview-btn">Suscribirme</div>' +
      '<div id="syspnl-preview-skip">No gracias</div>' +
      '</div>' +

      '<div id="syspnl-preview-step-success" style="display:none;">' +
      '<div id="syspnl-preview-success-icon">✅</div>' +
      '<div id="syspnl-preview-success-msg">¡Gracias por suscribirte! 🎉</div>' +
      '<div id="syspnl-preview-coupon-block" style="display:none;">' +
      '<div id="syspnl-preview-coupon-msg">¡Usa este código en tu próxima compra!</div>' +
      '<div id="syspnl-preview-coupon-wrap">' +
      '<span id="syspnl-preview-coupon-code">NL-XXXXXXXX</span>' +
      '<button id="syspnl-preview-copy-btn">Copiar</button>' +
      '</div>' +
      '</div>' +
      '<div id="syspnl-preview-continue-btn">Continuar comprando</div>' +
      '</div>' +

      '</div>' +
      '</div>' +

      '<div id="syspnl-preview-toggle-btns">' +
      '<button id="syspnl-toggle-form" class="active">👁 Ver formulario</button>' +
      '<button id="syspnl-toggle-success">👁 Ver pantalla de éxito</button>' +
      '</div>' +

      '</div>';
    firstPanel.insertAdjacentHTML('afterbegin', previewHtml);
  }

  // ── Selectores de preview ────────────────────────────────────────────

  var overlay = document.getElementById('syspnl-preview-overlay');
  var card = document.getElementById('syspnl-preview-card');
  var title = document.getElementById('syspnl-preview-title');
  var subtitle = document.getElementById('syspnl-preview-subtitle');
  var input = document.getElementById('syspnl-preview-input');
  var btn = document.getElementById('syspnl-preview-btn');
  var teaser = document.getElementById('syspnl-preview-discount-teaser');
  var stepForm = document.getElementById('syspnl-preview-step-form');
  var stepSuccess = document.getElementById('syspnl-preview-step-success');
  var couponBlock = document.getElementById('syspnl-preview-coupon-block');
  var couponMsg = document.getElementById('syspnl-preview-coupon-msg');
  var continueBtn = document.getElementById('syspnl-preview-continue-btn');
  var successMsg = document.getElementById('syspnl-preview-success-msg');
  var copyBtn = document.getElementById('syspnl-preview-copy-btn');

  var toggleForm = document.getElementById('syspnl-toggle-form');
  var toggleSuccess = document.getElementById('syspnl-toggle-success');

  if (toggleForm) {
    toggleForm.addEventListener('click', function () {
      stepForm.style.display = 'block';
      stepSuccess.style.display = 'none';
      toggleForm.classList.add('active');
      toggleSuccess.classList.remove('active');
    });
  }
  if (toggleSuccess) {
    toggleSuccess.addEventListener('click', function () {
      stepForm.style.display = 'none';
      stepSuccess.style.display = 'block';
      toggleForm.classList.remove('active');
      toggleSuccess.classList.add('active');
    });
  }

  // ── Selectores de campos del formulario ──────────────────────────────

  function g(name) { return document.querySelector('[name="' + name + '"]'); }

  var fTitle = g('SYSPNL_TITLE');
  var fSubtitle = g('SYSPNL_SUBTITLE');
  var fBtnText = g('SYSPNL_BTN_TEXT');
  var fPlaceholder = g('SYSPNL_PLACEHOLDER');
  var fSuccessMsg = g('SYSPNL_SUCCESS_MSG');
  var fDiscountMsg = g('SYSPNL_DISCOUNT_MSG');
  var fBgColor = g('SYSPNL_COLOR_BG');
  var fOverlay = g('SYSPNL_COLOR_OVERLAY');
  var fTitleColor = g('SYSPNL_COLOR_TITLE');
  var fSubColor = g('SYSPNL_COLOR_SUBTITLE');
  var fBtnBg = g('SYSPNL_COLOR_BTN_BG');
  var fBtnTextColor = g('SYSPNL_COLOR_BTN_TEXT');
  var fInputBdr = g('SYSPNL_COLOR_INPUT_BORDER');
  var fBorderRad = g('SYSPNL_BORDER_RADIUS');
  var fWidth = g('SYSPNL_WIDTH');
  var fFontFamily = g('SYSPNL_FONT_FAMILY');
  var fFontSizeT = g('SYSPNL_FONT_SIZE_TITLE');
  var fFontSizeS = g('SYSPNL_FONT_SIZE_SUBTITLE');
  var fDiscActive = document.getElementById('SYSPNL_DISCOUNT_ACTIVE_on');

  // ── Actualizar preview ───────────────────────────────────────────────

  function updatePreview() {
    if (!card) return;

    if (title && fTitle) title.textContent = fTitle.value || 'Título';
    if (subtitle && fSubtitle) subtitle.textContent = fSubtitle.value || 'Subtítulo';
    if (btn && fBtnText) btn.textContent = fBtnText.value || 'Suscribirme';
    if (input && fPlaceholder) input.placeholder = fPlaceholder.value || 'Email';
    if (successMsg && fSuccessMsg) successMsg.textContent = fSuccessMsg.value || '¡Gracias por suscribirte!';
    if (couponMsg && fDiscountMsg) couponMsg.textContent = fDiscountMsg.value || '¡Usa este código en tu próxima compra!';

    var discActive = fDiscActive && fDiscActive.checked;
    if (teaser) teaser.style.display = discActive ? 'block' : 'none';
    if (couponBlock) couponBlock.style.display = discActive ? 'block' : 'none';

    if (card && fBgColor) card.style.backgroundColor = fBgColor.value;
    if (card && fBorderRad) card.style.borderRadius = (fBorderRad.value || 12) + 'px';
    if (card && fWidth) card.style.maxWidth = (fWidth.value || 480) + 'px';
    if (card && fFontFamily) card.style.fontFamily = fFontFamily.options[fFontFamily.selectedIndex].value;

    if (overlay && fOverlay) overlay.style.backgroundColor = fOverlay.value;

    if (title && fTitleColor) title.style.color = fTitleColor.value;
    if (title && fFontSizeT) title.style.fontSize = (fFontSizeT.value || 20) + 'px';

    if (subtitle && fSubColor) subtitle.style.color = fSubColor.value;
    if (subtitle && fFontSizeS) subtitle.style.fontSize = (fFontSizeS.value || 13) + 'px';

    if (btn && fBtnBg) btn.style.background = fBtnBg.value;
    if (btn && fBtnTextColor) btn.style.color = fBtnTextColor.value;
    if (continueBtn && fBtnBg) continueBtn.style.background = fBtnBg.value;
    if (continueBtn && fBtnTextColor) continueBtn.style.color = fBtnTextColor.value;
    if (copyBtn && fBtnBg) copyBtn.style.background = fBtnBg.value;
    if (copyBtn && fBtnTextColor) copyBtn.style.color = fBtnTextColor.value;
    if (input && fInputBdr) input.style.borderColor = fInputBdr.value;

    if (teaser && fBtnBg) {
      teaser.style.borderColor = fBtnBg.value;
      teaser.style.color = fBtnBg.value;
    }
  }

  // ── Observar cambios ─────────────────────────────────────────────────

  var watchFields = [
    fTitle, fSubtitle, fBtnText, fPlaceholder, fSuccessMsg, fDiscountMsg,
    fBgColor, fOverlay, fTitleColor, fSubColor, fBtnBg, fBtnTextColor, fInputBdr,
    fBorderRad, fWidth, fFontFamily, fFontSizeT, fFontSizeS
  ];

  watchFields.forEach(function (field) {
    if (!field) return;
    var evt = (field.tagName === 'SELECT') ? 'change' : 'input';
    field.addEventListener(evt, updatePreview);
  });

  ['SYSPNL_DISCOUNT_ACTIVE_on', 'SYSPNL_DISCOUNT_ACTIVE_off'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', updatePreview);
  });

  updatePreview();

  // ── Mostrar/ocultar campo "días" ─────────────────────────────────────

  var freqSel = g('SYSPNL_FREQUENCY');
  var freqVal = g('SYSPNL_FREQUENCY_VALUE');
  if (freqSel && freqVal) {
    var freqGroup = freqVal.closest('.form-group') || freqVal.parentElement;
    function toggleFreq() {
      freqGroup.style.display = (freqSel.value === 'days') ? '' : 'none';
    }
    freqSel.addEventListener('change', toggleFreq);
    toggleFreq();
  }

  // ── Mostrar/ocultar campo "valor descuento" ──────────────────────────

  var discType = g('SYSPNL_DISCOUNT_TYPE');
  var discVal = g('SYSPNL_DISCOUNT_VALUE');
  if (discType && discVal) {
    var discGroup = discVal.closest('.form-group') || discVal.parentElement;
    function toggleDisc() {
      discGroup.style.display = (discType.value === 'shipping') ? 'none' : '';
    }
    discType.addEventListener('change', toggleDisc);
    toggleDisc();
  }

});