<?php
/**
 * Eine offene Meldung in der Wiedervorlage.
 * @var array  $item
 * @var string $back
 * @var int    $backId
 */
defined('KINDERARBEIT') || exit;

$back   = $back ?? 'eltern';
$backId = $backId ?? 0;
?>
<article class="review">
  <div class="review__head" style="--accent: <?= e($item['child_color']) ?>">
    <span class="avatar" aria-hidden="true"><?= e($item['child_emoji']) ?></span>
    <div>
      <div class="review__title"><?= e($item['child_name']) ?> hat „<?= e($item['title']) ?>“ erledigt</div>
      <div class="review__meta">gemeldet <?= e(format_datetime($item['created_at'])) ?></div>
    </div>
    <div class="review__amount"><?= e(Money::format((int)$item['amount_cents'])) ?></div>
  </div>

  <?php if (!empty($item['note'])): ?>
    <p class="small muted" style="padding:.6rem .9rem 0;margin:0">💬 <?= e($item['note']) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('pruefen')) ?>" class="review__body">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="back_id" value="<?= (int)$backId ?>">

    <label class="visually-hidden" for="note-<?= (int)$item['id'] ?>">Notiz (freiwillig)</label>
    <input class="input review__note" type="text" id="note-<?= (int)$item['id'] ?>" name="note"
           maxlength="120" placeholder="Notiz (freiwillig)">

    <button class="btn btn--done" type="submit" name="action" value="approve">
      Bestätigen &amp; <?= e(Money::format((int)$item['amount_cents'])) ?> gutschreiben
    </button>
    <button class="btn btn--danger" type="submit" name="action" value="reject">Ablehnen</button>
  </form>
</article>
