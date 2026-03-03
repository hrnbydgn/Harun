<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: kurulum.php');
    exit;
}
require_once 'config.php';
require_once 'includes/functions.php';
define('BASE_URL', '');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı.';
            }
        } catch (Exception $e) {
            $error = 'Veritabanı hatası. <a href="kurulum.php">Kurulum</a>';
        }
    } else {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    }
}

$companyName = '';
try { $companyName = getSetting('company_name', 'HSG Aviation'); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş | Teklif Yönetimi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0d1b2a 0%, #1a3a5c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-wrap { width: 100%; max-width: 420px; }
        .login-brand { text-align: center; margin-bottom: 30px; color: white; }
        .login-icon { width: 68px; height: 68px; background: linear-gradient(135deg, #0066cc, #ff6b35); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; margin: 0 auto 16px; box-shadow: 0 8px 24px rgba(0,102,204,.45); }
        .login-brand h1 { font-size: 22px; font-weight: 700; }
        .login-brand p { color: rgba(255,255,255,.55); font-size: 13.5px; margin-top: 5px; }
        .login-card { background: white; border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .login-card h2 { font-size: 19px; font-weight: 700; margin-bottom: 6px; }
        .login-card p { color: #5a6a7a; font-size: 13.5px; margin-bottom: 24px; }
        label { font-size: 13px; font-weight: 600; color: #5a6a7a; margin-bottom: 5px; display: block; }
        .input-icon-wrap { position: relative; }
        .input-icon-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #8a9ab0; font-size: 16px; }
        input[type=text], input[type=password] { width: 100%; padding: 11px 14px 11px 40px; border: 1.5px solid #dde3ec; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color .2s; }
        input:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.1); }
        .btn-login { width: 100%; padding: 12px; background: linear-gradient(135deg, #0066cc, #1a8cff); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .2s; margin-top: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,102,204,.4); }
        .alert-err { background: #fef2f2; border: 1.5px solid #fecaca; color: #7f1d1d; padding: 11px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .form-group { margin-bottom: 16px; }
        .password-toggle { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8a9ab0; background: none; border: none; }
        .footer-link { text-align: center; margin-top: 18px; color: rgba(255,255,255,.5); font-size: 12.5px; }
        .footer-link a { color: rgba(255,255,255,.75); }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="login-icon"><i class="bi bi-send-fill"></i></div>
        <h1><?= htmlspecialchars($companyName ?: 'HSG Aviation') ?></h1>
        <p>Teklif Yönetim Sistemi</p>
    </div>
    <div class="login-card">
        <h2>Hoş Geldiniz</h2>
        <p>Devam etmek için giriş yapın</p>
        <?php if ($error): ?>
            <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= $error ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <div class="input-icon-wrap" style="position:relative">
                    <i class="bi bi-person-fill"></i>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Kullanıcı adı" autocomplete="username" required>
                </div>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <div class="input-icon-wrap" style="position:relative">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" name="password" id="passInput" placeholder="Şifre" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" onclick="togglePass()"><i class="bi bi-eye" id="passEye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Giriş Yap</button>
        </form>
    </div>
    <div class="footer-link">Kurulum için: <a href="kurulum.php">kurulum.php</a></div>
</div>
<script>
function togglePass() {
    var inp = document.getElementById('passInput');
    var eye = document.getElementById('passEye');
    if (inp.type === 'password') { inp.type = 'text'; eye.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; eye.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
