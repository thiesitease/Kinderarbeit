<?php
/**
 * @var array  $child, $pending, $summary, $entries, $expenses, $months
 * @var int    $balance, $pendingAmount, $daysLeft
 * @var string $month
 */
defined('KINDERARBEIT') || exit;

$childId        = (int)$child['id'];
$isCurrentMonth = $month === current_month();
$activeExpenses = array_filter($expenses, static fn (array $ex): bool => (int)$ex['is_active'] === 1);

// Damit Bearbeiten und Löschen einer Buchung wieder auf dieser Seite landen.
$back = ['back' => 'kind-detail', 'back_id' => $childId, 'back_monat' => $month];
$backFields = '';
foreach ($back as $key => $value) {
    $backFields .= '<input type="hidden" name="' . e($key) . '" value="' . e((string)$value) . '">';
}
?>

<section class="hero" style="--accent: <?= e($child['color']) ?>">
  <div class="row">
    <span class="avatar avatar--xl" aria-hidden="true"><?= e($child['emoji']) ?></span>
    <div>
      <div class="hero__label"><?= e($child['name']) ?> · Guthaben</div>
      <div class="hero__amount<?= $balance < 0 ? ' hero__amount--negative' : '' ?>" style="margin-bottom:.2rem">
        <?= e(Money::format($balance)) ?>
      </div>
    </div>
  </div>
  <div class="hero__meta">
    <?php if ($pendingAmount > 0): ?>
      <span class="pill pill--pending">⏳ <?= e(Money::format($pendingAmount)) ?> zu bestätigen</span>
    <?php endif; ?>
    <span class="pill">🕐 <?= !empty($child['last_login_at'])
        ? 'zuletzt angemeldet ' . e(format_datetime($child['last_login_at']))
        : 'noch nie angemeldet' ?></span>
  </div>
  <div class="btn-row mt-2">
    <?php if (!empty($child['phone'])): ?>
      <?php
      $text = 'Hallo ' . $child['name'] . '! Dein Guthaben bei Kinderarbeit: '
            . Money::format($balance) . '. ' . base_url();
      ?>
      <a class="btn btn--whatsapp btn--sm" target="_blank" rel="noopener"
         href="<?= e(Phone::waLink($child['phone'], $text)) ?>">💬 WhatsApp</a>
    <?php endif; ?>
    <a class="btn btn--sm" href="<?= e(url('buchung', $back + ['kind' => $childId, 'art' => 'payout'])) ?>">💶 Auszahlen</a>
    <a class="btn btn--sm" href="<?= e(url('buchung', $back + ['kind' => $childId, 'art' => 'bonus'])) ?>">🎁 Bonus</a>
    <a class="btn btn--sm" href="<?= e(url('ausgabe-form', ['kind' => $childId])) ?>">💳 Feste Ausgabe</a>
  </div>
</section>

<?php if ($pending): ?>
  <section class="section">
    <div class="section__head">
      <h2>Zu bestätigen</h2>
      <span class="section__hint"><?= count($pending) ?> · <?= e(Money::format($pendingAmount)) ?></span>
    </div>
    <?php foreach ($pending as $item): ?>
      <?= View::render('partials/review-item', ['item' => $item, 'back' => 'kind-detail', 'backId' => $childId]) ?>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<section class="section">
  <div class="section__head">
    <h2><?= e(month_label($month)) ?></h2>
    <?php if ($isCurrentMonth): ?>
      <span class="section__hint">noch <?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'Tag' : 'Tage' ?></span>
    <?php endif; ?>
    <div class="section__action">
      <?= View::render('partials/month-switch', [
            'month' => $month, 'months' => $months,
            'target' => 'kind-detail', 'extra' => ['id' => $childId],
      ]) ?>
    </div>
  </div>

  <div class="card">
    <div class="stats">
      <div class="stat">
        <div class="stat__label">Verdient</div>
        <div class="stat__value value-positive"><?= e(Money::format($summary['earned'])) ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Bonus</div>
        <div class="stat__value<?= $summary['bonus'] > 0 ? ' value-positive' : '' ?>"><?= e(Money::format($summary['bonus'])) ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Feste Ausgaben</div>
        <div class="stat__value<?= $summary['expenses'] > 0 ? ' value-negative' : '' ?>"><?= e(Money::format(-$summary['expenses'])) ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Ausgezahlt</div>
        <div class="stat__value"><?= e(Money::format(-$summary['payouts'])) ?></div>
      </div>
      <?php if ($summary['corrections'] !== 0): ?>
        <div class="stat">
          <div class="stat__label">Korrekturen</div>
          <div class="stat__value <?= $summary['corrections'] > 0 ? 'value-positive' : 'value-negative' ?>"><?= e(Money::format($summary['corrections'], true)) ?></div>
        </div>
      <?php endif; ?>
      <div class="stat stat--rest">
        <div class="stat__label">Rest im Monat</div>
        <div class="stat__value"><?= e(Money::format($summary['net'], true)) ?></div>
      </div>
    </div>
    <p class="field__hint mt-1">
      <strong>Rest im Monat</strong> = in diesem Monat verdient, minus feste Ausgaben und Auszahlungen.
      Das <strong>Guthaben</strong> oben ist der tatsächliche Kontostand über alle Monate.
    </p>
  </div>
</section>

<section class="section">
  <div class="section__head">
    <h2>Feste Ausgaben</h2>
    <div class="section__action">
      <a class="btn btn--sm" href="<?= e(url('ausgabe-form', ['kind' => $childId])) ?>">＋ Hinzufügen</a>
    </div>
  </div>

  <?php if (!$activeExpenses): ?>
    <div class="card empty">
      <span class="empty__icon" aria-hidden="true">💳</span>
      Für <?= e($child['name']) ?> sind keine regelmäßigen Ausgaben hinterlegt.
    </div>
  <?php else: ?>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($activeExpenses as $expense): ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= e($expense['emoji']) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($expense['title']) ?></div>
                <div class="entry__meta">jeden <?= (int)$expense['day_of_month'] ?>. im Monat, seit <?= e(month_label($expense['start_month'])) ?></div>
              </div>
              <div class="entry__amount value-negative"><?= e(Money::format(-(int)$expense['amount_cents'])) ?></div>
              <a class="btn btn--ghost btn--sm" href="<?= e(url('ausgabe-form', ['id' => (int)$expense['id']])) ?>">Bearbeiten</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section__head">
    <h2>Buchungen im <?= e(month_label($month)) ?></h2>
    <span class="section__hint"><?= count($entries) ?> Einträge</span>
  </div>

  <?php if (!$entries): ?>
    <div class="card empty"><span class="empty__icon" aria-hidden="true">📄</span>Keine Buchungen in diesem Monat.</div>
  <?php else: ?>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($entries as $entry): ?>
          <?php $amount = (int)$entry['amount_cents']; ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= e(Ledger::emoji($entry['category'])) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($entry['description']) ?></div>
                <div class="entry__meta">
                  <?= e(Ledger::label($entry['category'])) ?> · <?= e(format_datetime($entry['booked_at'])) ?>
                  <?php if (!empty($entry['created_by_name'])): ?> · <?= e($entry['created_by_name']) ?><?php endif; ?>
                  <?php if (!empty($entry['updated_at'])): ?>
                    · geändert <?= e(format_datetime($entry['updated_at'])) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="entry__amount <?= $amount >= 0 ? 'value-positive' : 'value-negative' ?>">
                <?= e(Money::format($amount, true)) ?>
              </div>
              <a class="btn btn--ghost btn--sm" href="<?= e(url('buchung', $back + ['id' => (int)$entry['id']])) ?>"
                 title="Buchung bearbeiten">
                <span aria-hidden="true">✏️</span>
                <span class="visually-hidden">Buchung „<?= e($entry['description']) ?>“ bearbeiten</span>
              </a>
              <form method="post" action="<?= e(url('buchung-aktion')) ?>" class="inline-form"
                    data-confirm="<?= e(Ledger::deleteQuestion($entry)) ?>">
                <?= Csrf::field() ?>
                <?= $backFields ?>
                <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                <button class="btn btn--ghost btn--sm" type="submit" name="action" value="delete"
                        title="Buchung löschen">
                  <span aria-hidden="true">🗑</span>
                  <span class="visually-hidden">Buchung „<?= e($entry['description']) ?>“ löschen</span>
                </button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>
