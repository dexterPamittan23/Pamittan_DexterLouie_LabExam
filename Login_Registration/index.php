<?php
/**
 * Cakey Bakey Pastry Shop - Login & Registration System
 * Single file: drop this folder into htdocs and open http://localhost/cakey-bakey/
 * Users are saved (with hashed passwords) in users_data.php, created automatically.
 */
session_start();

const DB_FILE = __DIR__ . '/users_data.php';

function load_users(): array {
    if (!file_exists(DB_FILE)) return [];
    $raw = file_get_contents(DB_FILE);
    $json = substr($raw, strpos($raw, "\n") + 1);
    return json_decode($json, true) ?: [];
}
function save_users(array $u): void {
    file_put_contents(DB_FILE, "<?php exit; ?>\n" . json_encode($u, JSON_PRETTY_PRINT), LOCK_EX);
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean_phone(string $p): string { return preg_replace('/[\s\-()]/', '', $p); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$page   = $_GET['page'] ?? 'login';
$errors = [];
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$old = ['email' => '', 'phone' => '', 'identifier' => $_COOKIE['remember_id'] ?? ''];

// ---------- Logout ----------
if ($page === 'logout') {
    session_destroy();
    session_start();
    $_SESSION['flash'] = 'You have been logged out.';
    header('Location: index.php?page=login'); exit;
}

// ---------- Form handling ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'register') {
        $page = 'register';
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $phone    = clean_phone(trim($_POST['phone'] ?? ''));
        $old['email'] = $email;
        $old['phone'] = trim($_POST['phone'] ?? '');

        if ($email === '') $errors['email'] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address, like name@example.com.';

        if ($password === '') $errors['password'] = 'Password is required.';
        elseif (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password))
            $errors['password'] = 'Use at least one uppercase letter, one lowercase letter and one number.';

        if ($confirm === '') $errors['confirm'] = 'Please confirm your password.';
        elseif ($password !== $confirm) $errors['confirm'] = 'Passwords do not match.';

        if ($phone !== '' && !preg_match('/^\+?\d{10,13}$/', $phone))
            $errors['phone'] = 'Phone number must be 10 to 13 digits (optional +).';

        if (!$errors) {
            $users = load_users();
            $key = strtolower($email);
            if (isset($users[$key])) {
                $errors['email'] = 'This email is already registered. Try logging in.';
            } else {
                $users[$key] = [
                    'email' => $email,
                    'phone' => $phone,
                    'hash'  => password_hash($password, PASSWORD_DEFAULT),
                    'created' => date('c'),
                ];
                save_users($users);
                $_SESSION['flash'] = 'Account created! You can log in now.';
                header('Location: index.php?page=login'); exit;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'login') {
        $page = 'login';
        $id = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $old['identifier'] = $id;

        if ($id === '') $errors['identifier'] = 'Enter your email or phone number.';
        if ($password === '') $errors['password'] = 'Password is required.';

        if (!$errors) {
            $found = null;
            foreach (load_users() as $u) {
                if (strcasecmp($u['email'], $id) === 0 || ($u['phone'] !== '' && $u['phone'] === clean_phone($id))) { $found = $u; break; }
            }
            if ($found && password_verify($password, $found['hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = $found['email'];
                if (!empty($_POST['remember'])) setcookie('remember_id', $id, time() + 60*60*24*30, '/', '', false, true);
                else setcookie('remember_id', '', time() - 3600, '/');
                header('Location: index.php?page=welcome'); exit;
            }
            $errors['form'] = 'Incorrect email/phone or password.';
        }
    }
}

if ($page === 'welcome' && empty($_SESSION['user'])) { header('Location: index.php?page=login'); exit; }
if (!in_array($page, ['login', 'register', 'welcome'], true)) $page = 'login';

// ---------- View helpers ----------
function field_err(array $errors, string $k): void {
    if (isset($errors[$k])) echo '<p class="err" role="alert">' . e($errors[$k]) . '</p>';
}
function logo(): void { ?>
<svg class="logo" viewBox="0 0 200 200" role="img" aria-label="Cakey Bakey Pastry Shop">
  <circle cx="100" cy="100" r="98" fill="#ecc6ea"/>
  <circle cx="100" cy="100" r="90" fill="none" stroke="#fff" stroke-opacity=".5"/>
  <defs><path id="arc" d="M100,100 m-74,0 a74,74 0 1,1 148,0 a74,74 0 1,1 -148,0"/></defs>
  <text font-size="15.5" fill="#6b2f73" letter-spacing="2.5" font-weight="700">
    <textPath href="#arc" startOffset="2%">CAKEY BAKEY PASTRY SHOP</textPath></text>
  <text x="100" y="118" font-size="62" text-anchor="middle">🧁</text>
  <text x="100" y="150" font-size="9.5" text-anchor="middle" fill="#6b2f73" letter-spacing="1.5">FRESH MORNING BAKES</text>
</svg>
<?php }
$eye = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><line class="slash" x1="3" y1="21" x2="21" y2="3"/></svg>';
$user_svg = '<svg class="usericon" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.5 3.5-7 8-7s8 2.5 8 7"/></svg>';
$ic = [
 'mail'  => '<svg viewBox="0 0 24 24" width="22" height="22" fill="#111"><path d="M3 5h18v14H3z" opacity=".15"/><path d="M2 4h20v16H2V4zm2 2v.5l8 5.5 8-5.5V6H4zm16 3l-8 5.5L4 9v9h16V9z"/></svg>',
 'lock'  => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#111" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>',
 'phone' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#111" stroke-width="2"><path d="M5 3h4l2 5-2.5 1.5a11 11 0 006 6L16 13l5 2v4a2 2 0 01-2 2A16 16 0 013 5a2 2 0 012-2z"/></svg>',
];
$google = '<svg viewBox="0 0 48 48" width="22" height="22"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.5 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 010-9.4l-7.9-6.1a24 24 0 000 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page === 'register' ? 'Sign-Up' : ($page === 'welcome' ? 'Welcome' : 'Log-In') ?> | Cakey Bakey Pastry Shop</title>
<link href="https://fonts.googleapis.com/css2?family=Gelasio:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--sky:#c9ebfd;--lilac:#cf9bdb;--plum:#6b2f73;--field:#c5e6fb;--ink:#b24fbd;--card:rgba(255,255,255,.82);--bad:#b3261e;--good:#1b6b3a}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{font-family:'Gelasio',Georgia,serif;background:var(--sky);color:#111;overflow-x:hidden}
.bubbles{position:fixed;inset:0;z-index:0;pointer-events:none}
.bubbles i{position:absolute;border-radius:50%;background:#cf8fd8;opacity:.85}
.bubbles i:nth-child(1){width:260px;height:300px;left:-70px;top:-60px}
.bubbles i:nth-child(2){width:150px;height:150px;left:34%;top:-60px;background:#d9a7e2}
.bubbles i:nth-child(3){width:340px;height:340px;right:-90px;top:-100px}
.bubbles i:nth-child(4){width:120px;height:120px;left:6%;top:48%;background:#d9a7e2}
.bubbles i:nth-child(5){width:300px;height:300px;left:-60px;bottom:-110px}
.bubbles i:nth-child(6){width:340px;height:340px;right:-80px;bottom:-130px}
.bubbles i:nth-child(7){width:110px;height:110px;right:4%;top:46%;background:#e0b3e6}
.bubbles i:nth-child(8){width:80px;height:80px;right:22%;bottom:6%;background:#d9a7e2}
.wrap{position:relative;z-index:1;min-height:100vh;display:flex}
.side{flex:0 0 41%;background:var(--lilac);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center}
.side .logo{width:min(260px,60%);margin-bottom:28px}
.side h2{font-size:1.25rem;letter-spacing:.04em;margin:0 0 24px;line-height:1.5}
.side p{font-size:.85rem;line-height:1.7;max-width:340px;margin:0;text-transform:uppercase;font-weight:700}
.main{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.card{position:relative;width:100%;max-width:420px;background:var(--card);border-radius:34px;padding:30px 36px 28px;text-align:center;backdrop-filter:blur(3px);box-shadow:0 8px 30px rgba(107,47,115,.12)}
.card h1{font-size:2.5rem;margin:0 0 18px;font-weight:700}
.usericon{width:38px;height:38px;display:block;margin:0 auto -4px}
.card>.logo{position:absolute;top:-4px;right:-44px;width:112px;box-shadow:0 0 0 4px rgba(255,255,255,.4);border-radius:50%}
.field{position:relative;text-align:left;margin-bottom:12px}
.pill{display:flex;align-items:center;gap:12px;background:var(--field);border-radius:999px;padding:0 18px;height:44px;border:2px solid transparent}
.pill:focus-within{border-color:var(--ink)}
.pill.bad{border-color:var(--bad)}
.pill input{flex:1;min-width:0;border:0;background:none;font:600 .92rem 'Gelasio',Georgia,serif;color:var(--ink);outline:none;height:100%}
.pill input::placeholder{color:var(--ink);opacity:1}
.pill svg{flex:none}
.toggle{background:none;border:0;padding:0;cursor:pointer;color:#111;display:flex}
.toggle .slash{display:none}
.toggle.off .slash{display:block}
.err{color:var(--bad);font-size:.78rem;margin:4px 0 0 18px;font-weight:700}
.msg{border-radius:14px;padding:10px 14px;margin:0 0 14px;font-size:.88rem;font-weight:700;text-align:left}
.msg.bad{background:#fde8e6;color:var(--bad)}
.msg.good{background:#e1f5e8;color:var(--good)}
.btn{display:inline-block;background:var(--field);color:var(--ink);border:0;border-radius:999px;height:42px;padding:0 44px;font:700 .95rem 'Gelasio',Georgia,serif;cursor:pointer;margin:6px 0 10px;text-decoration:none;line-height:42px}
.btn:hover,.btn:focus-visible{background:var(--ink);color:#fff;outline:none}
.row{display:flex;justify-content:space-between;font-size:.82rem;margin:2px 6px 8px;color:var(--ink);font-weight:700}
.row label{display:flex;align-items:center;gap:5px;cursor:pointer}
.row a{color:var(--ink);text-decoration:none}
.or{display:flex;align-items:center;gap:10px;color:var(--ink);font-size:.8rem;margin:2px 0 10px}
.or:before,.or:after{content:"";flex:1;border-top:2px dashed var(--ink)}
.google{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:var(--field);border:0;border-radius:999px;height:44px;font:700 .88rem 'Gelasio',Georgia,serif;color:var(--ink);cursor:pointer}
.switch{display:block;margin-top:12px;font-size:.85rem;font-weight:700;color:var(--ink);text-decoration:none}
.switch:hover{text-decoration:underline}
.welcome{max-width:520px}
.welcome .logo{position:static;width:150px;margin:0 auto 10px;display:block;box-shadow:none}
.welcome p{font-size:1rem;line-height:1.6}
@media(max-width:820px){
 .wrap{flex-direction:column}
 .side{flex:none;padding:28px 20px}
 .side .logo{width:130px;margin-bottom:14px}
 .card>.logo{position:static;display:block;margin:0 auto 6px;width:96px;box-shadow:none}
}
@media(max-width:480px){.card{padding:26px 20px;border-radius:26px}.card h1{font-size:2rem}}
</style>
</head>
<body>
<div class="bubbles"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>

<?php if ($page === 'login'): ?>
<div class="wrap">
  <aside class="side">
    <?php logo(); ?>
    <h2>HELLO VALUED CUSTOMER,<br>WELCOME!</h2>
    <p>Here in Cakey Bakey Pastry Shop, you can order our baked goods, and make a customized order using this website. Order now!!</p>
  </aside>
  <main class="main">
    <form class="card" method="post" action="index.php?page=login" novalidate>
      <?= $user_svg ?>
      <h1>Log-In</h1>
      <?php if ($success): ?><p class="msg good" role="status"><?= e($success) ?></p><?php endif; ?>
      <?php if (isset($errors['form'])): ?><p class="msg bad" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="login">

      <div class="field">
        <div class="pill <?= isset($errors['identifier']) ? 'bad' : '' ?>"><?= $ic['mail'] ?>
          <input type="text" name="identifier" placeholder="Email ID/Phone Number" value="<?= e($old['identifier']) ?>" aria-label="Email or phone number" autocomplete="username"></div>
        <?php field_err($errors, 'identifier'); ?>
      </div>
      <div class="field">
        <div class="pill <?= isset($errors['password']) ? 'bad' : '' ?>"><?= $ic['lock'] ?>
          <input type="password" name="password" placeholder="Password" aria-label="Password" autocomplete="current-password">
          <button type="button" class="toggle off" aria-label="Show password"><?= $eye ?></button></div>
        <?php field_err($errors, 'password'); ?>
      </div>
      <div class="row">
        <label><input type="checkbox" name="remember" <?= $old['identifier'] ? 'checked' : '' ?>> Remember Me</label>
        <a href="#" id="forgot">Forget Password?</a>
      </div>
      <button class="btn" type="submit">Log-In</button>
      <a class="switch" href="index.php?page=register">No account? Click Here</a>
    </form>
  </main>
</div>

<?php elseif ($page === 'register'): ?>
<div class="wrap">
  <main class="main">
    <form class="card" method="post" action="index.php?page=register" novalidate>
      <?php logo(); ?>
      <?= $user_svg ?>
      <h1>Sign-Up</h1>
      <?php if (isset($errors['form'])): ?><p class="msg bad" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="register">

      <div class="field">
        <div class="pill <?= isset($errors['email']) ? 'bad' : '' ?>"><?= $ic['mail'] ?>
          <input type="email" name="email" placeholder="Email ID" value="<?= e($old['email']) ?>" aria-label="Email" autocomplete="email"></div>
        <?php field_err($errors, 'email'); ?>
      </div>
      <div class="field">
        <div class="pill <?= isset($errors['password']) ? 'bad' : '' ?>"><?= $ic['lock'] ?>
          <input type="password" name="password" placeholder="Password" aria-label="Password" autocomplete="new-password">
          <button type="button" class="toggle off" aria-label="Show password"><?= $eye ?></button></div>
        <?php field_err($errors, 'password'); ?>
      </div>
      <div class="field">
        <div class="pill <?= isset($errors['confirm']) ? 'bad' : '' ?>"><?= $ic['lock'] ?>
          <input type="password" name="confirm" placeholder="Confirm Password" aria-label="Confirm password" autocomplete="new-password">
          <button type="button" class="toggle off" aria-label="Show password"><?= $eye ?></button></div>
        <?php field_err($errors, 'confirm'); ?>
      </div>
      <div class="field">
        <div class="pill <?= isset($errors['phone']) ? 'bad' : '' ?>"><?= $ic['phone'] ?>
          <input type="tel" name="phone" placeholder="Phone Number(Optional)" value="<?= e($old['phone']) ?>" aria-label="Phone number (optional)" autocomplete="tel"></div>
        <?php field_err($errors, 'phone'); ?>
      </div>
      <button class="btn" type="submit">Sign-Up</button>
      <div class="or">or</div>
      <button class="google" type="button" id="google"><?= $google ?> Sign-Up with Google Account</button>
      <a class="switch" href="index.php?page=login">Have an account? Click here</a>
    </form>
  </main>
</div>

<?php else: /* welcome */ ?>
<div class="wrap">
  <main class="main">
    <section class="card welcome">
      <?php logo(); ?>
      <h1>Welcome!</h1>
      <p>You're logged in as <strong><?= e($_SESSION['user']) ?></strong>.<br>Fresh morning bakes are waiting for your order.</p>
      <a class="btn" href="index.php?page=logout">Log Out</a>
    </section>
  </main>
</div>
<?php endif; ?>

<script>
// Show / hide password
document.querySelectorAll('.toggle').forEach(function (b) {
  b.addEventListener('click', function () {
    var input = b.parentElement.querySelector('input');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.classList.toggle('off', !show);
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
var f = document.getElementById('forgot');
if (f) f.addEventListener('click', function (ev) { ev.preventDefault(); alert('Password reset is not available yet. Please contact Cakey Bakey Pastry Shop.'); });
var g = document.getElementById('google');
if (g) g.addEventListener('click', function () { alert('Google sign-up is a design placeholder in this project.'); });

// Quick client-side checks (server validates again)
document.querySelectorAll('form.card').forEach(function (form) {
  form.addEventListener('submit', function (ev) {
    var bad = false;
    form.querySelectorAll('.err.js').forEach(function (n) { n.remove(); });
    function flag(input, text) {
      var field = input.closest('.field');
      var p = document.createElement('p'); p.className = 'err js'; p.textContent = text;
      field.appendChild(p); field.querySelector('.pill').classList.add('bad'); bad = true;
    }
    form.querySelectorAll('.pill.bad').forEach(function (n) { n.classList.remove('bad'); });
    var email = form.elements.email, pw = form.elements.password, cf = form.elements.confirm, id = form.elements.identifier;
    if (id && !id.value.trim()) flag(id, 'Enter your email or phone number.');
    if (email) {
      if (!email.value.trim()) flag(email, 'Email is required.');
      else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) flag(email, 'Enter a valid email address, like name@example.com.');
    }
    if (pw && !pw.value) flag(pw, 'Password is required.');
    else if (pw && cf) {
      if (pw.value.length < 8) flag(pw, 'Password must be at least 8 characters.');
      else if (!/[A-Z]/.test(pw.value) || !/[a-z]/.test(pw.value) || !/\d/.test(pw.value)) flag(pw, 'Use at least one uppercase letter, one lowercase letter and one number.');
    }
    if (cf) {
      if (!cf.value) flag(cf, 'Please confirm your password.');
      else if (cf.value !== pw.value) flag(cf, 'Passwords do not match.');
    }
    if (bad) ev.preventDefault();
  });
});
</script>
</body>
</html>
