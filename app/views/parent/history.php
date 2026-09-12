<?php
/** @var array $entries, $children, $months, $decisions; @var int $childId; @var string $month */
defined('KINDERARBEIT') || exit;

$income  = array_sum(array_map(static fn (array $e): int => max(0, (int)$e['amount_cents']), $entries));
$outflow = array_sum(array_map(static fn (array $e): int => min(0, (int)$e['amount_cents']), $entries));
?>

<div class="section__head">
  <h1>Verlauf</h1>
  <span class="section__hint"><?= count($entries) ?> Buchungen</span>
  <div class="section__action">
    <a class="btn btn--sm" href="<?= e(url('buchung')) ?>">＋ Buchung</a>
  </div>
</div>

<form method="get" action="./" class="card mb-2">
  <input type="hidden" name="p" value="verlauf">
  <div class="row">
    <label class="visually-hidden" for="filter-kind">Kind</label>
    <select class="select" id="filter-kind" name="kind" data-autosubmit style="width:auto;min-width:11rem">
      <option value="0">Alle Kinder</option>
      <?php foreach ($children as $child): ?>
        <option value="<?= (int)$child['id'] ?>"<?= $childId === (int)$child['id'] ? ' selected' : '' ?>>
          <?= e($child['emoji']) ?> <?= e($child['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="filter-monat">Monat</label>
    <select class="select" id="filter-monat" name="monat" data-autosubmit style="width:auto;min-width:11rem">
      <option value="alle"<?= $month === 'alle' ? ' selected' : '' ?>>Alle Monate</option>
      <?php foreach ($months as $option): ?>
        <option value="<?= e($option) ?>"<?= $option === $month ? ' selected' : '' ?>><?= e(month_label($option)) ?></option>
      <?php endforeach; ?>
    </select>

    <noscript><button class="btn btn--sm" type="submit">Filtern</button></noscript>

    <span class="push-right row row--tight">
      <span class="pill pill--positive">+ <?= e(Money::format($income)) ?></span>
      <span class="pill pill--negative">− <?= e(Money::format(abs($outflow))) ?></span>
      <span class="pill">= <?= e(Money::format($income + $outflow, true)) ?></span>
    </span>
  </div>
</form>

<?php if (!$entries): ?>
  <div class="card empty">
    <span class="empty__icon" aria-hidden="true">🕘</span>
    Für diese Auswahl gibt es keine Buchungen.
  </div>
<?php else: ?>
  <div class="card card--flush">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Kind</th>
            <th>Buchung</th>
            <th>Art</th>
            <th>Erfasst von</th>
            <th class="num">Betrag</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $entry): ?>
            <?php $amount = (int)$entry['amount_cents']; ?>
            <tr>
              <td class="muted small"><?= e(format_date($entry['booked_at'])) ?></td>
              <td>
                <span class="row row--tight">
                  <span class="avatar" style="--accent: <?= e($entry['child_color']) ?>;width:1.5rem;height:1.5rem;font-size:.85rem" aria-hidden="true"><?= e($entry['child_emoji']) ?></span>
                  <?= e($entry['child_name']) ?>
                </span>
              </td>
              <td><?= e($entry['description']) ?></td>
              <td class="muted small"><?= e(Ledger::emoji($entry['category'])) ?> <?= e(Ledger::label($entry['category'])) ?></td>
              <td class="muted small"><?= e($entry['created_by_name'] ?? '–') ?></td>
              <td class="num <?= $amount >= 0 ? 'value-positive' : 'value-negative' ?>"><?= e(Money::format($amount, true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($decisions): ?>
  <section class="section">
    <div class="section__head">
      <h2>Entschiedene Meldungen</h2>
      <span class="section__hint">Bestätigungen und Ablehnungen</span>
    </div>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($decisions as $item): ?>
          <?php $approved = $item['status'] === 'approved'; ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= $approved ? '✅' : '✖️' ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($item['child_name']) ?> · <?= e($item['title']) ?></div>
                <div class="entry__meta">
                  <?= $approved ? 'Bestätigt' : 'Abgelehnt' ?> von <?= e($item['decided_by_name'] ?? '–') ?>,
                  <?= e(format_datetime($item['decided_at'])) ?>
                  <?php if (!empty($item['decision_note'])): ?> · 💬 <?= e($item['decision_note']) ?><?php endif; ?>
                </div>
              </div>
              <div class="entry__amount <?= $approved ? 'value-positive' : 'muted' ?>">
                <?= e(Money::format((int)$item['amount_cents'])) ?>
              </div>
              <?php if ($approved): ?>
                <form method="post" action="<?= e(url('pruefen')) ?>" class="inline-form"
                      data-confirm="Bestätigung zurücknehmen? Der Betrag wird wieder abgezogen.">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <input type="hidden" name="back" value="verlauf">
                  <button class="btn btn--ghost btn--sm" type="submit" name="action" value="revoke"
                          title="Bestätigung zurücknehmen">
                    <span aria-hidden="true">↩︎</span>
                    <span class="visually-hidden">Bestätigung zurücknehmen</span>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
