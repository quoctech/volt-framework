<?php

/**
 * Reusable Alpine modal shell for Volt metadata views.
 *
 * @var string $modalState
 * @var string $title
 * @var string $bodyHtml
 * @var string $footerHtml
 * @var string $closeAction
 * @var string $maxWidthClass
 * @var string $panelClass
 */
$modalState = $modalState ?? 'modalOpen';
$title = $title ?? 'Modal';
$bodyHtml = $bodyHtml ?? '';
$footerHtml = $footerHtml ?? '';
$closeAction = $closeAction ?? 'closeModal()';
$maxWidthClass = $maxWidthClass ?? 'max-w-md';
$panelClass = $panelClass ?? '';
?>
<div
    x-show="<?= esc($modalState, 'attr') ?>"
    x-cloak
    class="claro-dialog"
    @keydown.escape.window="<?= esc($closeAction, 'attr') ?>"
>
    <div class="claro-dialog__overlay" @click="<?= esc($closeAction, 'attr') ?>"></div>
    <div class="claro-dialog__panel <?= esc($maxWidthClass, 'attr') ?> <?= esc($panelClass, 'attr') ?>" @click.stop>
        <div class="claro-dialog__title">
            <?= esc($title) ?>
            <button type="button" class="claro-dialog__close" @click="<?= esc($closeAction, 'attr') ?>">&times;</button>
        </div>
        <div class="claro-dialog__body">
            <?= $bodyHtml ?>
        </div>
        <?php if ($footerHtml !== ''): ?>
            <div class="claro-dialog__actions">
                <?= $footerHtml ?>
            </div>
        <?php endif; ?>
    </div>
</div>
