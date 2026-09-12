<?php defined('KINDERARBEIT') || exit; ?>
<a class="nav__link" href="<?= e($href) ?>"<?= !empty($active) ? ' aria-current="page"' : '' ?>>
  <span aria-hidden="true"><?= e($icon) ?></span>
  <span><?= e($label) ?></span>
  <?php if (!empty($count)): ?><span class="nav__count"><?= (int)$count ?></span><?php endif; ?>
</a>
