<?php
/**
 * Schalter fuer Push-Benachrichtigungen.
 *
 * Bleibt ohne JavaScript und auf Geraeten ohne Push unsichtbar: das Kaestchen
 * startet mit "hidden", erst assets/app.js blendet den passenden Zustand ein.
 *
 * @var array $me
 */
defined('KINDERARBEIT') || exit;

if (!Push::isAvailable() || empty($me)) {
    return;
}

$istKind = ($me['role'] ?? '') === 'child';
?>
<div class="card push mb-2" data-push hidden
     data-key="<?= e(Push::publicKey()) ?>"
     data-sw="<?= e((string)parse_url(base_url(), PHP_URL_PATH) . 'sw.js') ?>"
     data-user="<?= (int)$me['id'] ?>"
     data-an="<?= e(url('push-an')) ?>"
     data-aus="<?= e(url('push-aus')) ?>"
     data-csrf="<?= e(Csrf::token()) ?>">

  <div class="row" data-push-state="aus" hidden>
    <span class="push__icon" aria-hidden="true">🔔</span>
    <div class="push__text">
      <div class="push__title">Benachrichtigungen einschalten</div>
      <div class="small muted">
        <?php if ($istKind): ?>
          Dann sagt dir dein Handy sofort Bescheid, wenn eine Aufgabe bestätigt ist
          oder es etwas Neues zu tun gibt.
        <?php else: ?>
          Dann meldet sich dieses Gerät, sobald ein Kind eine Aufgabe zur
          Bestätigung meldet – auch wenn die Seite geschlossen ist.
        <?php endif; ?>
      </div>
    </div>
    <button class="btn btn--primary push-right" type="button" data-push-an>Einschalten</button>
  </div>

  <div class="row" data-push-state="an" hidden>
    <span class="push__icon" aria-hidden="true">🔔</span>
    <div class="push__text">
      <div class="push__title">Benachrichtigungen sind an</div>
      <div class="small muted">Auf diesem Gerät. Andere Geräte werden einzeln eingeschaltet.</div>
    </div>
    <button class="btn btn--ghost btn--sm push-right" type="button" data-push-aus>Ausschalten</button>
  </div>

  <div class="row" data-push-state="blockiert" hidden>
    <span class="push__icon" aria-hidden="true">🔕</span>
    <div class="push__text">
      <div class="push__title">Benachrichtigungen sind im Browser gesperrt</div>
      <div class="small muted">
        Das lässt sich nur in den Einstellungen des Browsers wieder erlauben –
        beim Schloss-Symbol neben der Adresse.
      </div>
    </div>
  </div>

  <div class="row" data-push-state="ios" hidden>
    <span class="push__icon" aria-hidden="true">📲</span>
    <div class="push__text">
      <div class="push__title">Erst zum Home-Bildschirm hinzufügen</div>
      <div class="small muted">
        Auf dem iPhone gibt es Benachrichtigungen nur, wenn die Seite als App
        gespeichert ist: unten auf „Teilen“ tippen, dann „Zum Home-Bildschirm“.
      </div>
    </div>
  </div>

  <div class="row" data-push-state="fehler" hidden>
    <span class="push__icon" aria-hidden="true">⚠️</span>
    <div class="push__text">
      <div class="push__title">Das hat nicht geklappt</div>
      <div class="small muted" data-push-fehler></div>
    </div>
  </div>
</div>
