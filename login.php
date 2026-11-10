<?php
require_once 'config.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if ($user && password_verify($p, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama']     = $user['nama'] ?: $user['username'];
        $_SESSION['role']     = $user['role'];
        redirect('index.php');
    } else {
        $err = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
<meta name="theme-color" content="#4f46e5">
<meta name="mobile-web-app-capable" content="yes">
<title>Login · <?= htmlspecialchars(NAMA_TOKO) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{
  font-family:'Roboto','Google Sans',system-ui,-apple-system,'Segoe UI',sans-serif;
  background:linear-gradient(160deg,#4f46e5 0%,#6366f1 40%,#818cf8 100%);
  min-height:100vh;display:flex;flex-direction:column;justify-content:flex-end;
  -webkit-font-smoothing:antialiased;
}
.top{
  flex:1;display:grid;place-items:center;
  padding:48px 24px 28px;color:#fff;text-align:center;
}
.logo img{
  max-height:88px;max-width:200px;background:#fff;
  border-radius:22px;padding:10px;margin-bottom:16px;
  box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.logo .brand-icon{
  width:80px;height:80px;background:#fff;border-radius:24px;
  display:inline-grid;place-items:center;color:#4f46e5;
  font-size:34px;font-weight:700;margin-bottom:16px;
  box-shadow:0 8px 24px rgba(0,0,0,.18);
}
h1{font-size:24px;font-weight:600;letter-spacing:.2px}
p.sub{font-size:14.5px;opacity:.88;margin-top:6px;font-weight:400}
.box{
  background:#fff;
  padding:32px 24px calc(32px + env(safe-area-inset-bottom,0px));
  border-radius:32px 32px 0 0;width:100%;max-width:480px;margin:0 auto;
  animation:up .4s cubic-bezier(.2,0,0,1);
  box-shadow:0 -4px 24px rgba(0,0,0,.08);
}
@keyframes up{from{transform:translateY(48px);opacity:0}to{transform:none;opacity:1}}
label{
  display:block;font-size:12.5px;font-weight:600;
  margin-bottom:6px;color:#49465a;
}
input{
  width:100%;padding:14px 16px;
  border:1.5px solid #c8c8d8;border-radius:14px;
  font-size:16px;margin-bottom:18px;font-family:inherit;
  min-height:52px;background:#fff;color:#1c1b1f;
  transition:border-color .15s,box-shadow .15s;
}
input:focus{
  outline:none;border-color:#4f46e5;
  box-shadow:0 0 0 3px rgba(79,70,229,.15);
}
button{
  width:100%;height:52px;background:#4f46e5;color:#fff;
  border:none;border-radius:26px;font-size:15.5px;font-weight:600;
  cursor:pointer;font-family:inherit;margin-top:6px;
  box-shadow:0 2px 8px rgba(79,70,229,.35);
  transition:filter .12s,transform .12s;
}
button:active{filter:brightness(.92);transform:scale(.98)}
.err{
  background:#fee2e2;color:#991b1b;
  padding:12px 14px;border-radius:14px;
  font-size:13.5px;margin-bottom:18px;text-align:center;font-weight:500;
}
@media (min-width:600px){
  body{justify-content:center;padding:24px;background:#eef0ff}
  .top{flex:none;padding-bottom:20px}
  .top,.top h1,.top p{color:#1c1b1f}
  .box{border-radius:28px;box-shadow:0 12px 40px rgba(79,70,229,.12)}
  body{background:linear-gradient(160deg,#eef0ff 0%,#e0e7ff 100%)}
  .top h1{color:#1c1b1f}
  .top p.sub{color:#6b6b80}
}
</style></head><body>
<div class="top"><div class="logo">
    <?php if (LOGO_PATH && file_exists(LOGO_PATH)): ?>
      <img src="<?= LOGO_PATH ?>" alt="Logo">
    <?php else: ?>
      <div class="brand-icon"><?= strtoupper(mb_substr(NAMA_TOKO, 0, 1)) ?></div>
    <?php endif; ?>
    <h1><?= htmlspecialchars(NAMA_TOKO) ?></h1>
    <p class="sub">Silakan login untuk melanjutkan</p>
  </div></div>
<form class="box" method="post">
  <?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>
  <label>Username</label>
  <input name="username" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
  <label>Password</label>
  <input type="password" name="password" required autocomplete="current-password">
  <button type="submit">Masuk</button>
</form>
</body></html>
