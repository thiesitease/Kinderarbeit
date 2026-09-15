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

  /* --- Zugangslink in die Zwischenablage kopieren ------------------------- */
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    var field = document.getElementById(button.dataset.copy);
    if (!field) return;

    button.addEventListener('click', function () {
      var done = function () {
        var original = button.textContent;
        button.textContent = button.dataset.copiedLabel || 'Kopiert';
        window.setTimeout(function () { button.textContent = original; }, 2000);
      };

      // Auswahl sichtbar machen, damit auch ein Kopieren von Hand leichtfällt.
      field.focus();
      field.setSelectionRange(0, field.value.length);

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.value).then(done, function () {
          if (document.execCommand('copy')) done();
        });
      } else if (document.execCommand('copy')) {
        done();
      }
    });
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

  /* --- Benachrichtigungen ------------------------------------------------- */
  (function () {
    var box = document.querySelector('[data-push]');
    if (!box) return;

    var unterstuetzt = 'serviceWorker' in navigator &&
                       'PushManager' in window &&
                       'Notification' in window;

    var istApple = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                   (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function zeige(zustand, text) {
      box.hidden = false;
      box.querySelectorAll('[data-push-state]').forEach(function (teil) {
        teil.hidden = teil.dataset.pushState !== zustand;
      });
      if (text) {
        var feld = box.querySelector('[data-push-fehler]');
        if (feld) feld.textContent = text;
      }
    }

    if (!unterstuetzt) {
      // Auf dem iPhone gibt es Push nur, wenn die Seite als App auf dem
      // Home-Bildschirm liegt. Das ist kein Fehler, sondern ein fehlender
      // Schritt – und einer, auf den man von allein nicht kommt.
      if (istApple && !window.navigator.standalone) zeige('ios');
      return;
    }

    // Den Pfad zum Service Worker gibt der Server vor: er muss im
    // Wurzelverzeichnis der Anwendung liegen, damit sein Geltungsbereich
    // alle Seiten umfasst, und aus der Adresszeile allein liesse sich das
    // nicht zuverlaessig ableiten. Bewusst nur der Pfad und nicht die volle
    // Adresse – so passt das Schema immer zu dem der Seite.
    var swPfad = box.dataset.sw;

    function schluesselAlsBytes(base64) {
      var gefuellt = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
      var roh = window.atob(gefuellt);
      var bytes = new Uint8Array(roh.length);
      for (var i = 0; i < roh.length; i++) bytes[i] = roh.charCodeAt(i);
      return bytes;
    }

    function melde(ziel, abo) {
      var daten = new URLSearchParams();
      daten.set('csrf', box.dataset.csrf);
      daten.set('endpoint', abo.endpoint);

      if (ziel === box.dataset.an) {
        var json = abo.toJSON();
        daten.set('p256dh', (json.keys && json.keys.p256dh) || '');
        daten.set('auth', (json.keys && json.keys.auth) || '');
      }

      return fetch(ziel, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: daten.toString()
      }).then(function (antwort) {
        return antwort.json().catch(function () { return { ok: false }; });
      }).then(function (ergebnis) {
        if (!ergebnis.ok) throw new Error(ergebnis.error || 'Der Server hat die Anmeldung abgelehnt.');
        return ergebnis;
      });
    }

    navigator.serviceWorker.register(swPfad).then(function (registrierung) {
      return registrierung.pushManager.getSubscription().then(function (abo) {
        if (abo) {
          zeige('an');
          // Einmal je Sitzung nachmelden. Das hält den Serverstand richtig,
          // wenn sich auf demselben Gerät jemand anderes anmeldet – der
          // Endpunkt bleibt derselbe, die Person dahinter nicht.
          var merker = 'kinderarbeit-push-' + box.dataset.user;
          try {
            if (window.sessionStorage && !sessionStorage.getItem(merker)) {
              melde(box.dataset.an, abo).then(function () {
                sessionStorage.setItem(merker, '1');
              }, function () { /* beim nächsten Aufruf erneut */ });
            }
          } catch (fehler) { /* privates Fenster: dann eben jedes Mal */ }
        } else if (Notification.permission === 'denied') {
          zeige('blockiert');
        } else {
          zeige('aus');
        }

        var einschalten = box.querySelector('[data-push-an]');
        if (einschalten) {
          einschalten.addEventListener('click', function () {
            einschalten.disabled = true;

            Notification.requestPermission().then(function (erlaubnis) {
              if (erlaubnis !== 'granted') {
                einschalten.disabled = false;
                zeige(erlaubnis === 'denied' ? 'blockiert' : 'aus');
                return;
              }

              return registrierung.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: schluesselAlsBytes(box.dataset.key)
              }).then(function (neu) {
                return melde(box.dataset.an, neu).then(function () { zeige('an'); });
              });
            }).catch(function (fehler) {
              einschalten.disabled = false;
              zeige('fehler', fehler.message || String(fehler));
            });
          });
        }

        var ausschalten = box.querySelector('[data-push-aus]');
        if (ausschalten) {
          ausschalten.addEventListener('click', function () {
            ausschalten.disabled = true;

            registrierung.pushManager.getSubscription().then(function (vorhanden) {
              if (!vorhanden) { zeige('aus'); return; }
              // Erst beim Server abmelden: schlägt das fehl, bleibt das Abo
              // im Browser bestehen und lässt sich erneut abmelden.
              return melde(box.dataset.aus, vorhanden)
                .then(function () { return vorhanden.unsubscribe(); })
                .then(function () { zeige('aus'); });
            }).catch(function (fehler) {
              zeige('fehler', fehler.message || String(fehler));
            }).then(function () {
              ausschalten.disabled = false;
            });
          });
        }
      });
    }).catch(function (fehler) {
      zeige('fehler', fehler.message || String(fehler));
    });
  })();
})();
