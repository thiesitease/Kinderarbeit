<?php
/**
 * @var array|null $entry
 * @var array      $children, $balances, $back
 * @var int        $presetChild
 * @var string     $presetKind, $cancel
 */
defined('KINDERARBEIT') || exit;

$kinds = [
    'payout'     => ['💶', 'Auszahlung',  'Bargeld ausgezahlt – der Betrag wird vom Guthaben abgezogen.'],
    'bonus'      => ['🎁', 'Bonus',       'Zusätzliche Gutschrift, z. B. für besonderen Einsatz.'],
    'charge'     => ['➖', 'Abzug',       'Einmaliger Abzug, z. B. für eine gemeinsame Anschaffung.'],
    'correction' => ['✏️', 'Korrektur',   'Gutschrift zum Ausgleich eines Fehlers.'],
];
$presetKind = array_key_exists($presetKind, $kinds) ? $presetKind : 'payout';

$isEdit = $entry !== null;
// Nur von Hand erfasste Buchungen lassen sich einem anderen Kind oder einer
// anderen Art zuordnen; alles andere haengt an einer Aufgabe oder festen Ausgabe.
$free   = !$isEdit || Ledger::isManual($entry);
$amount = $isEdit ? Money::forInput(abs((int)$entry['amount_cents'])) : '';
$date   = $isEdit ? substr((string)$entry['booked_at'], 0, 10) : date('Y-m-d');

// Woher der Aufruf kam, wird durch das Formular gereicht.
$backFields = '';
foreach ($back as $key => $value) {
    $backFields .= '<input type="hidden" name="' . e($key) . '" value="' . e((string)$value) . '">';
}
?>

<div style="max-width:34rem;margin:0 auto">
  <div class="section__head">
    <h1><?= $isEdit ? 'Buchung bearbeiten' : 'Buchung erfassen' ?></h1>
  </div>

  <form method="post" action="<?= e(url('buchung-save')) ?>" class="card">
    <?= Csrf::field() ?>
    <?= $backFields ?>
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <?php if ($free): ?>
        <label class="field__label" for="child_id">Für wen?</label>
        <select class="select" id="child_id" name="child_id" required>
          <option value="">Bitte wählen …</option>
          <?php foreach ($children as $child): ?>
            <option value="<?= (int)$child['id'] ?>"<?= $presetChild === (int)$child['id'] ? ' selected' : '' ?>>
              <?= e($child['emoji']) ?> <?= e($child['name']) ?> · Guthaben <?= e(Money::format($balances[(int)$child['id']] ?? 0)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <span class="field__label">Für wen?</span>
        <p style="margin:0">
          <?= e($entry['child_emoji']) ?> <?= e($entry['child_name']) ?>
          <span class="muted">· Guthaben <?= e(Money::format($balances[(int)$entry['child_id']] ?? 0)) ?></span>
        </p>
      <?php endif; ?>
    </div>

    <div class="field">
      <span class="field__label">Art der Buchung</span>
      <?php if ($free): ?>
        <div class="segmented">
          <?php foreach ($kinds as $value => [$icon, $label, $hint]): ?>
            <input type="radio" id="kind-<?= e($value) ?>" name="kind" value="<?= e($value) ?>"
                   <?= $presetKind === $value ? ' checked' : '' ?>>
            <label for="kind-<?= e($value) ?>"><?= e($icon) ?> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <p class="field__hint">
          <strong>Auszahlung</strong> und <strong>Abzug</strong> verringern das Guthaben,
          <strong>Bonus</strong> und <strong>Korrektur</strong> erhöhen es.
        </p>
      <?php else: ?>
        <p style="margin:0"><?= e(Ledger::emoji($entry['category'])) ?> <?= e(Ledger::label($entry['category'])) ?></p>
        <p class="field__hint">
          <?php if ($entry['ref_type'] === 'expense'): ?>
            Diese Buchung stammt aus einer festen Ausgabe. Betrag, Datum und Text lassen sich
            hier ändern – die monatliche Abbuchung selbst wird unter <strong>Ausgaben</strong> gepflegt.
          <?php else: ?>
            Diese Buchung gehört zu einer bestätigten Aufgabe. Der Betrag wird auch in der
            Meldung mitgeführt, damit beides zusammenpasst.
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="field-row">
      <div class="field">
        <label class="field__label" for="amount">Betrag</label>
        <input class="input input--amount tnum" type="text" id="amount" name="amount"
               inputmode="decimal" required placeholder="10,00" value="<?= e($amount) ?>"
               <?= $isEdit ? '' : 'autofocus' ?>>
        <p class="field__hint">Immer als positive Zahl eingeben – die Richtung ergibt sich aus der Art.</p>
      </div>
      <div class="field">
        <label class="field__label" for="datum">Datum</label>
        <input class="input" type="date" id="datum" name="datum" value="<?= e($date) ?>">
        <p class="field__hint">Bestimmt, in welchem Monat die Buchung zählt.</p>
      </div>
    </div>

    <div class="field">
      <label class="field__label" for="description">Verwendungszweck <span class="muted">(freiwillig)</span></label>
      <input class="input" type="text" id="description" name="description" maxlength="120"
             placeholder="z. B. Taschengeld bar ausgezahlt"
             value="<?= e($isEdit ? $entry['description'] : '') ?>">
    </div>

    <div class="field">
      <label class="check">
        <input type="checkbox" name="confirm_negative" value="1">
        <span>Buchung auch zulassen, wenn das Konto dadurch ins Minus geht.</span>
      </label>
    </div>

    <div class="btn-row mt-2">
      <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Speichern' : 'Buchen' ?></button>
      <a class="btn btn--ghost" href="<?= e($cancel) ?>">Abbrechen</a>
    </div>
  </form>

  <?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('buchung-aktion')) ?>" class="mt-2"
          data-confirm="<?= e(Ledger::deleteQuestion($entry)) ?>">
      <?= Csrf::field() ?>
      <?= $backFields ?>
      <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
      <button class="btn btn--danger btn--block" type="submit" name="action" value="delete">Buchung löschen</button>
    </form>

    <p class="field__hint" style="text-align:center">
      Erfasst von <?= e($entry['created_by_name'] ?? '–') ?>, <?= e(format_datetime($entry['created_at'])) ?>.
      <?php if (!empty($entry['updated_at'])): ?>
        Zuletzt geändert von <?= e($entry['updated_by_name'] ?? '–') ?>, <?= e(format_datetime($entry['updated_at'])) ?>.
      <?php endif; ?>
    </p>
  <?php endif; ?>
</div>
