<?php
/**
 * @var array $tasks, $pending, $expenses, $summary
 * @var int   $balance, $pendingAmount, $daysLeft
 * @var string $month
 * @var array $me
 */
defined('KINDERARBEIT') || exit;

$pendingTaskIds = [];
foreach ($pending as $item) {
    $pendingTaskIds[(int)$item['task_id']] = true;
}
$monthlyExpenses = array_sum(array_map(static fn (array $e): int => (int)$e['amount_cents'], $expenses));
$rest = $summary['net'];
?>

<section class="hero">
  <div class="hero__label">Dein Guthaben</div>
  <div class="hero__amount<?= $balance < 0 ? ' hero__amount--negative' : '' ?>"><?= e(Money::format($balance)) ?></div>
  <div class="hero__meta">
    <?php if ($pendingAmount > 0): ?>
      <span class="pill pill--pending">⏳ <?= e(Money::format($pendingAmount)) ?> warten auf Bestätigung</span>
    <?php endif; ?>
    <span class="pill">📅 <?= e(month_label($month)) ?>: <?= e(Money::format($summary['earned'])) ?> verdient</span>
    <?php if ($monthlyExpenses > 0): ?>
      <span class="pill">💳 <?= e(Money::format($monthlyExpenses)) ?> feste Ausgaben</span>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="section__head">
    <h2>Was du machen kannst</h2>
    <span class="section__hint"><?= count($tasks) ?> <?= count($tasks) === 1 ? 'Aufgabe' : 'Aufgaben' ?></span>
  </div>

  <?php if (!$tasks): ?>
    <div class="card empty">
      <span class="empty__icon" aria-hidden="true">🎉</span>
      Gerade gibt es keine Aufgaben. Frag Mama oder Papa, ob sie neue einstellen.
    </div>
  <?php else: ?>
    <div class="tasks">
      <?php foreach ($tasks as $task): ?>
        <?php $waiting = isset($pendingTaskIds[(int)$task['id']]); ?>
        <article class="task<?= $waiting ? ' task--waiting' : '' ?>">
          <div class="task__icon" aria-hidden="true"><?= e($task['emoji']) ?></div>
          <div class="task__body">
            <div class="task__title"><?= e($task['title']) ?></div>
            <?php if ($task['description'] !== ''): ?>
              <div class="task__note"><?= e($task['description']) ?></div>
            <?php endif; ?>
            <?php if ($waiting): ?>
              <div class="task__note">⏳ Wartet auf Bestätigung</div>
            <?php elseif ($task['kind'] === 'once'): ?>
              <div class="task__note">Einmalige Aufgabe</div>
            <?php elseif (!empty($task['last_approved_at'])): ?>
              <div class="task__note">Zuletzt: <?= e(format_date($task['last_approved_at'])) ?></div>
            <?php endif; ?>
          </div>
          <div class="task__side">
            <span class="task__amount"><?= e(Money::format((int)$task['amount_cents'])) ?></span>
            <?php if ($waiting): ?>
              <button class="btn btn--sm" type="button" disabled>Gemeldet</button>
            <?php else: ?>
              <form method="post" action="<?= e(url('kind-erledigt')) ?>" class="inline-form"
                    data-confirm="„<?= e($task['title']) ?>“ als erledigt melden?">
                <?= Csrf::field() ?>
                <input type="hidden" name="task" value="<?= (int)$task['id'] ?>">
                <button class="btn btn--done" type="submit" data-busy-label="…">Erledigt ✓</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($pending): ?>
  <section class="section">
    <div class="section__head">
      <h2>Wartet auf Mama oder Papa</h2>
      <span class="section__hint"><?= e(Money::format($pendingAmount)) ?> insgesamt</span>
    </div>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($pending as $item): ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= e($item['emoji']) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($item['title']) ?></div>
                <div class="entry__meta">Gemeldet <?= e(format_datetime($item['created_at'])) ?></div>
              </div>
              <div class="entry__amount"><?= e(Money::format((int)$item['amount_cents'])) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if (!empty($reachableParents)): ?>
      <?php
      $titel = array_map(static fn (array $i): string => $i['title'], $pending);
      $liste = count($titel) === 1
          ? $titel[0]
          : implode(', ', array_slice($titel, 0, -1)) . ' und ' . end($titel);
      ?>
      <div class="card mt-2">
        <div class="row">
          <div>
            <strong class="small">Bescheid sagen</strong>
            <div class="small muted">Öffnet WhatsApp mit fertigem Text – abgeschickt wird von dir.</div>
          </div>
          <span class="btn-row push-right">
            <?php foreach ($reachableParents as $elternteil): ?>
              <?php
              $text = 'Hallo ' . $elternteil['name'] . '! Ich habe ' . $liste . ' erledigt ('
                    . Money::format($pendingAmount) . '). Kannst du es bestätigen? ' . base_url();
              ?>
              <a class="btn btn--whatsapp btn--sm" target="_blank" rel="noopener"
                 href="<?= e(Phone::waLink($elternteil['phone'], $text)) ?>">
                <span aria-hidden="true"><?= e($elternteil['emoji']) ?></span> <?= e($elternteil['name']) ?>
              </a>
            <?php endforeach; ?>
          </span>
        </div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="section">
  <div class="section__head">
    <h2><?= e(month_label($month)) ?></h2>
    <span class="section__hint">noch <?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'Tag' : 'Tage' ?> im Monat</span>
  </div>
  <div class="card">
    <div class="stats">
      <div class="stat">
        <div class="stat__label">Verdient</div>
        <div class="stat__value value-positive"><?= e(Money::format($summary['earned'] + $summary['bonus'])) ?></div>
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
        <div class="stat__value"><?= e(Money::format($rest, true)) ?></div>
      </div>
    </div>

    <?php if ($expenses): ?>
      <p class="small muted mt-2 mb-1">
        Jeden Monat gehen automatisch ab – ändern können das nur Mama und Papa:
      </p>
      <ul class="list">
        <?php foreach ($expenses as $expense): ?>
          <li>
            <div class="entry" style="padding-left:0;padding-right:0">
              <div class="entry__icon" aria-hidden="true"><?= e($expense['emoji']) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($expense['title']) ?></div>
                <div class="entry__meta">jeden <?= (int)$expense['day_of_month'] ?>. im Monat</div>
              </div>
              <div class="entry__amount value-negative"><?= e(Money::format(-(int)$expense['amount_cents'])) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <p class="mt-2"><a class="btn btn--block" href="<?= e(url('kind-konto')) ?>">Mein Konto ansehen</a></p>
  </div>
</section>

<?= View::render('partials/push-toggle') ?>
