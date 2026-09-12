<?php
/**
 * @var ?string $error
 * @var bool    $force
 * @var array   $me
 */
defined('KINDERARBEIT') || exit;

$form = static function (bool $force, ?string $error, array $me): string {
    ob_start(); ?>
    <?php if ($force): ?>
      <div class="notice mb-2">
        <span aria-hidden="true">🔐</span>
        <span>Du bist mit der voreingestellten Start-PIN angemeldet. Bitte wähle jetzt eine eigene PIN, die nur du kennst.</span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="flash flash--error mb-2"><span aria-hidden="true">⚠️</span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('pin')) ?>" class="card">
      <?= Csrf::field() ?>

      <div class="field">
        <label class="field__label" for="current_pin">Aktuelle PIN</label>
        <input class="input tnum" type="password" id="current_pin" name="current_pin"
               inputmode="numeric" pattern="[0-9]*" autocomplete="current-password"
               maxlength="10" required autofocus>
      </div>

      <div class="field">
        <label class="field__label" for="new_pin">Neue PIN</label>
        <input class="input tnum" type="password" id="new_pin" name="new_pin"
               inputmode="numeric" pattern="[0-9]*" autocomplete="new-password"
               minlength="<?= Auth::MIN_PIN_LENGTH ?>" maxlength="<?= Auth::MAX_PIN_LENGTH ?>" required>
        <p class="field__hint">
          <?= Auth::MIN_PIN_LENGTH ?> bis <?= Auth::MAX_PIN_LENGTH ?> Ziffern.
          Keine Reihen wie 1234 und keine gleichen Ziffern wie 1111.
        </p>
      </div>

      <div class="field">
        <label class="field__label" for="repeat_pin">Neue PIN wiederholen</label>
        <input class="input tnum" type="password" id="repeat_pin" name="repeat_pin"
               inputmode="numeric" pattern="[0-9]*" autocomplete="new-password"
               minlength="<?= Auth::MIN_PIN_LENGTH ?>" maxlength="<?= Auth::MAX_PIN_LENGTH ?>" required>
      </div>

      <div class="btn-row mt-2">
        <button class="btn btn--primary" type="submit">PIN speichern</button>
        <?php if (!$force): ?>
          <a class="btn btn--ghost" href="<?= e(url('start')) ?>">Abbrechen</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($force): ?>
      <form method="post" action="<?= e(url('logout')) ?>" class="mt-2" style="text-align:center">
        <?= Csrf::field() ?>
        <button class="btn btn--ghost btn--sm" type="submit">Doch lieber abmelden</button>
      </form>
    <?php endif; ?>
    <?php
    return (string)ob_get_clean();
};
?>

<?php if ($force): ?>
  <div class="auth">
    <div class="auth__inner">
      <div class="auth__head">
        <div class="auth__logo" aria-hidden="true"><?= e($me['emoji']) ?></div>
        <h1 class="auth__title">Hallo <?= e($me['name']) ?>!</h1>
        <p class="auth__sub">Noch ein Schritt: deine eigene PIN.</p>
      </div>
      <?= $form(true, $error, $me) ?>
    </div>
  </div>
<?php else: ?>
  <div style="max-width:32rem;margin:0 auto">
    <div class="section__head"><h1>PIN ändern</h1></div>
    <?= $form(false, $error, $me) ?>
  </div>
<?php endif; ?>
