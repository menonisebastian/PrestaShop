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
    var previewHtml = '<div id="syspnl-preview-wrap">' +
      '<h4>👁 Vista previa en tiempo real</h4>' +
      '<div id="syspnl-preview-card">' +
        '<div id="syspnl-preview-title">Título</div>' +
        '<div id="syspnl-preview-subtitle">Subtítulo del popup</div>' +
        '<input id="syspnl-preview-input" type="text" placeholder="Tu correo electrónico" readonly>' +
        '<div id="syspnl-preview-btn">Suscribirme</div>' +
      '</div>' +
    '</div>';
    firstPanel.insertAdjacentHTML('afterbegin', previewHtml);
  }

  // ── Selectores ───────────────────────────────────────────────────────

  var card     = document.getElementById('syspnl-preview-card');
  var title    = document.getElementById('syspnl-preview-title');
  var subtitle = document.getElementById('syspnl-preview-subtitle');
  var input    = document.getElementById('syspnl-preview-input');
  var btn      = document.getElementById('syspnl-preview-btn');

  function g(name) { return document.querySelector('[name="' + name + '"]'); }

  var fTitle      = g('SYSPNL_TITLE');
  var fSubtitle   = g('SYSPNL_SUBTITLE');
  var fBtnText    = g('SYSPNL_BTN_TEXT');
  var fPlaceholder= g('SYSPNL_PLACEHOLDER');
  var fBgColor    = g('SYSPNL_COLOR_BG');
  var fTitleColor = g('SYSPNL_COLOR_TITLE');
  var fSubColor   = g('SYSPNL_COLOR_SUBTITLE');
  var fBtnBg      = g('SYSPNL_COLOR_BTN_BG');
  var fBtnText2   = g('SYSPNL_COLOR_BTN_TEXT');
  var fInputBdr   = g('SYSPNL_COLOR_INPUT_BORDER');
  var fBorderRad  = g('SYSPNL_BORDER_RADIUS');
  var fWidth      = g('SYSPNL_WIDTH');
  var fFontFamily = g('SYSPNL_FONT_FAMILY');
  var fFontSizeT  = g('SYSPNL_FONT_SIZE_TITLE');
  var fFontSizeS  = g('SYSPNL_FONT_SIZE_SUBTITLE');

  // ── Actualizar preview ───────────────────────────────────────────────

  function updatePreview() {
    if (!card) return;

    if (title    && fTitle)       title.textContent    = fTitle.value    || 'Título';
    if (subtitle && fSubtitle)    subtitle.textContent = fSubtitle.value || 'Subtítulo';
    if (btn      && fBtnText)     btn.textContent      = fBtnText.value  || 'Suscribirme';
    if (input    && fPlaceholder) input.placeholder    = fPlaceholder.value || 'Email';

    if (card && fBgColor)       card.style.backgroundColor = fBgColor.value;
    if (card && fBorderRad)     card.style.borderRadius    = (fBorderRad.value || 12) + 'px';
    if (card && fFontFamily)    card.style.fontFamily      = fFontFamily.options[fFontFamily.selectedIndex].value;

    if (title    && fTitleColor) title.style.color         = fTitleColor.value;
    if (title    && fFontSizeT)  title.style.fontSize      = (fFontSizeT.value || 20) + 'px';
    if (subtitle && fSubColor)   subtitle.style.color      = fSubColor.value;
    if (subtitle && fFontSizeS)  subtitle.style.fontSize   = (fFontSizeS.value || 13) + 'px';

    if (btn && fBtnBg)          { btn.style.background = fBtnBg.value; }
    if (btn && fBtnText2)       { btn.style.color = fBtnText2.value; }
    if (input && fInputBdr)     { input.style.borderColor = fInputBdr.value; }
  }

  // ── Observar cambios ─────────────────────────────────────────────────

  var watchFields = [
    fTitle, fSubtitle, fBtnText, fPlaceholder,
    fBgColor, fTitleColor, fSubColor, fBtnBg, fBtnText2, fInputBdr,
    fBorderRad, fWidth, fFontFamily, fFontSizeT, fFontSizeS
  ];

  watchFields.forEach(function (field) {
    if (!field) return;
    var evt = (field.tagName === 'SELECT') ? 'change' : 'input';
    field.addEventListener(evt, updatePreview);
  });

  // Preview inicial
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
  var discVal  = g('SYSPNL_DISCOUNT_VALUE');
  if (discType && discVal) {
    var discGroup = discVal.closest('.form-group') || discVal.parentElement;
    function toggleDisc() {
      discGroup.style.display = (discType.value === 'shipping') ? 'none' : '';
    }
    discType.addEventListener('change', toggleDisc);
    toggleDisc();
  }

});
