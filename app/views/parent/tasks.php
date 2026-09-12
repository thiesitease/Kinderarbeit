<?php
/** @var array $tasks, $children */
defined('KINDERARBEIT') || exit;

$active   = array_filter($tasks, static fn (array $t): bool => (int)$t['is_active'] === 1);
$paused   = array_filter($tasks, static fn (array $t): bool => (int)$t['is_active'] !== 1);
$total    = array_sum(array_map(static fn (array $t): int => (int)$t['amount_cents'], $active));
?>

<div class="section__head">
  <h1>Aufgaben</h1>
  <span class="section__hint"><?= count($active) ?> aktiv · zusammen <?= e(Money::format($total)) ?></span>
  <div class="section__action">
    <a class="btn btn--primary btn--sm" href="<?= e(url('aufgabe-form')) ?>">＋ Neue Aufgabe</a>
  </div>
</div>

<?php if (!$active): ?>
  <div class="card empty">
    <span class="empty__icon" aria-hidden="true">📋</span>
    Noch keine Aufgabe angelegt.
    <p class="mt-2"><a class="btn btn--primary" href="<?= e(url('aufgabe-form')) ?>">Erste Aufgabe anlegen</a></p>
  </div>
<?php else: ?>
  <div class="tasks">
    <?php foreach ($active as $task): ?>
      <article class="task">
        <div class="task__icon" aria-hidden="true"><?= e($task['emoji']) ?></div>
        <div class="task__body">
          <div class="task__title"><?= e($task['title']) ?></div>
          <div class="task__note">
            <?php if ($task['assigned_name']): ?>
              nur für <?= e($task['assigned_name']) ?>
            <?php else: ?>
              für alle Kinder
            <?php endif; ?>
            <?= $task['kind'] === 'once' ? ' · einmalig' : ' · beliebig oft' ?>
            <?php if ($task['description'] !== ''): ?>
              · <?= e($task['description']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="task__side">
          <span class="task__amount"><?= e(Money::format((int)$task['amount_cents'])) ?></span>
          <a class="btn btn--sm" href="<?= e(url('aufgabe-form', ['id' => (int)$task['id']])) ?>">Bearbeiten</a>
          <form method="post" action="<?= e(url('aufgabe-aktion')) ?>" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
            <button class="btn btn--ghost btn--sm" type="submit" name="action" value="up" title="Nach oben" aria-label="Nach oben schieben">↑</button>
          </form>
          <form method="post" action="<?= e(url('aufgabe-aktion')) ?>" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
            <button class="btn btn--ghost btn--sm" type="submit" name="action" value="down" title="Nach unten" aria-label="Nach unten schieben">↓</button>
          </form>
          <form method="post" action="<?= e(url('aufgabe-aktion')) ?>" class="inline-form"
                data-confirm="„<?= e($task['title']) ?>“ pausieren? Die Kinder sehen sie dann nicht mehr.">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
            <button class="btn btn--ghost btn--sm" type="submit" name="action" value="pause">Pausieren</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($paused): ?>
  <section class="section">
    <div class="section__head">
      <h2>Pausiert</h2>
      <span class="section__hint">für die Kinder nicht sichtbar</span>
    </div>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($paused as $task): ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= e($task['emoji']) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($task['title']) ?></div>
                <div class="entry__meta"><?= e(Money::format((int)$task['amount_cents'])) ?></div>
              </div>
              <form method="post" action="<?= e(url('aufgabe-aktion')) ?>" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                <button class="btn btn--sm" type="submit" name="action" value="activate">Wieder anzeigen</button>
              </form>
              <form method="post" action="<?= e(url('aufgabe-aktion')) ?>" class="inline-form"
                    data-confirm="„<?= e($task['title']) ?>“ endgültig löschen?">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                <button class="btn btn--danger btn--sm" type="submit" name="action" value="delete">Löschen</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
