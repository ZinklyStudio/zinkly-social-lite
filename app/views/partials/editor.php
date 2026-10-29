<?php
/**
 * Text box that becomes a rich text editor (bold, headings, lists, quotes, links ...) when rich text is on and JavaScript runs.
 * Without JavaScript it stays a plain text box. $name, $id, $value, $format ('text'|'html'), $label, $placeholder, $max, $plain (force plain)
 */
$rich = empty($plain) && setting('rich_text', '1') === '1' && Text::richAvailable();
$format = $format ?? 'text';
?>
<div class="zl-editor" <?= $rich ? 'data-rich' : '' ?> data-format="<?= e($format) ?>">
    <label class="sr-only" for="<?= e($id) ?>"><?= e($label ?? '') ?></label>
    <textarea id="<?= e($id) ?>" class="<?= e($class ?? 'zl-input') ?>" name="<?= e($name) ?>" <?= !empty($max) ? 'data-max="' . (int) $max . '"' : '' ?> placeholder="<?= e($placeholder ?? '') ?>" data-autogrow><?= e((string) ($value ?? '')) ?></textarea>
    <input type="hidden" name="format" value="text" data-format-input>
</div>
