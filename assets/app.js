/*
 * Kinderarbeit – etwas Komfort im Browser.
 * Die Anwendung funktioniert vollstaendig auch ohne JavaScript.
 */
(function () {
  'use strict';

  /* --- Zifferblock auf der Anmeldeseite ---------------------------------- */
  document.querySelectorAll('[data-pinpad]').forEach(function (pad) {
    var input = document.getElementById(pad.dataset.target);
    if (!input) return;

    pad.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) return;

      if (button.dataset.digit) {
        if (input.value.length < Number(input.maxLength || 10)) {
          input.value += button.dataset.digit;
        }
      } else if (button.dataset.action === 'back') {
        input.value = input.value.slice(0, -1);
      } else if (button.dataset.action === 'clear') {
        input.value = '';
      }
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  /* --- Symbolauswahl in Formularen --------------------------------------- */
  document.querySelectorAll('[data-emoji-picker]').forEach(function (picker) {
    var input = document.getElementById(picker.dataset.target);
    if (!input) return;

    picker.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button || !button.dataset.emoji) return;
      input.value = button.dataset.emoji;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  /* --- Sicherheitsabfrage vor folgenreichen Aktionen ---------------------- */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var question = form.dataset.confirm;
    if (question && !window.confirm(question)) {
      event.preventDefault();
    }
  });

  /* --- Doppelklicks auf Absende-Buttons verhindern ------------------------ */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset.confirm && event.defaultPrevented) return;

    window.setTimeout(function () {
      form.querySelectorAll('button[type="submit"]').forEach(function (button) {
        button.disabled = true;
        if (button.dataset.busyLabel) button.textContent = button.dataset.busyLabel;
      });
    }, 0);
  });

  /* --- Filter sofort anwenden -------------------------------------------- */
  document.querySelectorAll('[data-autosubmit]').forEach(function (element) {
    element.addEventListener('change', function () {
      element.form.submit();
    });
  });

  /* --- Betragsfeld: Punkt als Komma übernehmen --------------------------- */
  document.querySelectorAll('.input--amount').forEach(function (input) {
    input.addEventListener('blur', function () {
      input.value = input.value.replace(/\s|€/g, '');
    });
  });
})();
