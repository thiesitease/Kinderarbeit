<?php
/** @var array|null $expense; @var array $children, $emojis; @var int $presetChild */
defined('KINDERARBEIT') || exit;

$isEdit       = $expense !== null;
$selectedKind = $isEdit ? (int)$expense['child_id'] : $presetChild;
$emoji        = $expense['emoji'] ?? '💳';
?>

<div style="max-width:34rem;margin:0 auto">
  <div class="section__head">
    <h1><?= $isEdit ? 'Ausgabe bearbeiten' : 'Neue feste Ausgabe' ?></h1>
  </div>

  <form method="post" action="<?= e(url('ausgabe-save')) ?>" class="card">
    <?= Csrf::field() ?>
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <label class="field__label" for="child_id">Für wen?</label>
      <select class="select" id="child_id" name="child_id" required>
        <option value="">Bitte wählen …</option>
        <?php foreach ($children as $child): ?>
          <option value="<?= (int)$child['id'] ?>"<?= $selectedKind === (int)$child['id'] ? ' selected' : '' ?>>
            <?= e($child['emoji']) ?> <?= e($child['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="field__label" for="title">Wofür?</label>
      <input class="input" type="text" id="title" name="title" maxlength="80" required
             placeholder="z. B. Beitrag Fitnessstudio"
             value="<?= e($expense['title'] ?? '') ?>">
    </div>

    <div class="field-row">
      <div class="field">
        <label class="field__label" for="amount">Betrag pro Monat</label>
        <input class="input input--amount tnum" type="text" id="amount" name="amount"
               inputmode="decimal" required placeholder="19,90"
               value="<?= e($isEdit ? Money::forInput((int)$expense['amount_cents']) : '') ?>">
      </div>
      <div class="field">
        <label class="field__label" for="day_of_month">Abbuchung am</label>
        <select class="select" id="day_of_month" name="day_of_month">
          <?php for ($day = 1; $day <= 28; $day++): ?>
            <option value="<?= $day ?>"<?= (int)($expense['day_of_month'] ?? 1) === $day ? ' selected' : '' ?>>
              <?= $day ?>. des Monats
            </option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label class="field__label" for="emoji">Symbol</label>
      <input class="input" type="text" id="emoji" name="emoji" maxlength="8"
             style="width:5rem;text-align:center;font-size:1.4rem" value="<?= e($emoji) ?>">
      <div class="emoji-picker" data-emoji-picker data-target="emoji">
        <?php foreach ($emojis as $option): ?>
          <button class="emoji-picker__btn" type="button" data-emoji="<?= e($option) ?>"
                  aria-label="Symbol <?= e($option) ?> wählen"><?= e($option) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="field-row">
      <?php if (!$isEdit): ?>
        <div class="field">
          <label class="field__label" for="start_month">Erster Monat</label>
          <input class="input" type="month" id="start_month" name="start_month" value="<?= e(current_month()) ?>">
          <p class="field__hint">Ab diesem Monat wird abgebucht.</p>
        </div>
      <?php endif; ?>
      <div class="field">
        <label class="field__label" for="end_month">Letzter Monat <span class="muted">(freiwillig)</span></label>
        <input class="input" type="month" id="end_month" name="end_month" value="<?= e($expense['end_month'] ?? '') ?>">
        <p class="field__hint">Leer lassen, wenn es unbefristet läuft.</p>
      </div>
    </div>

    <div class="btn-row mt-2">
      <button class="btn btn--primary" type="submit">Speichern</button>
      <a class="btn btn--ghost" href="<?= e(url('ausgaben')) ?>">Abbrechen</a>
      <?php if ($isEdit): ?>
        <span class="push-right"></span>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('ausgabe-aktion')) ?>" class="mt-2"
          data-confirm="„<?= e($expense['title']) ?>“ wirklich entfernen? Bereits erfolgte Abbuchungen bleiben im Verlauf.">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">
      <button class="btn btn--danger btn--block" type="submit" name="action" value="delete">Ausgabe entfernen</button>
    </form>
  <?php endif; ?>
</div>
