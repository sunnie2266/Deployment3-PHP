<?php
declare(strict_types=1);
session_start();

/*
 * MyNotes — single-file PHP notes app.
 * Requirements: PHP 8+; write permission in this directory for notes.json.
 */

$dataFile = __DIR__ . '/notes.json';

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadNotes(string $file): array {
    if (!is_file($file)) {
        return [
            ['id' => 'n1', 'title' => 'Study Plan', 'body' => "Learn PHP fundamentals\nBuild a small project\nPractice coding every day", 'created' => time() - 3600, 'color' => 'blue'],
            ['id' => 'n2', 'title' => 'Project Ideas', 'body' => "Build a personal finance tracker\nCreate a beautiful portfolio\nExplore AI on edge devices", 'created' => time() - 86400, 'color' => 'green'],
            ['id' => 'n3', 'title' => 'Daily Reminder', 'body' => 'Be consistent. Small steps every day lead to big results. 💪', 'created' => time() - 172800, 'color' => 'purple'],
            ['id' => 'n4', 'title' => 'Bucket List', 'body' => "Visit Japan\nPlay badminton in every city\nBuild a life I'm proud of", 'created' => time() - 259200, 'color' => 'pink'],
        ];
    }
    $json = file_get_contents($file);
    $notes = json_decode($json ?: '[]', true);
    return is_array($notes) ? $notes : [];
}

function saveNotes(string $file, array $notes): bool {
    $json = json_encode(array_values($notes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return file_put_contents($file, $json === false ? '[]' : $json, LOCK_EX) !== false;
}

$notes = loadNotes($dataFile);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = trim((string)($_POST['id'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($title === '' || $body === '') {
            $error = 'Please enter both a title and some note content.';
        } elseif (mb_strlen($title) > 100 || mb_strlen($body) > 5000) {
            $error = 'Title must be under 100 characters and note content under 5,000 characters.';
        } else {
            $colors = ['blue', 'green', 'purple', 'pink'];
            if ($id !== '') {
                foreach ($notes as &$note) {
                    if (($note['id'] ?? '') === $id) {
                        $note['title'] = $title;
                        $note['body'] = $body;
                        $note['updated'] = time();
                        break;
                    }
                }
                unset($note);
                $success = 'Your note has been updated.';
            } else {
                $notes[] = [
                    'id' => bin2hex(random_bytes(8)),
                    'title' => $title,
                    'body' => $body,
                    'created' => time(),
                    'color' => $colors[array_rand($colors)],
                ];
                $success = 'A little idea saved successfully.';
            }
            if (!saveNotes($dataFile, $notes)) {
                $error = 'Could not save notes. Please check folder write permissions.';
                $success = '';
            }
        }
    } elseif ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $notes = array_values(array_filter($notes, fn($note) => ($note['id'] ?? '') !== $id));
        if (saveNotes($dataFile, $notes)) {
            $success = 'Note deleted.';
        } else {
            $error = 'Could not update notes. Check folder write permissions.';
        }
    }

    $_SESSION['flash_success'] = $success;
    $_SESSION['flash_error'] = $error;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$success = (string)($_SESSION['flash_success'] ?? '');
$error = (string)($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$sort = (string)($_GET['sort'] ?? 'newest');
if ($sort === 'oldest') {
    usort($notes, fn($a, $b) => ($a['created'] ?? 0) <=> ($b['created'] ?? 0));
} else {
    usort($notes, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));
}

$editing = null;
$editId = (string)($_GET['edit'] ?? '');
if ($editId !== '') {
    foreach ($notes as $note) {
        if (($note['id'] ?? '') === $editId) {
            $editing = $note;
            break;
        }
    }
}

$icons = [
    'blue' => '✎',
    'green' => '✧',
    'purple' => '☆',
    'pink' => '♡',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f6f8ff">
<title>MyNotes — Make room for your ideas</title>
<style>
:root {
    --ink: #182747;
    --muted: #65799d;
    --line: rgba(185, 201, 231, .62);
    --glass: rgba(255, 255, 255, .76);
    --purple: #7055f5;
    --blue: #2f83ff;
    --shadow: 0 18px 48px rgba(70, 91, 150, .09);
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    min-height: 100vh;
    color: var(--ink);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:
        radial-gradient(ellipse at 50% 0%, rgba(255, 225, 218, .85), transparent 37rem),
        radial-gradient(ellipse at 8% 35%, rgba(190, 216, 255, .65), transparent 33rem),
        radial-gradient(ellipse at 90% 38%, rgba(222, 211, 255, .55), transparent 32rem),
        linear-gradient(180deg, #f7f8ff 0%, #fbfcff 48%, #f3f7ff 100%);
    background-attachment: fixed;
}
a { color: inherit; text-decoration: none; }
button, input, textarea, select { font: inherit; }
.topbar {
    position: sticky; top: 0; z-index: 5;
    border-bottom: 1px solid rgba(207, 216, 238, .65);
    background: rgba(255,255,255,.72);
    backdrop-filter: blur(18px);
}
.nav {
    width: min(1060px, calc(100% - 40px)); height: 70px; margin: auto;
    display: flex; align-items: center; justify-content: space-between; gap: 24px;
}
.brand { display: inline-flex; align-items: center; gap: 11px; font-size: 20px; font-weight: 800; letter-spacing: -.7px; }
.brand-mark {
    width: 37px; height: 37px; display: grid; place-items: center; border-radius: 12px;
    color: white; font-size: 21px; background: linear-gradient(145deg, #8a7bff, #5a47e8);
    box-shadow: 0 7px 18px rgba(103, 80, 242, .25);
}
.nav-links { display: flex; align-items: center; gap: 26px; color: var(--muted); font-size: 13px; font-weight: 650; }
.nav-links a { padding: 10px 0; transition: color .2s ease; }
.nav-links a:hover, .nav-links a.active { color: var(--purple); }
main { width: min(1060px, calc(100% - 40px)); margin: auto; }
.hero { text-align: center; padding: 54px 0 29px; }
.eyebrow {
    display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px;
    border: 1px solid rgba(145, 131, 255, .23); background: rgba(255,255,255,.64);
    color: #725df0; font-size: 11px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase;
}
h1 { margin: 18px 0 10px; font-size: clamp(34px, 5vw, 54px); line-height: 1.12; letter-spacing: -2.3px; }
.gradient-text { color: #7258f5; background: linear-gradient(100deg, #6555f2, #a14fe9 65%, #4799f7); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.hero p { margin: 0; color: #627697; font-size: 16px; line-height: 1.7; }
.hero-rule { width: 76px; height: 3px; margin: 21px auto 0; border-radius: 9px; background: linear-gradient(90deg, #9b58ff, #4cb7ff); }
.composer {
    max-width: 760px; margin: 8px auto 32px; padding: 22px;
    border: 1px solid rgba(255,255,255,.95); border-radius: 18px;
    background: rgba(255,255,255,.72); box-shadow: var(--shadow); backdrop-filter: blur(16px);
}
.composer-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.composer h2 { margin: 0; font-size: 16px; letter-spacing: -.3px; }
.composer-hint { color: #8a9ab8; font-size: 12px; }
.field {
    width: 100%; border: 1px solid #d6e0f3; border-radius: 10px; padding: 13px 14px;
    color: var(--ink); background: rgba(255,255,255,.68); outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.field::placeholder { color: #8b9cbb; }
.field:focus { border-color: #9988ff; box-shadow: 0 0 0 4px rgba(112,85,245,.1); }
textarea.field { min-height: 105px; resize: vertical; margin-top: 11px; line-height: 1.6; }
.form-bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 13px; }
.form-note { color: #91a0ba; font-size: 11px; }
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    border: 0; border-radius: 10px; padding: 11px 16px; font-size: 13px; font-weight: 750;
    cursor: pointer; transition: transform .18s, box-shadow .18s, opacity .18s;
}
.btn:hover { transform: translateY(-1px); }
.btn-primary { color: white; background: linear-gradient(110deg, #795cf6, #6650e9); box-shadow: 0 8px 17px rgba(108,83,239,.23); }
.btn-primary:hover { box-shadow: 0 11px 22px rgba(108,83,239,.3); }
.btn-quiet { color: #647795; background: #eef2fc; }
.alert { max-width: 760px; margin: 0 auto 20px; padding: 12px 15px; border-radius: 11px; font-size: 13px; }
.alert.success { color: #16744b; background: #e9f9f0; border: 1px solid #c7efd8; }
.alert.error { color: #a33d50; background: #fff0f2; border: 1px solid #ffd4dc; }
.notes-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 25px 0 18px; }
.heading-left { display: flex; align-items: center; gap: 12px; }
.notes-heading h2 { margin: 0; font-size: 25px; letter-spacing: -.9px; }
.count { display: grid; place-items: center; min-width: 29px; height: 29px; padding: 0 8px; border-radius: 99px; color: #6655f0; background: #e6e9ff; font-size: 12px; font-weight: 800; }
.sort-wrap { display: flex; align-items: center; gap: 9px; color: #7789a9; font-size: 12px; }
.sort-select { padding: 8px 10px; border: 1px solid #d9e2f3; border-radius: 9px; color: #617696; background: rgba(255,255,255,.72); outline: none; cursor: pointer; }
.notes-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px; }
.note {
    --accent: #9ec5ff; --tint: #edf5ff;
    position: relative; min-height: 190px; padding: 21px 21px 18px;
    overflow: hidden; border: 1px solid rgba(211, 222, 243, .8); border-radius: 15px;
    background: rgba(255,255,255,.68); box-shadow: 0 9px 28px rgba(61, 86, 145, .055);
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}
.note::before { content: ""; position: absolute; inset: 0 0 auto; height: 3px; background: var(--accent); }
.note:hover { transform: translateY(-3px); box-shadow: 0 15px 34px rgba(61,86,145,.1); border-color: #d5ddf8; }
.note.green { --accent: #b5e9d1; --tint: #e9f8f0; }
.note.purple { --accent: #d8c5ff; --tint: #f0e9ff; }
.note.pink { --accent: #f6c9df; --tint: #ffeaf2; }
.note-head { display: flex; align-items: center; gap: 13px; }
.note-icon { flex: 0 0 46px; width: 46px; height: 46px; display: grid; place-items: center; border-radius: 14px; background: var(--tint); color: #4388fa; font-size: 24px; }
.note.green .note-icon { color: #20a66a; }
.note.purple .note-icon { color: #895cf4; }
.note.pink .note-icon { color: #e94e88; }
.note-title { margin: 0 0 5px; font-size: 15px; letter-spacing: -.2px; overflow-wrap: anywhere; }
.note-date { color: #8798b6; font-size: 11px; }
.note-body { margin: 16px 0 23px; color: #63799c; font-size: 13px; line-height: 1.7; white-space: pre-wrap; overflow-wrap: anywhere; }
.note-actions { display: flex; justify-content: flex-end; gap: 8px; position: absolute; right: 17px; bottom: 15px; }
.icon-btn { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 9px; cursor: pointer; font-size: 15px; transition: transform .15s, background .15s; }
.icon-btn:hover { transform: translateY(-2px); }
.edit-btn { color: #327ff4; background: #eaf2ff; }
.delete-btn { color: #ed5574; background: #fff0f3; }
.delete-form { display: inline; margin: 0; }
.empty { grid-column: 1 / -1; text-align: center; padding: 52px 20px; border: 1px dashed #ccd7ee; border-radius: 16px; background: rgba(255,255,255,.48); }
.empty-icon { font-size: 35px; }
.empty h3 { margin: 12px 0 6px; }
.empty p { margin: 0; color: var(--muted); font-size: 13px; }
footer { padding: 29px 0 24px; text-align: center; color: #8a9ab8; font-size: 11px; }
footer span { color: #e95f91; }
@media (max-width: 640px) {
    .nav { width: min(100% - 28px, 1060px); height: 62px; }
    .brand { font-size: 18px; }
    .nav-links { gap: 13px; font-size: 12px; }
    .nav-links a:nth-child(2) { display: none; }
    main { width: min(100% - 28px, 1060px); }
    .hero { padding: 42px 0 24px; }
    h1 { letter-spacing: -1.5px; }
    .hero p { font-size: 14px; }
    .composer { padding: 16px; margin-bottom: 27px; }
    .composer-hint, .form-note { display: none; }
    .notes-grid { grid-template-columns: 1fr; gap: 13px; }
    .notes-heading h2 { font-size: 22px; }
    .note { min-height: 175px; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
}
</style>
</head>
<body>
<header class="topbar">
    <nav class="nav" aria-label="Main navigation">
        <a class="brand" href="#home" aria-label="MyNotes home">
            <span class="brand-mark">▤</span><span>MyNotes</span>
        </a>
        <div class="nav-links">
            <a class="active" href="#home">⌂ &nbsp;Home</a>
            <a href="#notes">▤ &nbsp;All Notes</a>
            <a href="#about">ⓘ &nbsp;About</a>
        </div>
    </nav>
</header>

<main id="home">
    <section class="hero">
        <span class="eyebrow">✦ Your little corner of the internet</span>
        <h1>Welcome to <span class="gradient-text">MyNotes</span></h1>
        <p>Capture your thoughts, organize your ideas, and make it happen.</p>
        <div class="hero-rule"></div>
    </section>

    <?php if ($success !== ''): ?><div class="alert success" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <section class="composer" aria-labelledby="composer-title">
        <div class="composer-top">
            <h2 id="composer-title"><?= $editing ? '✎ Edit your note' : '✦ Create something lovely' ?></h2>
            <span class="composer-hint">Your ideas deserve a home</span>
        </div>
        <form method="post" action="#notes">
            <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? '')) ?>">
            <label for="title" style="position:absolute;left:-9999px">Note title</label>
            <input class="field" id="title" name="title" maxlength="100" required placeholder="Give your note a title..." value="<?= e((string)($editing['title'] ?? '')) ?>">
            <label for="body" style="position:absolute;left:-9999px">Note content</label>
            <textarea class="field" id="body" name="body" maxlength="5000" required placeholder="What's on your mind? Let it out..."><?= e((string)($editing['body'] ?? '')) ?></textarea>
            <div class="form-bottom">
                <span class="form-note">A thought today, an idea tomorrow ✨</span>
                <div style="display:flex;gap:8px">
                    <?php if ($editing): ?><a class="btn btn-quiet" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>#notes">Cancel</a><?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= $editing ? '✓ Save changes' : '＋ Add Note' ?></button>
                </div>
            </div>
        </form>
    </section>

    <section id="notes" aria-labelledby="notes-title">
        <div class="notes-heading">
            <div class="heading-left">
                <h2 id="notes-title">Your Notes</h2>
                <span class="count"><?= count($notes) ?></span>
            </div>
            <form class="sort-wrap" method="get" action="#notes">
                <span aria-hidden="true">↕</span>
                <label for="sort" style="position:absolute;left:-9999px">Sort notes</label>
                <select class="sort-select" id="sort" name="sort" onchange="this.form.submit()">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                </select>
            </form>
        </div>

        <div class="notes-grid">
        <?php if (!$notes): ?>
            <div class="empty">
                <div class="empty-icon">☁️</div>
                <h3>A fresh page awaits</h3>
                <p>Your first note can be the start of something wonderful.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note):
                $color = in_array(($note['color'] ?? 'blue'), ['blue','green','purple','pink'], true) ? $note['color'] : 'blue';
                $created = (int)($note['created'] ?? time());
            ?>
                <article class="note <?= e($color) ?>">
                    <div class="note-head">
                        <div class="note-icon" aria-hidden="true"><?= e($icons[$color]) ?></div>
                        <div style="min-width:0">
                            <h3 class="note-title"><?= e((string)($note['title'] ?? 'Untitled')) ?></h3>
                            <div class="note-date"><?= date('M j, Y · g:i A', $created) ?></div>
                        </div>
                    </div>
                    <p class="note-body"><?= e((string)($note['body'] ?? '')) ?></p>
                    <div class="note-actions">
                        <a class="icon-btn edit-btn" href="?edit=<?= rawurlencode((string)$note['id']) ?>#home" aria-label="Edit <?= e((string)$note['title']) ?>" title="Edit note">✎</a>
                        <form class="delete-form" method="post" onsubmit="return confirm('Delete this note? This cannot be undone.');">
                            <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string)$note['id']) ?>">
                            <button class="icon-btn delete-btn" type="submit" aria-label="Delete <?= e((string)$note['title']) ?>" title="Delete note">⌫</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </section>
    <footer id="about">Made with care and a little PHP <span>♥</span> &nbsp;·&nbsp; Your ideas, your space.</footer>
</main>
</body>
</html>
