<?php

/** @var \Volt\Core\Notes\Entities\NoteEntity|null $note */
/** @var array<string, string> $errors */
$isEdit = $note !== null;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Note Form</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<main class="mx-auto max-w-3xl px-6 py-10">
    <a href="<?= site_url('notes') ?>" class="text-sm text-cyan-300">Back to Notes</a>
    <div class="mt-4 rounded-3xl border border-white/10 bg-white/5 p-6">
        <h1 class="text-2xl font-semibold text-white"><?= $isEdit ? 'Edit Note' : 'Create Note' ?></h1>

        <?php if ($errors !== []): ?>
            <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                <?php foreach ($errors as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="<?= $isEdit ? site_url('notes/update/' . $note->id) : site_url('notes/store') ?>" method="post" class="mt-6 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="mb-2 block text-sm text-slate-300" for="title">Title</label>
                <input id="title" name="title" type="text" required value="<?= esc($note->title ?? '') ?>" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white">
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300" for="body">Body</label>
                <textarea id="body" name="body" rows="8" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white"><?= esc($note->body ?? '') ?></textarea>
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300" for="status">Status</label>
                <select id="status" name="status" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white">
                    <?php foreach (['draft', 'published', 'archived'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= (($note->status ?? 'draft') === $status) ? 'selected' : '' ?>><?= esc($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-2xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950"><?= $isEdit ? 'Update' : 'Create' ?></button>
        </form>
    </div>
</main>
</body>
</html>
