<?php
/** @var array $users, $colors, $emojis, $me */
defined('KINDERARBEIT') || exit;
?>

<div class="section__head">
  <h1>Familie</h1>
  <span class="section__hint">Profile, PINs und Sperren</span>
</div>

<div class="notice mb-2">
  <span aria-hidden="true">🔐</span>
  <span>
    Nach <?= Auth::MAX_ATTEMPTS ?> falschen PIN-Eingaben wird ein Profil für
    <?= Auth::LOCK_MINUTES ?> Minuten gesperrt. Ihr könnt die Sperre hier sofort aufheben.
  </span>
</div>

<div class="grid-2">
  <?php foreach ($users as $user): ?>
    <?php
    $userId   = (int)$user['id'];
    $isSelf   = $userId === (int)$me['id'];
    $isLocked = Users::isLocked($user);
    $canReset = $user['role'] === 'child' || $isSelf;
    ?>
    <article class="card" style="--accent: <?= e($user['color']) ?>">
      <div class="row row--nowrap">
        <span class="avatar avatar--lg" aria-hidden="true"><?= e($user['emoji']) ?></span>
        <div>
          <div style="font-weight:650;font-size:1.05rem">
            <?= e($user['name']) ?>
            <?php if ($isSelf): ?><span class="pill tiny">du</span><?php endif; ?>
          </div>
          <div class="small muted">
            <?= $user['role'] === 'parent' ? 'Elternzugang' : 'Kinderzugang' ?> ·
            <?php if (!empty($user['last_login_at'])): ?>
              zuletzt angemeldet <?= e(format_datetime($user['last_login_at'])) ?>
            <?php else: ?>
              noch nie angemeldet
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row mt-2 row--tight">
        <?php if ((int)$user['must_change_pin'] === 1): ?>
          <span class="pill pill--pending">⚠️ Standard-PIN aktiv</span>
        <?php else: ?>
          <span class="pill pill--positive">✓ Eigene PIN gesetzt</span>
        <?php endif; ?>
        <?php if ($isLocked): ?>
          <span class="pill pill--negative">🔒 gesperrt bis <?= e(date('H:i', (int)strtotime((string)$user['locked_until']))) ?> Uhr</span>
        <?php elseif ((int)$user['failed_logins'] > 0): ?>
          <span class="pill"><?= (int)$user['failed_logins'] ?> Fehlversuch<?= (int)$user['failed_logins'] === 1 ? '' : 'e' ?></span>
        <?php endif; ?>
      </div>

      <?php if ($isLocked): ?>
        <form method="post" action="<?= e(url('familie-aktion')) ?>" class="mt-2">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $userId ?>">
          <button class="btn btn--sm" type="submit" name="action" value="unlock">Sperre aufheben</button>
        </form>
      <?php endif; ?>

      <?php if ($canReset): ?>
        <form method="post" action="<?= e(url('familie-aktion')) ?>" class="mt-2">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $userId ?>">
          <label class="field__label" for="pin-<?= $userId ?>">
            <?= $isSelf ? 'Eigene PIN neu setzen' : 'Neue PIN für ' . e($user['name']) ?>
          </label>
          <div class="row row--tight">
            <input class="input tnum" type="text" id="pin-<?= $userId ?>" name="new_pin"
                   inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                   minlength="<?= Auth::MIN_PIN_LENGTH ?>" maxlength="<?= Auth::MAX_PIN_LENGTH ?>"
                   placeholder="<?= Auth::MIN_PIN_LENGTH ?>–<?= Auth::MAX_PIN_LENGTH ?> Ziffern" style="flex:1;min-width:8rem">
            <button class="btn btn--sm" type="submit" name="action" value="reset-pin">Setzen</button>
          </div>
          <?php if (!$isSelf): ?>
            <p class="field__hint"><?= e($user['name']) ?> muss die PIN beim nächsten Anmelden selbst ändern.</p>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <p class="field__hint mt-2">
          Die PIN des anderen Elternteils kann nur <?= e($user['name']) ?> selbst ändern.
        </p>
      <?php endif; ?>

      <form method="post" action="<?= e(url('familie-aktion')) ?>" class="mt-2">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $userId ?>">
        <label class="field__label" for="emoji-<?= $userId ?>">Symbol und Farbe</label>
        <div class="row row--tight">
          <input class="input" type="text" id="emoji-<?= $userId ?>" name="emoji" maxlength="8"
                 style="width:4rem;text-align:center;font-size:1.25rem" value="<?= e($user['emoji']) ?>">
          <input class="input" type="color" name="color" value="<?= e($user['color']) ?>"
                 style="width:4rem;padding:.2rem" aria-label="Farbe für <?= e($user['name']) ?>">
          <button class="btn btn--sm" type="submit" name="action" value="profile">Speichern</button>
        </div>
        <details class="reveal">
          <summary>Symbol auswählen</summary>
          <div class="emoji-picker" data-emoji-picker data-target="emoji-<?= $userId ?>">
            <?php foreach ($emojis as $option): ?>
              <button class="emoji-picker__btn" type="button" data-emoji="<?= e($option) ?>"
                      aria-label="Symbol <?= e($option) ?> wählen"><?= e($option) ?></button>
            <?php endforeach; ?>
          </div>
        </details>
      </form>
    </article>
  <?php endforeach; ?>
</div>

<section class="section">
  <div class="section__head"><h2>Gut zu wissen</h2></div>
  <div class="card">
    <ul class="small muted" style="margin:0;padding-left:1.1rem;display:grid;gap:.4rem">
      <li>Jedes Kind sieht nur die eigenen Aufgaben, das eigene Konto und den eigenen Verlauf.</li>
      <li>Beide Elternzugänge haben die gleichen Rechte – eine Bestätigung genügt.</li>
      <li>Eine Bestätigung lässt sich im Verlauf zurücknehmen; die Gegenbuchung bleibt sichtbar.</li>
      <li>Gelöschte Aufgaben mit Historie werden nur pausiert, damit alte Buchungen nachvollziehbar bleiben.</li>
    </ul>
  </div>
</section>
