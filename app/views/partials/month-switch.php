<?php
/**
 * Monatswechsler als Formular – funktioniert auch ohne JavaScript.
 * @var string $month
 * @var array  $months
 * @var string $target   Seite, auf die gewechselt wird
 * @var array  $extra    zusaetzliche Formularfelder (Name => Wert)
 * @var bool   $allowAll
 */
defined('KINDERARBEIT') || exit;

$extra    = $extra ?? [];
$allowAll = !empty($allowAll);
?>
<form method="get" action="./" class="row row--tight">
  <input type="hidden" name="p" value="<?= e($target) ?>">
  <?php foreach ($extra as $name => $value): ?>
    <input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>">
  <?php endforeach; ?>
  <label class="visually-hidden" for="monat-<?= e($target) ?>">Monat</label>
  <select class="select" id="monat-<?= e($target) ?>" name="monat" data-autosubmit style="width:auto;min-width:11rem">
    <?php if ($allowAll): ?>
      <option value="alle"<?= $month === 'alle' ? ' selected' : '' ?>>Alle Monate</option>
    <?php endif; ?>
    <?php foreach ($months as $option): ?>
      <option value="<?= e($option) ?>"<?= $option === $month ? ' selected' : '' ?>><?= e(month_label($option)) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn btn--sm" type="submit">Anzeigen</button></noscript>
</form>
