<?php
/**
 * @var array      $users
 * @var array|null $selected
 * @var ?string    $error
 */
defined('KINDERARBEIT') || exit;

$children = array_filter($users, static fn (array $u): bool => $u['role'] === 'child');
$parents  = array_filter($users, static fn (array $u): bool => $u['role'] === 'parent');
?>
<div class="auth">
  <div class="auth__inner">

    <div class="auth__head">
      <div class="auth__logo" aria-hidden="true">💪</div>
      <h1 class="auth__title">Kinderarbeit</h1>
      <p class="auth__sub">
        <?= $selected ? 'Bitte PIN eingeben' : 'Wer bist du?' ?>
      </p>
    </div>

    <?php if ($error): ?>
      <div class="flash flash--error mb-2">
        <span aria-hidden="true">⚠️</span><span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!$selected): ?>

      <div class="profiles">
        <p class="profiles__group-label">Kinder</p>
        <?php foreach ($children as $user): ?>
          <a class="profile" href="<?= e(url('login', ['user' => (int)$user['id']])) ?>"
             style="--accent: <?= e($user['color']) ?>">
            <span class="avatar avatar--lg" aria-hidden="true"><?= e($user['emoji']) ?></span>
            <span>
              <span class="profile__name"><?= e($user['name']) ?></span><br>
              <span class="profile__role">Aufgaben &amp; Konto</span>
            </span>
            <span class="profile__arrow" aria-hidden="true">›</span>
          </a>
        <?php endforeach; ?>

        <p class="profiles__group-label">Eltern</p>
        <?php foreach ($parents as $user): ?>
          <a class="profile" href="<?= e(url('login', ['user' => (int)$user['id']])) ?>"
             style="--accent: <?= e($user['color']) ?>">
            <span class="avatar avatar--lg" aria-hidden="true"><?= e($user['emoji']) ?></span>
            <span>
              <span class="profile__name"><?= e($user['name']) ?></span><br>
              <span class="profile__role">Aufgaben bestätigen &amp; verwalten</span>
            </span>
            <span class="profile__arrow" aria-hidden="true">›</span>
          </a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>

      <div class="card card--accent" style="--accent: <?= e($selected['color']) ?>">
        <div class="row" style="justify-content:center;flex-direction:column;gap:.35rem;text-align:center">
          <span class="avatar avatar--xl" aria-hidden="true"><?= e($selected['emoji']) ?></span>
          <strong style="font-size:1.2rem"><?= e($selected['name']) ?></strong>
        </div>

        <form method="post" action="<?= e(url('login')) ?>" id="pin-form" class="mt-2">
          <?= Csrf::field() ?>
          <input type="hidden" name="user" value="<?= (int)$selected['id'] ?>">

          <label class="visually-hidden" for="pin">PIN</label>
          <input class="input tnum" type="password" id="pin" name="pin"
                 inputmode="numeric" pattern="[0-9]*" autocomplete="current-password"
                 maxlength="10" required autofocus
                 style="text-align:center;font-size:1.6rem;letter-spacing:.5rem;min-height:3.4rem">

          <div class="pinpad" data-pinpad data-target="pin">
            <?php foreach ([1,2,3,4,5,6,7,8,9] as $digit): ?>
              <button type="button" data-digit="<?= $digit ?>"><?= $digit ?></button>
            <?php endforeach; ?>
            <button type="button" class="is-wide" data-action="clear">Löschen</button>
            <button type="button" data-digit="0">0</button>
            <button type="button" class="is-wide" data-action="back">←</button>
          </div>

          <button class="btn btn--primary btn--block mt-2" type="submit">Anmelden</button>
        </form>

        <p class="mt-2" style="text-align:center">
          <a class="small muted" href="<?= e(url('login')) ?>">← Anderes Profil wählen</a>
        </p>
      </div>

    <?php endif; ?>

    <p class="footer">Kinderarbeit · kinderarbeit.example.de</p>
  </div>
</div>
