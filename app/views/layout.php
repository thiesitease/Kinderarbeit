<?php
/**
 * @var string      $content
 * @var string|null $title
 * @var array|null  $me
 * @var string      $page
 * @var bool|null   $bare
 */
defined('KINDERARBEIT') || exit;

$me      = $me ?? null;
$bare    = !empty($bare);
$accent  = $me['color'] ?? '#1c7ed6';
$flashes = Flash::take();

$pendingForNav = ($me && $me['role'] === 'parent') ? Completions::pendingCount() : 0;
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'Kinderarbeit') ?> · Kinderarbeit</title>
<meta name="description" content="Aufgaben, Taschengeld und Konto für die ganze Familie.">
<meta name="theme-color" content="<?= e($accent) ?>">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/favicon.svg">
<link rel="manifest" href="assets/manifest.webmanifest">
<link rel="stylesheet" href="assets/app.css?v=<?= e(KINDERARBEIT) ?>">
<style>:root { --accent: <?= e($accent) ?>; --accent-text: <?= e(contrast_color($accent)) ?>; }</style>
</head>
<body>
<?php if ($bare): ?>

  <?php if ($flashes): ?>
    <div class="auth" style="min-height:0;padding-bottom:0">
      <div class="auth__inner"><?= View::render('partials/flashes', ['flashes' => $flashes]) ?></div>
    </div>
  <?php endif; ?>
  <?= $content ?>

<?php else: ?>

  <div class="shell">
    <header class="topbar">
      <div class="topbar__inner">
        <a class="brand" href="<?= e(url('start')) ?>">
          <span class="brand__mark" aria-hidden="true">💪</span>
          <span>Kinderarbeit</span>
        </a>
        <span class="topbar__spacer"></span>
        <?php if ($me): ?>
          <a class="userchip" href="<?= e(url('pin')) ?>" title="PIN ändern">
            <span class="avatar" aria-hidden="true"><?= e($me['emoji']) ?></span>
            <span><?= e($me['name']) ?></span>
          </a>
          <form method="post" action="<?= e(url('logout')) ?>" class="inline-form">
            <?= Csrf::field() ?>
            <button class="btn btn--ghost btn--sm" type="submit">Abmelden</button>
          </form>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($me): ?>
      <nav class="nav" aria-label="Hauptnavigation">
        <div class="nav__inner">
          <?php if ($me['role'] === 'parent'): ?>
            <?= View::render('partials/nav-link', ['href' => url('eltern'),   'label' => 'Übersicht',  'icon' => '🏠', 'active' => in_array($page, ['eltern', 'start'], true), 'count' => $pendingForNav]) ?>
            <?= View::render('partials/nav-link', ['href' => url('aufgaben'), 'label' => 'Aufgaben',   'icon' => '📋', 'active' => in_array($page, ['aufgaben', 'aufgabe-form'], true)]) ?>
            <?= View::render('partials/nav-link', ['href' => url('kinder'),   'label' => 'Kinder',     'icon' => '🧒', 'active' => in_array($page, ['kinder', 'kind-detail'], true)]) ?>
            <?= View::render('partials/nav-link', ['href' => url('ausgaben'), 'label' => 'Ausgaben',   'icon' => '💳', 'active' => in_array($page, ['ausgaben', 'ausgabe-form'], true)]) ?>
            <?= View::render('partials/nav-link', ['href' => url('verlauf'),  'label' => 'Verlauf',    'icon' => '🕘', 'active' => in_array($page, ['verlauf', 'buchung'], true)]) ?>
            <?= View::render('partials/nav-link', ['href' => url('familie'),  'label' => 'Familie',    'icon' => '⚙️', 'active' => $page === 'familie']) ?>
          <?php else: ?>
            <?= View::render('partials/nav-link', ['href' => url('kind'),         'label' => 'Aufgaben',  'icon' => '📋', 'active' => in_array($page, ['kind', 'start'], true)]) ?>
            <?= View::render('partials/nav-link', ['href' => url('kind-konto'),   'label' => 'Mein Konto','icon' => '🐷', 'active' => $page === 'kind-konto']) ?>
            <?= View::render('partials/nav-link', ['href' => url('kind-verlauf'), 'label' => 'Verlauf',   'icon' => '🕘', 'active' => $page === 'kind-verlauf']) ?>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>

    <main class="main<?= !empty($narrow) ? ' main--narrow' : '' ?>">
      <?= View::render('partials/flashes', ['flashes' => $flashes]) ?>
      <?= $content ?>
    </main>

    <footer class="footer">
      Kinderarbeit
    </footer>
  </div>

<?php endif; ?>
<script src="assets/app.js?v=<?= e(KINDERARBEIT) ?>" defer></script>
</body>
</html>
