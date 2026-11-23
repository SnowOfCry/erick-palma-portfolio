<?php
require __DIR__ . '/lib.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$auth = load_json('auth', []);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

function go(string $q = '', string $msg = '', string $type = 'ok'): void {
    if ($msg) $_SESSION['flash'] = [$type, $msg];
    header('Location: admin.php' . ($q ? "?$q" : ''));
    exit;
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Go back and try again.'); }
}
function logged_in(): bool { return !empty($_SESSION['admin']); }

$action = $_POST['action'] ?? '';

/* ---------- First-time setup: create the admin password ---------- */
if (empty($auth['hash'])) {
    if ($action === 'setup') {
        check_csrf();
        $p1 = $_POST['password'] ?? ''; $p2 = $_POST['password2'] ?? '';
        if (strlen($p1) < 8) go('', 'Use at least 8 characters.', 'err');
        if ($p1 !== $p2) go('', 'The passwords do not match.', 'err');
        save_json('auth', ['hash' => password_hash($p1, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        go('', 'Password created. Welcome!');
    }
    $view = 'setup';
}
/* ---------- Login / logout ---------- */
elseif ($action === 'login') {
    check_csrf();
    $lock = load_json('login', ['fails' => 0, 'until' => 0]);
    if ($lock['until'] > time()) go('', 'Too many attempts. Try again in a few minutes.', 'err');
    if (password_verify($_POST['password'] ?? '', $auth['hash'])) {
        save_json('login', ['fails' => 0, 'until' => 0]);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        go();
    }
    sleep(1);
    $lock['fails']++;
    if ($lock['fails'] >= 5) $lock = ['fails' => 0, 'until' => time() + 600];
    save_json('login', $lock);
    go('', 'Wrong password.', 'err');
}
elseif (($_GET['a'] ?? '') === 'logout') {
    $_SESSION = []; session_destroy();
    header('Location: admin.php'); exit;
}

if (!isset($view)) $view = logged_in() ? ($_GET['a'] ?? 'list') : 'login';

/* ---------- Actions that need login ---------- */
if (logged_in() && $action !== '' && !in_array($action, ['login', 'setup'], true)) {
    check_csrf();
    $projects = get_projects();
    $find = function ($id) use (&$projects) { foreach ($projects as $i => $p) if ($p['id'] === $id) return $i; return -1; };
    $renumber = function () use (&$projects) { foreach ($projects as $i => &$p) $p['order'] = $i + 1; unset($p); };

    try {
        switch ($action) {
            case 'save_project':
                $id = $_POST['id'] ?? '';
                $i = $id ? $find($id) : -1;
                $p = $i >= 0 ? $projects[$i] : ['id' => 'p' . bin2hex(random_bytes(4)), 'order' => 0, 'image' => ''];
                foreach (['title','title_es','tag_en','tag_es','desc_en','desc_es','problem_en','problem_es','result_en','result_es'] as $f)
                    $p[$f] = trim($_POST[$f] ?? '');
                if ($p['title'] === '') go($id ? 'a=edit&id=' . urlencode($id) : 'a=new', 'The project needs a name.', 'err');
                foreach (['live_url','demo_url','video_url'] as $f) $p[$f] = safe_url($_POST[$f] ?? '');
                $p['stack'] = array_values(array_filter(array_map('trim', explode(',', $_POST['stack'] ?? ''))));
                $p['featured'] = !empty($_POST['featured']);
                if (!empty($_POST['remove_image'])) { delete_upload($p['image']); $p['image'] = ''; }
                $new = store_upload($_FILES['image'] ?? []);
                if ($new !== '') { delete_upload($p['image']); $p['image'] = $new; }
                if ($i >= 0) $projects[$i] = $p; else array_unshift($projects, $p);
                $renumber();
                save_json('projects', $projects);
                go('', $i >= 0 ? 'Project updated.' : 'Project added.');

            case 'delete_project':
                $i = $find($_POST['id'] ?? '');
                if ($i >= 0) { delete_upload($projects[$i]['image'] ?? ''); array_splice($projects, $i, 1); $renumber(); save_json('projects', $projects); }
                go('', 'Project deleted.');

            case 'move':
                $i = $find($_POST['id'] ?? '');
                $j = $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
                if ($i >= 0 && $j >= 0 && $j < count($projects)) { [$projects[$i], $projects[$j]] = [$projects[$j], $projects[$i]]; $renumber(); save_json('projects', $projects); }
                go();

            case 'save_settings':
                $s = get_settings();
                foreach (['email','phone','whatsapp','linkedin','projects_count','web3forms_key'] as $f) $s[$f] = trim($_POST[$f] ?? '');
                if (!empty($_POST['remove_photo'])) { delete_upload($s['photo']); $s['photo'] = ''; }
                $new = store_upload($_FILES['photo'] ?? []);
                if ($new !== '') { delete_upload($s['photo']); $s['photo'] = $new; }
                save_json('settings', $s);
                go('a=settings', 'Settings saved.');

            case 'change_password':
                if (!password_verify($_POST['current'] ?? '', $auth['hash'])) go('a=settings', 'Current password is wrong.', 'err');
                $p1 = $_POST['password'] ?? '';
                if (strlen($p1) < 8) go('a=settings', 'Use at least 8 characters.', 'err');
                if ($p1 !== ($_POST['password2'] ?? '')) go('a=settings', 'The new passwords do not match.', 'err');
                save_json('auth', ['hash' => password_hash($p1, PASSWORD_DEFAULT)]);
                go('a=settings', 'Password changed.');
        }
    } catch (RuntimeException $ex) {
        go($action === 'save_settings' ? 'a=settings' : '', $ex->getMessage(), 'err');
    }
}

/* ---------- Data for views ---------- */
$projects = logged_in() ? get_projects() : [];
$settings = logged_in() ? get_settings() : [];
$editing = null;
if ($view === 'edit') { foreach ($projects as $p) if ($p['id'] === ($_GET['id'] ?? '')) $editing = $p; if (!$editing) $view = 'list'; }
if ($view === 'new') { $editing = ['id' => '', 'featured' => false, 'image' => '', 'stack' => []]; $view = 'edit'; }
$v = fn($k) => e($editing[$k] ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin · Portfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{--ink:#14161c;--ink-2:#5a5f6c;--paper:#f4f2ed;--card:#fff;--line:#e2ded4;--accent:#e2572b;--accent-dark:#b8411c;--ok:#1f8a57;--err:#c0392b}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Inter,system-ui,sans-serif;background:var(--paper);color:var(--ink);font-size:15px;line-height:1.5}
  h1,h2{font-family:"Space Grotesk",sans-serif;letter-spacing:-.01em}
  a{color:inherit}
  .top{background:var(--ink);color:#fff}
  .top .in{max-width:1000px;margin:0 auto;padding:0 20px;height:60px;display:flex;align-items:center;justify-content:space-between}
  .top b{font-family:"Space Grotesk";font-size:17px}
  .top nav{display:flex;gap:6px}
  .top nav a{text-decoration:none;padding:7px 12px;border-radius:8px;color:#c9ccd4;font-weight:500}
  .top nav a:hover,.top nav a.on{background:#2a2d36;color:#fff}
  main{max-width:1000px;margin:0 auto;padding:32px 20px 80px}
  .bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap}
  h1{font-size:28px}
  .btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--card);padding:9px 16px;border-radius:9px;font:600 14px Inter,sans-serif;cursor:pointer;text-decoration:none;color:var(--ink)}
  .btn:hover{border-color:#bdb8ab}
  .btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
  .btn.primary:hover{background:var(--accent-dark)}
  .btn.danger{color:var(--err)}
  .btn.sm{padding:6px 10px;font-size:13px}
  .flash{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-weight:500}
  .flash.ok{background:#e3f4ea;color:var(--ok)}
  .flash.err{background:#fbe5e2;color:var(--err)}
  .list{display:grid;gap:12px}
  .row{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px;display:flex;align-items:center;gap:16px}
  .thumb{width:120px;height:75px;border-radius:8px;background:#e8e4da center/cover no-repeat;flex-shrink:0;display:grid;place-items:center;color:#9a9588;font-size:12px}
  .row .info{flex:1;min-width:0}
  .row .info b{font-size:16px;display:block}
  .row .info span{color:var(--ink-2);font-size:13px}
  .badge{display:inline-block;background:#fbe7df;color:var(--accent-dark);font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-left:6px;vertical-align:middle;text-transform:uppercase}
  .acts{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
  .acts form{display:inline}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px;margin-bottom:20px}
  .card h2{font-size:18px;margin-bottom:16px}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  label{display:block;font-weight:600;font-size:13px;margin-bottom:6px}
  label small{font-weight:400;color:var(--ink-2)}
  .f{margin-bottom:16px}
  input[type=text],input[type=url],input[type=email],input[type=password],textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fdfcfa}
  textarea{min-height:90px;resize:vertical}
  input:focus,textarea:focus{outline:2px solid var(--accent);border-color:transparent}
  .check{display:flex;align-items:center;gap:8px;font-weight:500}
  .drop{border:2px dashed var(--line);border-radius:12px;padding:16px;display:flex;gap:16px;align-items:center;cursor:pointer;background:#fdfcfa}
  .drop:hover,.drop.over{border-color:var(--accent);background:#fff7f3}
  .drop img{width:180px;height:112px;object-fit:cover;border-radius:8px;background:#e8e4da}
  .drop.round img{width:100px;height:100px}
  .drop input{display:none}
  .hint{color:var(--ink-2);font-size:13px}
  .login{max-width:380px;margin:12vh auto}
  .empty{text-align:center;padding:48px;color:var(--ink-2)}
  @media (max-width:700px){.g2{grid-template-columns:1fr}.row{flex-wrap:wrap}.thumb{width:100%;height:140px}.acts{justify-content:flex-start}}
</style>
</head>
<body>

<?php if ($view === 'setup' || $view === 'login'): ?>
<main>
  <div class="card login">
    <h1 style="margin-bottom:6px"><?= $view === 'setup' ? 'Create your admin password' : 'Admin login' ?></h1>
    <p class="hint" style="margin-bottom:20px"><?= $view === 'setup' ? 'First time here. Choose a password to protect your admin panel.' : 'Portfolio admin panel' ?></p>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="<?= $view ?>">
      <div class="f"><label>Password</label><input type="password" name="password" required autofocus <?= $view === 'setup' ? 'minlength="8" autocomplete="new-password"' : 'autocomplete="current-password"' ?>></div>
      <?php if ($view === 'setup'): ?><div class="f"><label>Repeat password</label><input type="password" name="password2" required minlength="8" autocomplete="new-password"></div><?php endif; ?>
      <button class="btn primary" style="width:100%;justify-content:center"><?= $view === 'setup' ? 'Create password' : 'Log in' ?></button>
    </form>
  </div>
</main>

<?php else: ?>
<div class="top"><div class="in">
  <b>Portfolio Admin</b>
  <nav>
    <a href="admin.php" class="<?= in_array($view, ['list','edit']) ? 'on' : '' ?>">Projects</a>
    <a href="admin.php?a=settings" class="<?= $view === 'settings' ? 'on' : '' ?>">Settings</a>
    <a href="./" target="_blank">View site ↗</a>
    <a href="admin.php?a=logout">Log out</a>
  </nav>
</div></div>
<main>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($view === 'list'): ?>
  <div class="bar"><h1>Projects</h1><a href="admin.php?a=new" class="btn primary">+ Add project</a></div>
  <?php if (!$projects): ?><div class="card empty">No projects yet. Click “Add project” to create your first one.</div><?php endif; ?>
  <div class="list">
  <?php foreach ($projects as $i => $p): $img = img_url($p['image'] ?? ''); ?>
    <div class="row">
      <div class="thumb" <?= $img ? 'style="background-image:url(\'' . e($img) . '\')"' : '' ?>><?= $img ? '' : 'No photo' ?></div>
      <div class="info">
        <b><?= e($p['title']) ?><?= !empty($p['featured']) ? '<span class="badge">Featured</span>' : '' ?></b>
        <span><?= e($p['tag_en'] ?? '') ?></span>
      </div>
      <div class="acts">
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><input type="hidden" name="dir" value="up"><button class="btn sm" <?= $i === 0 ? 'disabled' : '' ?> title="Move up">↑</button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><input type="hidden" name="dir" value="down"><button class="btn sm" <?= $i === count($projects) - 1 ? 'disabled' : '' ?> title="Move down">↓</button></form>
        <a class="btn sm" href="admin.php?a=edit&id=<?= urlencode($p['id']) ?>">Edit</a>
        <form method="post" onsubmit="return confirm('Delete “<?= e(addslashes($p['title'])) ?>”? This also deletes its photo.')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_project"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button class="btn sm danger">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

<?php elseif ($view === 'edit'): $img = img_url($editing['image'] ?? ''); ?>
  <div class="bar"><h1><?= $editing['id'] ? 'Edit project' : 'New project' ?></h1><a href="admin.php" class="btn">← Back</a></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save_project">
    <input type="hidden" name="id" value="<?= $v('id') ?>">

    <div class="card">
      <h2>Photo</h2>
      <label class="drop" id="drop">
        <img id="preview" src="<?= e($img) ?>" alt="" <?= $img ? '' : 'style="visibility:hidden"' ?>>
        <div><b>Click or drag a screenshot here</b><div class="hint">JPG, PNG or WEBP, up to 8 MB. Best size: 1600×1000 (wide).</div></div>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" id="file">
      </label>
      <?php if ($img): ?><label class="check" style="margin-top:12px"><input type="checkbox" name="remove_image" value="1"> Remove current photo</label><?php endif; ?>
    </div>

    <div class="card">
      <h2>Details</h2>
      <div class="g2">
        <div class="f"><label>Project name *</label><input type="text" name="title" value="<?= $v('title') ?>" required></div>
        <div class="f"><label>Name in Spanish <small>(optional)</small></label><input type="text" name="title_es" value="<?= $v('title_es') ?>"></div>
        <div class="f"><label>Type of project <small>e.g. Restaurant · Website</small></label><input type="text" name="tag_en" value="<?= $v('tag_en') ?>"></div>
        <div class="f"><label>Type in Spanish</label><input type="text" name="tag_es" value="<?= $v('tag_es') ?>"></div>
        <div class="f"><label>Description</label><textarea name="desc_en"><?= $v('desc_en') ?></textarea></div>
        <div class="f"><label>Description in Spanish</label><textarea name="desc_es"><?= $v('desc_es') ?></textarea></div>
        <div class="f"><label>Problem <small>(optional)</small></label><input type="text" name="problem_en" value="<?= $v('problem_en') ?>"></div>
        <div class="f"><label>Problem in Spanish</label><input type="text" name="problem_es" value="<?= $v('problem_es') ?>"></div>
        <div class="f"><label>Result <small>(optional)</small></label><input type="text" name="result_en" value="<?= $v('result_en') ?>"></div>
        <div class="f"><label>Result in Spanish</label><input type="text" name="result_es" value="<?= $v('result_es') ?>"></div>
      </div>
      <div class="f"><label>Tools used <small>separate with commas, e.g. PHP, MySQL, Square</small></label><input type="text" name="stack" value="<?= e(implode(', ', $editing['stack'] ?? [])) ?>"></div>
      <label class="check"><input type="checkbox" name="featured" value="1" <?= !empty($editing['featured']) ? 'checked' : '' ?>> Featured (shows as a big full-width card)</label>
    </div>

    <div class="card">
      <h2>Links <small class="hint">— leave empty to hide the button</small></h2>
      <div class="f"><label>Live website</label><input type="url" name="live_url" value="<?= $v('live_url') ?>" placeholder="https://"></div>
      <div class="g2">
        <div class="f"><label>Demo link</label><input type="url" name="demo_url" value="<?= $v('demo_url') ?>" placeholder="https://"></div>
        <div class="f"><label>Video walkthrough <small>(YouTube, Loom…)</small></label><input type="url" name="video_url" value="<?= $v('video_url') ?>" placeholder="https://"></div>
      </div>
    </div>

    <button class="btn primary">Save project</button>
  </form>

<?php elseif ($view === 'settings'): $photo = img_url($settings['photo']); ?>
  <div class="bar"><h1>Settings</h1></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save_settings">
    <div class="card">
      <h2>Your photo</h2>
      <label class="drop round" id="drop">
        <img id="preview" src="<?= e($photo) ?>" alt="" <?= $photo ? '' : 'style="visibility:hidden"' ?>>
        <div><b>Click or drag your photo here</b><div class="hint">Square photo works best.</div></div>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="file">
      </label>
      <?php if ($photo): ?><label class="check" style="margin-top:12px"><input type="checkbox" name="remove_photo" value="1"> Remove photo</label><?php endif; ?>
    </div>
    <div class="card">
      <h2>Contact info</h2>
      <div class="g2">
        <div class="f"><label>Email</label><input type="email" name="email" value="<?= e($settings['email']) ?>"></div>
        <div class="f"><label>Phone</label><input type="text" name="phone" value="<?= e($settings['phone']) ?>"></div>
        <div class="f"><label>WhatsApp <small>digits with country code, e.g. 12095551234</small></label><input type="text" name="whatsapp" value="<?= e($settings['whatsapp']) ?>"></div>
        <div class="f"><label>LinkedIn URL</label><input type="url" name="linkedin" value="<?= e($settings['linkedin']) ?>" placeholder="https://linkedin.com/in/..."></div>
        <div class="f"><label>“Projects launched” number</label><input type="text" name="projects_count" value="<?= e($settings['projects_count']) ?>"></div>
        <div class="f"><label>Web3Forms access key <small>free at web3forms.com</small></label><input type="text" name="web3forms_key" value="<?= e($settings['web3forms_key']) ?>"></div>
      </div>
      <p class="hint">Leave a field empty to hide it on the site.</p>
    </div>
    <button class="btn primary">Save settings</button>
  </form>

  <form method="post" class="card" style="margin-top:28px">
    <h2>Change password</h2>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="change_password">
    <div class="g2">
      <div class="f"><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div></div>
      <div class="f"><label>New password</label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
      <div class="f"><label>Repeat new password</label><input type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
    </div>
    <button class="btn">Change password</button>
  </form>
<?php endif; ?>
</main>
<?php endif; ?>

<script>
// Photo preview + drag & drop
const drop = document.getElementById('drop'), file = document.getElementById('file'), prev = document.getElementById('preview');
if (drop && file) {
  const show = f => { if (f && f.type.startsWith('image/')) { prev.src = URL.createObjectURL(f); prev.style.visibility = 'visible'; } };
  file.addEventListener('change', () => show(file.files[0]));
  ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
  ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
  drop.addEventListener('drop', e => { if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; show(file.files[0]); } });
}
</script>
</body>
</html>
