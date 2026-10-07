<?php
/**
 * Monat fuer Monat mit dem Saldo des jeweiligen Monats.
 *
 * Jeder Monat steht fuer sich: am 1. faengt die Rechnung bei null an, am
 * Monatsende gehen die festen Ausgaben ab. Was dann unter dem Strich steht,
 * ist der Saldo – ob plus oder minus.
 *
 * @var array  $monate  Zeilen aus Ledger::monthlyTotals()
 * @var string $target  Seite, auf die ein Monat verlinkt ('' = keine Links)
 * @var array  $extra   zusaetzliche Parameter fuer diesen Link
 */
defined('KINDERARBEIT') || exit;

$target = $target ?? '';
$extra  = $extra  ?? [];
$jetzt  = current_month();
?>

<?php if (!$monate): ?>
  <div class="card empty">
    <span class="empty__icon" aria-hidden="true">📅</span>
    Noch kein Monat mit Buchungen.
  </div>
<?php else: ?>
  <div class="card card--flush">
    <ul class="list">
      <?php foreach ($monate as $monat): ?>
        <?php
        $saldo  = (int)$monat['net'];
        $laeuft = $monat['month'] === $jetzt;

        // Nur nennen, was es in diesem Monat auch gab.
        $teile = [];
        if ($monat['earned'] !== 0)      { $teile[] = 'verdient ' . Money::format($monat['earned']); }
        if ($monat['bonus'] !== 0)       { $teile[] = 'Bonus ' . Money::format($monat['bonus']); }
        if ($monat['expenses'] !== 0)    { $teile[] = 'feste Ausgaben ' . Money::format(-$monat['expenses']); }
        if ($monat['payouts'] !== 0)     { $teile[] = 'ausgezahlt ' . Money::format(-$monat['payouts']); }
        if ($monat['corrections'] !== 0) { $teile[] = 'Korrekturen ' . Money::format($monat['corrections'], true); }
        ?>
        <li>
          <div class="entry">
            <div class="entry__icon" aria-hidden="true"><?= $laeuft ? '⏳' : '📅' ?></div>
            <div class="entry__body">
              <div class="entry__title">
                <?php if ($target !== ''): ?>
                  <a href="<?= e(url($target, $extra + ['monat' => $monat['month']])) ?>"><?= e(month_label($monat['month'])) ?></a>
                <?php else: ?>
                  <?= e(month_label($monat['month'])) ?>
                <?php endif; ?>
                <?php if ($laeuft): ?><span class="pill tiny">läuft noch</span><?php endif; ?>
              </div>
              <div class="entry__meta"><?= e($teile ? implode(' · ', $teile) : 'nichts gebucht') ?></div>
            </div>
            <div class="entry__amount <?= $saldo > 0 ? 'value-positive' : ($saldo < 0 ? 'value-negative' : 'muted') ?>">
              <?= e(Money::format($saldo, true)) ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
