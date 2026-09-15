<?php
/**
 * @var array  $pending, $overview, $recent, $defaultPinUsers
 * @var string $month
 * @var int    $daysLeft
 */
defined('KINDERARBEIT') || exit;

$pendingTotal = array_sum(array_map(static fn (array $p): int => (int)$p['amount_cents'], $pending));
?>

<?php if (!empty($bescheid)): ?>
  <?php
  $kind    = $bescheid['child'];
  $text    = 'Hallo ' . $kind['name'] . '! Ich habe „' . $bescheid['title'] . '“ bestätigt, '
           . Money::format($bescheid['amount']) . ' sind auf deinem Konto. '
           . 'Dein Guthaben: ' . Money::format($bescheid['balance']) . '. ' . base_url();
  $link    = Phone::waLink($kind['phone'], $text);
  ?>
  <div class="card card--accent mb-2" style="--accent: <?= e($kind['color']) ?>">
    <div class="row">
      <span class="avatar avatar--lg" aria-hidden="true"><?= e($kind['emoji']) ?></span>
      <div class="row__text">
        <div style="font-weight:650"><?= e($kind['name']) ?> Bescheid geben?</div>
        <div class="small muted">
          „<?= e($bescheid['title']) ?>“ ist bestätigt – <?= e($kind['name']) ?> weiß es noch nicht.
        </div>
      </div>
      <a class="btn btn--whatsapp push-right" target="_blank" rel="noopener" href="<?= e($link) ?>">
        <span aria-hidden="true">💬</span> Per WhatsApp
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($defaultPinUsers): ?>
  <div class="notice mb-2">
    <span aria-hidden="true">🔐</span>
    <span>
      <strong>Standard-PIN noch aktiv</strong> bei
      <?= e(implode(', ', array_column($defaultPinUsers, 'name'))) ?>.
      Beim nächsten Anmelden wird automatisch nach einer eigenen PIN gefragt –
      unter <a href="<?= e(url('familie')) ?>">Familie</a> lässt sie sich auch direkt setzen.
    </span>
  </div>
<?php endif; ?>

<section class="section">
  <div class="section__head">
    <h1>Wiedervorlage</h1>
    <span class="section__hint">
      <?php if ($pending): ?>
        <?= count($pending) ?> <?= count($pending) === 1 ? 'Meldung' : 'Meldungen' ?> ·
        <?= e(Money::format($pendingTotal)) ?>
      <?php else: ?>
        nichts offen
      <?php endif; ?>
    </span>
  </div>

  <?php if (!$pending): ?>
    <div class="card empty">
      <span class="empty__icon" aria-hidden="true">✅</span>
      Alles erledigt – es wartet keine Meldung auf eure Bestätigung.
    </div>
  <?php else: ?>
    <?php foreach ($pending as $item): ?>
      <?= View::render('partials/review-item', ['item' => $item, 'back' => 'eltern']) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section__head">
    <h2>Konten im <?= e(month_label($month)) ?></h2>
    <span class="section__hint">noch <?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'Tag' : 'Tage' ?></span>
    <div class="section__action">
      <a class="btn btn--sm" href="<?= e(url('buchung')) ?>">＋ Buchung</a>
    </div>
  </div>

  <div class="grid-2">
    <?php foreach ($overview as $row): ?>
      <?php $child = $row['child']; ?>
      <article class="card" style="--accent: <?= e($child['color']) ?>">
        <div class="row">
          <span class="avatar avatar--lg" aria-hidden="true"><?= e($child['emoji']) ?></span>
          <div>
            <div style="font-weight:650;font-size:1.05rem"><?= e($child['name']) ?></div>
            <div class="small muted">
              <?php if ($row['pendingCount'] > 0): ?>
                ⏳ <?= (int)$row['pendingCount'] ?> offen · <?= e(Money::format($row['pendingAmount'])) ?>
              <?php else: ?>
                keine offenen Meldungen
              <?php endif; ?>
            </div>
          </div>
          <div class="push-right" style="text-align:right">
            <div class="small muted">Guthaben</div>
            <div class="tnum" style="font-size:1.35rem;font-weight:700<?= $row['balance'] < 0 ? ';color:var(--negative)' : '' ?>">
              <?= e(Money::format($row['balance'])) ?>
            </div>
          </div>
        </div>

        <div class="stats mt-2">
          <div class="stat">
            <div class="stat__label">Verdient</div>
            <div class="stat__value value-positive"><?= e(Money::format($row['summary']['earned'] + $row['summary']['bonus'])) ?></div>
          </div>
          <div class="stat">
            <div class="stat__label">Feste Ausgaben</div>
            <div class="stat__value<?= $row['summary']['expenses'] > 0 ? ' value-negative' : '' ?>"><?= e(Money::format(-$row['summary']['expenses'])) ?></div>
          </div>
          <div class="stat stat--rest stat--wide">
            <div class="stat__label">Rest im Monat</div>
            <div class="stat__value"><?= e(Money::format($row['summary']['net'], true)) ?></div>
          </div>
        </div>

        <div class="btn-row mt-2">
          <a class="btn btn--sm" href="<?= e(url('kind-detail', ['id' => (int)$child['id']])) ?>">Details</a>
          <a class="btn btn--sm" href="<?= e(url('buchung', ['kind' => (int)$child['id'], 'art' => 'payout'])) ?>">Auszahlen</a>
          <?php if ($row['monthlyExpense'] > 0): ?>
            <span class="pill push-right">💳 <?= e(Money::format($row['monthlyExpense'])) ?>/Monat</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($recent): ?>
  <section class="section">
    <div class="section__head">
      <h2>Zuletzt entschieden</h2>
      <div class="section__action"><a class="small" href="<?= e(url('verlauf')) ?>">Gesamten Verlauf ansehen →</a></div>
    </div>
    <div class="card card--flush">
      <ul class="list">
        <?php foreach ($recent as $item): ?>
          <?php $approved = $item['status'] === 'approved'; ?>
          <li>
            <div class="entry">
              <div class="entry__icon" aria-hidden="true"><?= e($item['child_emoji']) ?></div>
              <div class="entry__body">
                <div class="entry__title"><?= e($item['child_name']) ?> · <?= e($item['title']) ?></div>
                <div class="entry__meta">
                  <?= $approved ? 'Bestätigt' : 'Abgelehnt' ?> von <?= e($item['decided_by_name'] ?? '–') ?>,
                  <?= e(format_datetime($item['decided_at'])) ?>
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
                  <input type="hidden" name="back" value="eltern">
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

<?= View::render('partials/push-toggle') ?>
