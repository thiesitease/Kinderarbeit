<?php
/** @var array $children, $balances; @var int $presetChild; @var string $presetKind */
defined('KINDERARBEIT') || exit;

$kinds = [
    'payout'     => ['💶', 'Auszahlung',  'Bargeld ausgezahlt – der Betrag wird vom Guthaben abgezogen.'],
    'bonus'      => ['🎁', 'Bonus',       'Zusätzliche Gutschrift, z. B. für besonderen Einsatz.'],
    'charge'     => ['➖', 'Abzug',       'Einmaliger Abzug, z. B. für eine gemeinsame Anschaffung.'],
    'correction' => ['✏️', 'Korrektur',   'Gutschrift zum Ausgleich eines Fehlers.'],
];
$presetKind = array_key_exists($presetKind, $kinds) ? $presetKind : 'payout';
?>

<div style="max-width:34rem;margin:0 auto">
  <div class="section__head">
    <h1>Buchung erfassen</h1>
  </div>

  <form method="post" action="<?= e(url('buchung-save')) ?>" class="card">
    <?= Csrf::field() ?>

    <div class="field">
      <label class="field__label" for="child_id">Für wen?</label>
      <select class="select" id="child_id" name="child_id" required>
        <option value="">Bitte wählen …</option>
        <?php foreach ($children as $child): ?>
          <option value="<?= (int)$child['id'] ?>"<?= $presetChild === (int)$child['id'] ? ' selected' : '' ?>>
            <?= e($child['emoji']) ?> <?= e($child['name']) ?> · Guthaben <?= e(Money::format($balances[(int)$child['id']] ?? 0)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <span class="field__label">Art der Buchung</span>
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
    </div>

    <div class="field">
      <label class="field__label" for="amount">Betrag</label>
      <input class="input input--amount tnum" type="text" id="amount" name="amount"
             inputmode="decimal" required placeholder="10,00" autofocus>
      <p class="field__hint">Immer als positive Zahl eingeben – die Richtung ergibt sich aus der Art.</p>
    </div>

    <div class="field">
      <label class="field__label" for="description">Verwendungszweck <span class="muted">(freiwillig)</span></label>
      <input class="input" type="text" id="description" name="description" maxlength="120"
             placeholder="z. B. Taschengeld bar ausgezahlt">
    </div>

    <div class="field">
      <label class="check">
        <input type="checkbox" name="confirm_negative" value="1">
        <span>Buchung auch zulassen, wenn das Konto dadurch ins Minus geht.</span>
      </label>
    </div>

    <div class="btn-row mt-2">
      <button class="btn btn--primary" type="submit">Buchen</button>
      <a class="btn btn--ghost" href="<?= e(url('eltern')) ?>">Abbrechen</a>
    </div>
  </form>
</div>
