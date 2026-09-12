<?php
/** @var array $completions */
defined('KINDERARBEIT') || exit;

$labels = ['pending' => 'Wartet', 'approved' => 'Bestätigt', 'rejected' => 'Abgelehnt'];
$pills  = ['pending' => 'pill--pending', 'approved' => 'pill--positive', 'rejected' => 'pill--negative'];
$icons  = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '✖️'];
?>

<div class="section__head">
  <h1>Mein Verlauf</h1>
  <span class="section__hint">alle gemeldeten Aufgaben</span>
</div>

<?php if (!$completions): ?>
  <div class="card empty">
    <span class="empty__icon" aria-hidden="true">📋</span>
    Du hast noch keine Aufgabe gemeldet. Leg los!
  </div>
<?php else: ?>
  <div class="card card--flush">
    <ul class="list">
      <?php foreach ($completions as $item): ?>
        <?php $status = (string)$item['status']; ?>
        <li>
          <div class="entry">
            <div class="entry__icon" aria-hidden="true"><?= e($item['emoji']) ?></div>
            <div class="entry__body">
              <div class="entry__title"><?= e($item['title']) ?></div>
              <div class="entry__meta">
                Gemeldet <?= e(format_datetime($item['created_at'])) ?>
                <?php if ($item['decided_at']): ?>
                  · <?= e($labels[$status]) ?> von <?= e($item['decided_by_name'] ?? 'den Eltern') ?>
                  <?= e(format_datetime($item['decided_at'])) ?>
                <?php endif; ?>
              </div>
              <?php if (!empty($item['decision_note'])): ?>
                <div class="entry__meta">💬 <?= e($item['decision_note']) ?></div>
              <?php endif; ?>
            </div>
            <div style="text-align:right">
              <div class="entry__amount <?= $status === 'approved' ? 'value-positive' : ($status === 'rejected' ? 'muted' : '') ?>">
                <?= e(Money::format((int)$item['amount_cents'])) ?>
              </div>
              <span class="pill <?= e($pills[$status]) ?> tiny" style="margin-top:.2rem">
                <?= e($icons[$status]) ?> <?= e($labels[$status]) ?>
              </span>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
