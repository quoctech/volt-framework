<?php

/** @var array<int, \Volt\Core\Notes\Entities\NoteEntity> $notes */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Notes</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<main class="mx-auto max-w-6xl px-6 py-10">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300">Entity sample</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Notes</h1>
        </div>
        <a href="<?= site_url('notes/create') ?>" class="rounded-2xl bg-cyan-400 px-4 py-2.5 font-semibold text-slate-950">New Note</a>
    </div>

    <div class="mt-8 grid gap-4">
        <?php foreach ($notes as $note): ?>
            <article class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white"><?= esc($note->title) ?></h2>
                        <p class="mt-2 text-sm text-slate-400">Owner: <?= esc($note->owner) ?> | Status: <?= esc($note->status) ?></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?= site_url('notes/edit/' . $note->id) ?>" class="rounded-2xl border border-white/10 px-3 py-2 text-sm text-slate-200">Edit</a>
                        <form action="<?= site_url('notes/delete/' . $note->id) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-100">Delete</button>
                        </form>
                    </div>
                </div>
                <?php if (! empty($note->body)): ?>
                    <p class="mt-4 whitespace-pre-wrap text-slate-300"><?= esc($note->body) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
