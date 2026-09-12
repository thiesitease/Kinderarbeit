<?php defined('KINDERARBEIT') || exit; ?>
<?php if (!empty($flashes)): ?>
  <div class="flashes" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
      <div class="flash flash--<?= e($flash['type']) ?>">
        <span aria-hidden="true"><?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'error' ? '⚠️' : 'ℹ️') ?></span>
        <span><?= e($flash['message']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
