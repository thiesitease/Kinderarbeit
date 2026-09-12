<?php
/** @var array $grouped, $children, $totals, $balances */
defined('KINDERARBEIT') || exit;

$grandTotal = array_sum($totals);
?>

<div class="section__head">
  <h1>Feste Ausgaben</h1>
  <span class="section__hint">zusammen <?= e(Money::format($grandTotal)) ?> pro Monat</span>
  <div class="section__action">
    <a class="btn btn--primary btn--sm" href="<?= e(url('ausgabe-form')) ?>">＋ Neue Ausgabe</a>
  </div>
</div>

<div class="notice mb-2">
  <span aria-hidden="true">ℹ️</span>
  <span>Feste Ausgaben werden jeden Monat automatisch vom Konto des Kindes abgebucht –
  zum Beispiel der Beitrag fürs Fitnessstudio. Der Restbetrag im Monat berücksichtigt sie sofort.</span>
</div>

<?php foreach ($children as $child): ?>
  <?php
  $childId = (int)$child['id'];
  $rows    = $grouped[$childId] ?? [];
  $total   = $totals[$childId] ?? 0;
  ?>
  <section class="section">
    <div class="section__head">
      <h2 class="row row--tight">
        <span class="avatar" style="--accent: <?= e($child['color']) ?>" aria-hidden="true"><?= e($child['emoji']) ?></span>
        <?= e($child['name']) ?>
      </h2>
      <span class="section__hint">
        <?= e(Money::format($total)) ?> pro Monat · Guthaben <?= e(Money::format($balances[$childId] ?? 0)) ?>
      </span>
      <div class="section__action">
        <a class="btn btn--sm" href="<?= e(url('ausgabe-form', ['kind' => $childId])) ?>">＋ Hinzufügen</a>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="card empty" style="padding:1.2rem">
        Keine regelmäßigen Ausgaben für <?= e($child['name']) ?>.
      </div>
    <?php else: ?>
      <div class="card card--flush">
        <ul class="list">
          <?php foreach ($rows as $expense): ?>
            <?php $isActive = (int)$expense['is_active'] === 1; ?>
            <li<?= $isActive ? '' : ' style="opacity:.6"' ?>>
              <div class="entry">
                <div class="entry__icon" aria-hidden="true"><?= e($expense['emoji']) ?></div>
                <div class="entry__body">
                  <div class="entry__title">
                    <?= e($expense['title']) ?>
                    <?php if (!$isActive): ?><span class="pill tiny">pausiert</span><?php endif; ?>
                  </div>
                  <div class="entry__meta">
                    jeden <?= (int)$expense['day_of_month'] ?>. im Monat · seit <?= e(month_label($expense['start_month'])) ?>
                    <?php if (!empty($expense['end_month'])): ?> · bis <?= e(month_label($expense['end_month'])) ?><?php endif; ?>
                  </div>
                </div>
                <div class="entry__amount value-negative"><?= e(Money::format(-(int)$expense['amount_cents'])) ?></div>
                <a class="btn btn--sm" href="<?= e(url('ausgabe-form', ['id' => (int)$expense['id']])) ?>">Bearbeiten</a>
                <form method="post" action="<?= e(url('ausgabe-aktion')) ?>" class="inline-form">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">
                  <?php if ($isActive): ?>
                    <button class="btn btn--ghost btn--sm" type="submit" name="action" value="pause">Pausieren</button>
                  <?php else: ?>
                    <button class="btn btn--sm" type="submit" name="action" value="activate">Fortsetzen</button>
                  <?php endif; ?>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
