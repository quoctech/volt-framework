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
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
    @keydown.escape.window="<?= esc($closeAction, 'attr') ?>"
>
    <div class="w-full <?= esc($maxWidthClass, 'attr') ?> border border-zinc-300 bg-white shadow-xl <?= esc($panelClass, 'attr') ?>" @click.stop>
        <div class="border-b border-zinc-300 px-4 py-3">
            <h2 class="text-lg font-semibold text-zinc-900"><?= esc($title) ?></h2>
        </div>
        <div class="space-y-4 px-4 py-4">
            <?= $bodyHtml ?>
        </div>
        <div class="flex justify-end gap-2 border-t border-zinc-300 px-4 py-3">
            <?= $footerHtml ?>
        </div>
    </div>
</div>
