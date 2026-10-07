<?php
/**
 * Monatssaldo: alle Kinder nebeneinander, Monat für Monat.
 *
 * @var array $children  Kinder in ihrer Reihenfolge
 * @var array $monate    Monat => Kind-ID => Zahlen (Ledger::monthlyTotalsByChild)
 * @var array $balances  Kind-ID => Guthaben über alle Monate
 */
defined('KINDERARBEIT') || exit;

$jetzt = current_month();
?>

<div class="section__head">
  <h1>Monatssaldo</h1>
  <span class="section__hint">Endsaldo je Monat und Kind</span>
</div>

<?php if (!$monate): ?>
  <div class="card empty">
    <span class="empty__icon" aria-hidden="true">📅</span>
    Noch kein Monat mit Buchungen.
  </div>
<?php else: ?>
  <div class="card card--flush">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="col-monat">Monat</th>
            <?php foreach ($children as $child): ?>
              <th class="num"><?= e($child['emoji']) ?> <?= e($child['name']) ?></th>
            <?php endforeach; ?>
            <th class="num">Zusammen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($monate as $monat => $zeile): ?>
            <?php $summe = 0; ?>
            <tr>
              <td class="col-monat">
                <a href="<?= e(url('verlauf', ['monat' => $monat])) ?>"><?= e(month_label((string)$monat)) ?></a>
                <?php if ($monat === $jetzt): ?><span class="pill tiny">läuft noch</span><?php endif; ?>
              </td>

              <?php foreach ($children as $child): ?>
                <?php
                $kindId = (int)$child['id'];
                $zahlen = $zeile[$kindId] ?? null;
                $saldo  = (int)($zahlen['net'] ?? 0);
                $summe += $saldo;

                // Was den Saldo ausmacht, steht im Tooltip – die Tabelle bleibt schmal.
                $teile = [];
                if ($zahlen !== null) {
                    if ($zahlen['earned'] !== 0)      { $teile[] = 'verdient ' . Money::format($zahlen['earned']); }
                    if ($zahlen['bonus'] !== 0)       { $teile[] = 'Bonus ' . Money::format($zahlen['bonus']); }
                    if ($zahlen['expenses'] !== 0)    { $teile[] = 'feste Ausgaben ' . Money::format(-$zahlen['expenses']); }
                    if ($zahlen['payouts'] !== 0)     { $teile[] = 'ausgezahlt ' . Money::format(-$zahlen['payouts']); }
                    if ($zahlen['corrections'] !== 0) { $teile[] = 'Korrekturen ' . Money::format($zahlen['corrections'], true); }
                }
                ?>
                <td class="num <?= $saldo > 0 ? 'value-positive' : ($saldo < 0 ? 'value-negative' : 'muted') ?>">
                  <?php if ($zahlen === null): ?>
                    <span class="muted">–</span>
                  <?php else: ?>
                    <a href="<?= e(url('kind-detail', ['id' => $kindId, 'monat' => $monat])) ?>"
                       title="<?= e($teile ? implode(' · ', $teile) : 'nichts gebucht') ?>">
                      <?= e(Money::format($saldo, true)) ?>
                    </a>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>

              <td class="num <?= $summe > 0 ? 'value-positive' : ($summe < 0 ? 'value-negative' : 'muted') ?>">
                <?= e(Money::format($summe, true)) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th class="col-monat">Guthaben gesamt</th>
            <?php $gesamt = 0; ?>
            <?php foreach ($children as $child): ?>
              <?php $stand = (int)($balances[(int)$child['id']] ?? 0); $gesamt += $stand; ?>
              <th class="num <?= $stand < 0 ? 'value-negative' : ($stand > 0 ? 'value-positive' : 'muted') ?>">
                <?= e(Money::format($stand)) ?>
              </th>
            <?php endforeach; ?>
            <th class="num <?= $gesamt < 0 ? 'value-negative' : ($gesamt > 0 ? 'value-positive' : 'muted') ?>">
              <?= e(Money::format($gesamt)) ?>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <p class="field__hint">
    Jeder Monat fängt am 1. bei 0 an; am Monatsende gehen die festen Ausgaben ab.
    Was dann unter dem Strich steht, ist der Saldo dieses Monats – ob plus oder minus.
    Ein Klick auf einen Betrag zeigt den Monat beim Kind, ein Klick auf den Monat
    alle Buchungen. <strong>Guthaben gesamt</strong> unten ist der Kontostand über
    alle Monate hinweg.
  </p>
<?php endif; ?>
