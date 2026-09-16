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

$id     = (int)$item['id'];
$grund  = Completions::baseAmount($item);
$sterne = Completions::starLabel($item);
?>
<article class="review">
  <div class="review__head" style="--accent: <?= e($item['child_color']) ?>">
    <span class="avatar" aria-hidden="true"><?= e($item['child_emoji']) ?></span>
    <div>
      <div class="review__title"><?= e($item['child_name']) ?> hat „<?= e($item['title']) ?>“ erledigt</div>
      <div class="review__meta">gemeldet <?= e(format_datetime($item['created_at'])) ?></div>
    </div>
    <div class="review__amount"><?= e(Money::format($grund)) ?></div>
  </div>

  <?php if (!empty($item['note'])): ?>
    <p class="small muted" style="padding:.6rem .9rem 0;margin:0">💬 <?= e($item['note']) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('pruefen')) ?>" class="review__body">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="back_id" value="<?= (int)$backId ?>">

    <?php if ($sterne !== ''): ?>
      <?php /* Der Regler schickt Zehntel (10 = unveraendert), damit nirgends
               ein Komma durch die Rechnung laeuft. Ohne JavaScript bleibt die
               Vorschau darunter verborgen – der Betrag entsteht ohnehin erst
               auf dem Server, sie soll nur nicht falsch dastehen. */ ?>
      <div class="zuschlag" data-zuschlag data-grund="<?= $grund ?>">
        <p class="zuschlag__grund">
          <span class="zuschlag__sterne" aria-hidden="true"><?= $sterne ?></span>
          <?= e($item['child_name']) ?> fand es besonders schwer.
        </p>
        <label class="zuschlag__label" for="faktor-<?= $id ?>">Zuschlag (1- bis 3-facher Betrag)</label>
        <div class="zuschlag__regler">
          <span class="zuschlag__ende" aria-hidden="true">×1</span>
          <input class="zuschlag__schieber" type="range" id="faktor-<?= $id ?>" name="faktor"
                 min="<?= Completions::FAKTOR_MIN ?>" max="<?= Completions::FAKTOR_MAX ?>" step="1"
                 value="<?= Completions::FAKTOR_MIN ?>">
          <span class="zuschlag__ende" aria-hidden="true">×3</span>
        </div>
        <output class="zuschlag__wert" for="faktor-<?= $id ?>" data-zuschlag-wert hidden></output>
      </div>
    <?php endif; ?>

    <label class="visually-hidden" for="note-<?= $id ?>">Notiz (freiwillig)</label>
    <input class="input review__note" type="text" id="note-<?= $id ?>" name="note"
           maxlength="120" placeholder="Notiz (freiwillig)">

    <button class="btn btn--done" type="submit" name="action" value="approve">
      <?php if ($sterne !== ''): ?>
        Bestätigen &amp;<span data-zuschlag-betrag hidden></span> gutschreiben
      <?php else: ?>
        Bestätigen &amp; <?= e(Money::format($grund)) ?> gutschreiben
      <?php endif; ?>
    </button>
    <button class="btn btn--danger" type="submit" name="action" value="reject">Ablehnen</button>
  </form>
</article>
