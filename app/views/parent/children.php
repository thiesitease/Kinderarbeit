<?php
/** @var array $rows, $months; @var string $month */
defined('KINDERARBEIT') || exit;
?>

<div class="section__head">
  <h1>Kinder</h1>
  <div class="section__action">
    <?= View::render('partials/month-switch', ['month' => $month, 'months' => $months, 'target' => 'kinder']) ?>
  </div>
</div>

<div class="card card--flush">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kind</th>
          <th class="num">Guthaben</th>
          <th class="num">Verdient</th>
          <th class="num">Feste Ausgaben</th>
          <th class="num">Rest <?= e(month_label($month)) ?></th>
          <th class="num">Offen</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $child = $row['child']; ?>
          <tr>
            <td>
              <span class="row row--tight">
                <span class="avatar" style="--accent: <?= e($child['color']) ?>" aria-hidden="true"><?= e($child['emoji']) ?></span>
                <strong><?= e($child['name']) ?></strong>
              </span>
            </td>
            <td class="num<?= $row['balance'] < 0 ? ' value-negative' : '' ?>"><?= e(Money::format($row['balance'])) ?></td>
            <td class="num value-positive"><?= e(Money::format($row['summary']['earned'] + $row['summary']['bonus'])) ?></td>
            <td class="num<?= $row['summary']['expenses'] > 0 ? ' value-negative' : '' ?>"><?= e(Money::format(-$row['summary']['expenses'])) ?></td>
            <td class="num"><?= e(Money::format($row['summary']['net'], true)) ?></td>
            <td class="num">
              <?php if ($row['pendingCount'] > 0): ?>
                <span class="pill pill--pending"><?= (int)$row['pendingCount'] ?> · <?= e(Money::format($row['pendingAmount'])) ?></span>
              <?php else: ?>
                <span class="muted">–</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn--sm" href="<?= e(url('kind-detail', ['id' => (int)$child['id'], 'monat' => $month])) ?>">Öffnen</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-2 mt-3">
  <?php foreach ($rows as $row): ?>
    <?php $child = $row['child']; ?>
    <article class="card" style="--accent: <?= e($child['color']) ?>">
      <div class="row">
        <span class="avatar avatar--lg" aria-hidden="true"><?= e($child['emoji']) ?></span>
        <div>
          <div style="font-weight:650"><?= e($child['name']) ?></div>
          <div class="small muted">
            <?= (int)$row['approvedCount'] ?> <?= $row['approvedCount'] === 1 ? 'Aufgabe' : 'Aufgaben' ?>
            bestätigt im <?= e(month_label($month)) ?>
          </div>
        </div>
      </div>
      <div class="btn-row mt-2">
        <a class="btn btn--sm" href="<?= e(url('buchung', ['kind' => (int)$child['id'], 'art' => 'payout'])) ?>">💶 Auszahlen</a>
        <a class="btn btn--sm" href="<?= e(url('buchung', ['kind' => (int)$child['id'], 'art' => 'bonus'])) ?>">🎁 Bonus</a>
        <a class="btn btn--sm" href="<?= e(url('ausgabe-form', ['kind' => (int)$child['id']])) ?>">💳 Feste Ausgabe</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>
