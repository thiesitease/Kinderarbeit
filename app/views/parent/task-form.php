<?php
/** @var array|null $task; @var array $children, $emojis */
defined('KINDERARBEIT') || exit;

$isEdit = $task !== null;
$emoji  = $task['emoji'] ?? '⭐';
?>

<div style="max-width:34rem;margin:0 auto">
  <div class="section__head">
    <h1><?= $isEdit ? 'Aufgabe bearbeiten' : 'Neue Aufgabe' ?></h1>
  </div>

  <form method="post" action="<?= e(url('aufgabe-save')) ?>" class="card">
    <?= Csrf::field() ?>
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <label class="field__label" for="title">Was ist zu tun?</label>
      <input class="input" type="text" id="title" name="title" maxlength="80" required autofocus
             placeholder="z. B. Rasen mähen"
             value="<?= e($task['title'] ?? '') ?>">
    </div>

    <div class="field">
      <label class="field__label" for="amount">Betrag</label>
      <input class="input input--amount tnum" type="text" id="amount" name="amount"
             inputmode="decimal" required placeholder="2,50"
             value="<?= e($isEdit ? Money::forInput((int)$task['amount_cents']) : '') ?>">
      <p class="field__hint">In Euro, z. B. <strong>2,50</strong> oder <strong>5</strong>.</p>
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

    <div class="field">
      <label class="field__label" for="description">Beschreibung <span class="muted">(freiwillig)</span></label>
      <textarea class="textarea" id="description" name="description" maxlength="400"
                placeholder="Was genau gehört dazu?"><?= e($task['description'] ?? '') ?></textarea>
    </div>

    <div class="field">
      <label class="field__label" for="assigned_to">Für wen?</label>
      <select class="select" id="assigned_to" name="assigned_to">
        <option value="0">Alle Kinder</option>
        <?php foreach ($children as $child): ?>
          <option value="<?= (int)$child['id'] ?>"
            <?= (int)($task['assigned_to'] ?? 0) === (int)$child['id'] ? ' selected' : '' ?>>
            Nur <?= e($child['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <span class="field__label">Wie oft?</span>
      <div class="segmented">
        <input type="radio" id="kind-repeatable" name="kind" value="repeatable"
               <?= ($task['kind'] ?? 'repeatable') === 'repeatable' ? ' checked' : '' ?>>
        <label for="kind-repeatable">🔁 Immer wieder</label>

        <input type="radio" id="kind-once" name="kind" value="once"
               <?= ($task['kind'] ?? '') === 'once' ? ' checked' : '' ?>>
        <label for="kind-once">1️⃣ Nur einmal</label>
      </div>
      <p class="field__hint">
        „Immer wieder“ bleibt dauerhaft in der Liste. „Nur einmal“ verschwindet,
        sobald ein Kind sie gemeldet hat.
      </p>
    </div>

    <div class="btn-row mt-2">
      <button class="btn btn--primary" type="submit">Speichern</button>
      <a class="btn btn--ghost" href="<?= e(url('aufgaben')) ?>">Abbrechen</a>
    </div>
  </form>
</div>
