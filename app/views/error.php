<?php defined('KINDERARBEIT') || exit; ?>
<div class="card" style="text-align:center;padding:2.5rem 1.25rem">
  <div style="font-size:2.5rem" aria-hidden="true">🤔</div>
  <h1 class="mt-1"><?= e($title) ?></h1>
  <p class="muted mt-1"><?= e($message) ?></p>
  <p class="mt-2"><a class="btn btn--primary" href="<?= e(url('start')) ?>">Zur Startseite</a></p>
</div>
