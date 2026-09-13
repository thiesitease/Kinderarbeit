<?php
/**
 * @var int    $balance, $pendingAmount, $daysLeft
 * @var array  $summary, $entries, $expenses, $months
 * @var string $month
 */
defined('KINDERARBEIT') || exit;

$isCurrentMonth = $month === current_month();
?>

<section class="hero">
  <div class="hero__label">Guthaben gesamt</div>
  <div class="hero__amount<?= $balance < 0 ? ' hero__amount--negative' : '' ?>"><?= e(Money::format($balance)) ?></div>
  <div class="hero__meta">
    <?php if ($pendingAmount > 0): ?>
      <span class="pill pill--pending">⏳ <?= e(Money::format($pendingAmount)) ?> noch nicht bestätigt</span>
    <?php else: ?>
      <span class="pill pill--positive">✓ Alles bestätigt</span>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="section__head">
    <h2><?= e(month_label($month)) ?></h2>
    <?php if ($isCurrentMonth): ?>
      <span class="section__hint">noch <?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'Tag' : 'Tage' ?></span>
    <?php endif; ?>
    <div class="section__action">
      <?= View::render('partials/month-switch', ['month' => $month, 'months' => $months, 'target' => 'kind-konto']) ?>
    </div>
  </div>

  <div class="card">
    <div class="stats">
      <div class="stat">
        <div class="stat__label">Verdient</div>
        <div class="stat__value value-positive"><?= e(Money::format($summary['earned'])) ?></div>
      </div>
      <?php if ($summary['bonus'] > 0): ?>
        <div class="stat">
          <div class="stat__label">Bonus</div>
          <div class="stat__value value-positive"><?= e(Money::format($summary['bonus'])) ?></div>
        </div>
      <?php endif; ?>
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
        <div class="stat__label"><?= $isCurrentMonth ? 'Rest diesen Monat' : 'Rest im Monat' ?></div>
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
    <h2>Alle Buchungen</h2>
    <span class="section__hint"><?= count($entries) ?> im <?= e(month_label($month)) ?></span>
  </div>

  <?php if (!$entries): ?>
    <div class="card empty">
      <span class="empty__icon" aria-hidden="true">🐷</span>
      In diesem Monat gab es noch keine Buchung.
    </div>
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
                  <?php if (!empty($entry['updated_at'])): ?>
                    · geändert <?= e(format_datetime($entry['updated_at'])) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="entry__amount <?= $amount >= 0 ? 'value-positive' : 'value-negative' ?>">
                <?= e(Money::format($amount, true)) ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>
