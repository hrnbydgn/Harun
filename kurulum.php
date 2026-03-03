<?php
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? 'teklif_db');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
            $_SESSION['setup'] = compact('host', 'port', 'name', 'user', 'pass');
            header('Location: kurulum.php?step=2');
            exit;
        } catch (PDOException $e) {
            $error = 'Veritabanı bağlantısı başarısız: ' . $e->getMessage();
        }
    } elseif ($step === 2) {
        $s = $_SESSION['setup'] ?? null;
        if (!$s) { header('Location: kurulum.php?step=1'); exit; }
        try {
            $pdo = new PDO("mysql:host={$s['host']};port={$s['port']};dbname={$s['name']};charset=utf8mb4", $s['user'], $s['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            createTables($pdo);
            $success = 'Tablolar başarıyla oluşturuldu.';
            header('Location: kurulum.php?step=3');
            exit;
        } catch (Exception $e) {
            $error = 'Tablolar oluşturulamadı: ' . $e->getMessage();
        }
    } elseif ($step === 3) {
        $s = $_SESSION['setup'] ?? null;
        if (!$s) { header('Location: kurulum.php?step=1'); exit; }
        $adminName  = trim($_POST['admin_name'] ?? 'Admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminUser  = trim($_POST['admin_user'] ?? 'admin');
        $adminPass  = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';

        if (empty($adminUser) || empty($adminPass)) { $error = 'Kullanıcı adı ve şifre boş olamaz.'; }
        elseif ($adminPass !== $adminPass2) { $error = 'Şifreler eşleşmiyor.'; }
        elseif (strlen($adminPass) < 6) { $error = 'Şifre en az 6 karakter olmalıdır.'; }
        else {
            $pdo = new PDO("mysql:host={$s['host']};port={$s['port']};dbname={$s['name']};charset=utf8mb4", $s['user'], $s['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role) VALUES (?,?,?,?,'admin') ON DUPLICATE KEY UPDATE password=?, name=?, email=?");
            $stmt->execute([$adminName, $adminEmail, $adminUser, $hash, $hash, $adminName, $adminEmail]);
            $_SESSION['setup']['admin'] = $adminUser;
            header('Location: kurulum.php?step=4');
            exit;
        }
    } elseif ($step === 4) {
        $s = $_SESSION['setup'] ?? null;
        if (!$s) { header('Location: kurulum.php?step=1'); exit; }

        $companyName    = trim($_POST['company_name'] ?? 'HSG Aviation');
        $companyEmail   = trim($_POST['company_email'] ?? '');
        $companyPhone   = trim($_POST['company_phone'] ?? '');
        $companyAddress = trim($_POST['company_address'] ?? '');
        $companyWebsite = trim($_POST['company_website'] ?? 'https://hsgaviation.com');
        $companyTax     = trim($_POST['company_tax'] ?? '');
        $companyTaxOff  = trim($_POST['company_tax_office'] ?? '');
        $quotePrefix    = trim($_POST['quote_prefix'] ?? 'TKL');
        $currency       = trim($_POST['currency'] ?? 'TRY');

        $pdo = new PDO("mysql:host={$s['host']};port={$s['port']};dbname={$s['name']};charset=utf8mb4", $s['user'], $s['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $settings = [
            'company_name'    => $companyName,
            'company_email'   => $companyEmail,
            'company_phone'   => $companyPhone,
            'company_address' => $companyAddress,
            'company_website' => $companyWebsite,
            'company_tax'     => $companyTax,
            'company_tax_office' => $companyTaxOff,
            'quote_prefix'    => $quotePrefix,
            'default_currency'=> $currency,
            'quote_validity_days' => '30',
            'default_tax_rate' => '20',
        ];
        $stmt = $pdo->prepare("INSERT INTO company_settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }

        // Add default product categories
        $cats = ['Drone Sistemleri', 'Yedek Parça', 'Yazılım & Lisans', 'Eğitim & Sertifika', 'Bakım & Servis', 'Aksesuar'];
        $catStmt = $pdo->prepare("INSERT IGNORE INTO product_categories (name) VALUES (?)");
        foreach ($cats as $cat) $catStmt->execute([$cat]);

        // Default template
        $pdo->exec("INSERT IGNORE INTO quote_templates (id, name, is_default, validity_days, footer_text, terms_conditions) VALUES 
            (1, 'Standart Teklif', 1, 30, 'Bu teklif " . date('Y') . " yılında " . $companyName . " tarafından hazırlanmıştır.', 'Teklif geçerlilik süresi 30 gündür.\nÖdemeler peşin veya mutabık kalınan koşullara göre gerçekleştirilir.\nKDV fiyatlara dahil değildir.')");

        // Write config
        $configContent = '<?php' . "\n"
            . "define('DB_HOST', '" . addslashes($s['host']) . "');\n"
            . "define('DB_PORT', '" . addslashes($s['port']) . "');\n"
            . "define('DB_NAME', '" . addslashes($s['name']) . "');\n"
            . "define('DB_USER', '" . addslashes($s['user']) . "');\n"
            . "define('DB_PASS', '" . addslashes($s['pass']) . "');\n"
            . "define('APP_NAME', 'Teklif Yönetimi');\n"
            . "define('APP_URL', '');\n"
            . "define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');\n"
            . "define('VERSION', '1.0.0');\n\n"
            . "function getDB() {\n"
            . "    static \$pdo = null;\n"
            . "    if (\$pdo === null) {\n"
            . "        try {\n"
            . "            \$pdo = new PDO(\n"
            . "                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',\n"
            . "                DB_USER, DB_PASS,\n"
            . "                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci']\n"
            . "            );\n"
            . "        } catch (PDOException \$e) {\n"
            . "            die('<div style=\"font-family:sans-serif;padding:30px;color:#ff4444;\">Veritabanı bağlantı hatası: ' . \$e->getMessage() . '</div>');\n"
            . "        }\n"
            . "    }\n"
            . "    return \$pdo;\n"
            . "}\n";

        file_put_contents(__DIR__ . '/config.php', $configContent);

        $success = 'Kurulum tamamlandı!';
        unset($_SESSION['setup']);
        header('Location: kurulum.php?step=5');
        exit;
    }
}

function createTables(PDO $pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS company_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        key_name VARCHAR(100) UNIQUE NOT NULL,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS customers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_name VARCHAR(200),
        contact_name VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'Türkiye',
        tax_number VARCHAR(50),
        tax_office VARCHAR(100),
        notes TEXT,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS product_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT,
        code VARCHAR(50),
        name VARCHAR(200) NOT NULL,
        description TEXT,
        unit VARCHAR(50) DEFAULT 'Adet',
        price DECIMAL(12,2) DEFAULT 0.00,
        currency VARCHAR(10) DEFAULT 'TRY',
        tax_rate DECIMAL(5,2) DEFAULT 20.00,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quote_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        header_text TEXT,
        footer_text TEXT,
        terms_conditions TEXT,
        notes TEXT,
        validity_days INT DEFAULT 30,
        discount_type ENUM('percent','amount') DEFAULT 'percent',
        discount_value DECIMAL(10,2) DEFAULT 0.00,
        show_bank_info TINYINT(1) DEFAULT 1,
        show_terms TINYINT(1) DEFAULT 1,
        template_style VARCHAR(50) DEFAULT 'modern',
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quotes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quote_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INT,
        template_id INT,
        title VARCHAR(200),
        status ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
        issue_date DATE,
        valid_until DATE,
        currency VARCHAR(10) DEFAULT 'TRY',
        subtotal DECIMAL(12,2) DEFAULT 0.00,
        discount_type ENUM('percent','amount') DEFAULT 'percent',
        discount_value DECIMAL(10,2) DEFAULT 0.00,
        discount_amount DECIMAL(12,2) DEFAULT 0.00,
        tax_total DECIMAL(12,2) DEFAULT 0.00,
        total DECIMAL(12,2) DEFAULT 0.00,
        notes TEXT,
        terms TEXT,
        footer_text TEXT,
        header_text TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (template_id) REFERENCES quote_templates(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quote_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quote_id INT NOT NULL,
        product_id INT,
        code VARCHAR(50),
        name VARCHAR(200) NOT NULL,
        description TEXT,
        unit VARCHAR(50) DEFAULT 'Adet',
        quantity DECIMAL(10,3) DEFAULT 1.000,
        unit_price DECIMAL(12,2) DEFAULT 0.00,
        tax_rate DECIMAL(5,2) DEFAULT 20.00,
        discount_percent DECIMAL(5,2) DEFAULT 0.00,
        subtotal DECIMAL(12,2) DEFAULT 0.00,
        tax_amount DECIMAL(12,2) DEFAULT 0.00,
        total DECIMAL(12,2) DEFAULT 0.00,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quote_activity (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quote_id INT NOT NULL,
        user_id INT,
        action VARCHAR(100),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    foreach (explode(';', $sql) as $q) {
        $q = trim($q);
        if ($q) $pdo->exec($q);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum - Teklif Yönetim Sistemi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0d1b2a 0%, #1a3a5c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .setup-container { width: 100%; max-width: 580px; }
        .setup-header { text-align: center; margin-bottom: 30px; color: white; }
        .setup-header h1 { font-size: 26px; font-weight: 700; }
        .setup-header p { color: rgba(255,255,255,.65); font-size: 14px; margin-top: 6px; }
        .brand-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #0066cc, #ff6b35); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; margin: 0 auto 16px; }
        .setup-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .setup-card-header { background: linear-gradient(135deg, #0d1b2a, #1a3a5c); color: white; padding: 24px 28px; }
        .setup-card-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .setup-card-header p { color: rgba(255,255,255,.65); font-size: 13px; margin: 6px 0 0; }
        .setup-card-body { padding: 28px; }
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 28px; }
        .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid rgba(255,255,255,.3); color: rgba(255,255,255,.5); font-size: 13px; }
        .step-dot.done { background: #10b981; border-color: #10b981; color: white; }
        .step-dot.current { background: #0066cc; border-color: #0066cc; color: white; box-shadow: 0 0 0 4px rgba(0,102,204,.25); }
        .step-line { width: 40px; height: 2px; background: rgba(255,255,255,.2); }
        .step-line.done { background: #10b981; }
        label { font-size: 13px; font-weight: 600; color: #5a6a7a; margin-bottom: 5px; display: block; }
        input, select { width: 100%; padding: 10px 14px; border: 1.5px solid #dde3ec; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color .2s; }
        input:focus, select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.1); }
        .btn-setup { width: 100%; padding: 12px; background: linear-gradient(135deg, #0066cc, #1a8cff); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .2s; margin-top: 8px; }
        .btn-setup:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,102,204,.4); }
        .alert-err { background: #fef2f2; border: 1.5px solid #fecaca; color: #7f1d1d; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .alert-ok { background: #f0fdf4; border: 1.5px solid #bbf7d0; color: #14532d; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; color: white; margin: 0 auto 20px; }
        .feature-list { list-style: none; padding: 0; }
        .feature-list li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f4f8; font-size: 13.5px; }
        .feature-list li:last-child { border-bottom: none; }
        .feature-list li i { color: #10b981; font-size: 16px; }
    </style>
</head>
<body>
<div class="setup-container">
    <div class="setup-header">
        <div class="brand-icon"><i class="bi bi-send-fill"></i></div>
        <h1>Teklif Yönetim Sistemi</h1>
        <p>Kurulum Sihirbazı</p>
    </div>

    <div class="setup-card">
        <div class="setup-card-header">
            <div class="step-indicator">
                <?php
                $steps = ['DB', 'Tablo', 'Admin', 'Firma', 'Tamam'];
                for ($i = 1; $i <= 5; $i++):
                    if ($i > 1): ?><div class="step-line <?= $i <= $step ? 'done' : '' ?>"></div><?php endif; ?>
                    <div class="step-dot <?= $i < $step ? 'done' : ($i == $step ? 'current' : '') ?>">
                        <?= $i < $step ? '<i class="bi bi-check"></i>' : $i ?>
                    </div>
                <?php endfor; ?>
            </div>
            <?php if ($step === 1): ?>
                <h2>Veritabanı Bağlantısı</h2><p>MySQL bağlantı bilgilerinizi girin</p>
            <?php elseif ($step === 2): ?>
                <h2>Tabloları Oluştur</h2><p>Veritabanı tabloları oluşturulacak</p>
            <?php elseif ($step === 3): ?>
                <h2>Yönetici Hesabı</h2><p>Sisteme giriş için admin hesabı oluşturun</p>
            <?php elseif ($step === 4): ?>
                <h2>Firma Bilgileri</h2><p>Tekliflerde görünecek firma bilgilerinizi girin</p>
            <?php else: ?>
                <h2>Kurulum Tamamlandı!</h2><p>Sisteminiz kullanıma hazır</p>
            <?php endif; ?>
        </div>

        <div class="setup-card-body">
            <?php if ($error): ?>
                <div class="alert-err"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-ok"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <form method="post">
                <div class="form-row">
                    <div>
                        <label>MySQL Host</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                    </div>
                    <div>
                        <label>Port</label>
                        <input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
                    </div>
                </div>
                <div style="margin-top:14px">
                    <label>Veritabanı Adı</label>
                    <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'teklif_db') ?>" required>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>Kullanıcı Adı</label>
                        <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
                    </div>
                    <div>
                        <label>Şifre</label>
                        <input type="password" name="db_pass" value="">
                    </div>
                </div>
                <button type="submit" class="btn-setup" style="margin-top:24px"><i class="bi bi-arrow-right me-2"></i>Bağlantıyı Test Et & Devam Et</button>
            </form>

            <?php elseif ($step === 2): ?>
            <form method="post">
                <p style="color:#5a6a7a;font-size:14px;margin-bottom:20px">Aşağıdaki tablolar oluşturulacak:</p>
                <ul class="feature-list">
                    <li><i class="bi bi-check-circle-fill"></i> Kullanıcılar (users)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Firma Ayarları (company_settings)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Müşteriler (customers)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Ürün Kategorileri (product_categories)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Ürünler (products)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Teklif Şablonları (quote_templates)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Teklifler (quotes)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Teklif Kalemleri (quote_items)</li>
                    <li><i class="bi bi-check-circle-fill"></i> Teklif Aktiviteleri (quote_activity)</li>
                </ul>
                <button type="submit" class="btn-setup"><i class="bi bi-database-fill me-2"></i>Tabloları Oluştur</button>
            </form>

            <?php elseif ($step === 3): ?>
            <form method="post">
                <div>
                    <label>Ad Soyad</label>
                    <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" placeholder="Admin Kullanıcı" required>
                </div>
                <div style="margin-top:14px">
                    <label>E-posta</label>
                    <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@sirket.com">
                </div>
                <div style="margin-top:14px">
                    <label>Kullanıcı Adı</label>
                    <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>Şifre</label>
                        <input type="password" name="admin_pass" required>
                    </div>
                    <div>
                        <label>Şifre Tekrar</label>
                        <input type="password" name="admin_pass2" required>
                    </div>
                </div>
                <button type="submit" class="btn-setup" style="margin-top:24px"><i class="bi bi-person-check me-2"></i>Hesabı Oluştur</button>
            </form>

            <?php elseif ($step === 4): ?>
            <form method="post">
                <div>
                    <label>Firma Adı</label>
                    <input type="text" name="company_name" value="HSG Aviation" required>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>E-posta</label>
                        <input type="email" name="company_email" value="info@hsgaviation.com">
                    </div>
                    <div>
                        <label>Telefon</label>
                        <input type="text" name="company_phone" value="">
                    </div>
                </div>
                <div style="margin-top:14px">
                    <label>Adres</label>
                    <input type="text" name="company_address" value="İstanbul Trakya Serbest Bölgesi" placeholder="Firma adresi">
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>Web Sitesi</label>
                        <input type="url" name="company_website" value="https://hsgaviation.com">
                    </div>
                    <div>
                        <label>Vergi No</label>
                        <input type="text" name="company_tax" value="">
                    </div>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>Vergi Dairesi</label>
                        <input type="text" name="company_tax_office" value="">
                    </div>
                    <div>
                        <label>Para Birimi</label>
                        <select name="currency">
                            <option value="TRY" selected>₺ Türk Lirası (TRY)</option>
                            <option value="USD">$ Dolar (USD)</option>
                            <option value="EUR">€ Euro (EUR)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <div>
                        <label>Teklif Numarası Ön Eki</label>
                        <input type="text" name="quote_prefix" value="HSG" placeholder="TKL">
                    </div>
                </div>
                <button type="submit" class="btn-setup" style="margin-top:24px"><i class="bi bi-check-circle me-2"></i>Kurulumu Tamamla</button>
            </form>

            <?php else: ?>
            <div style="text-align:center;padding:10px 0">
                <div class="success-icon"><i class="bi bi-check-lg"></i></div>
                <h3 style="font-size:20px;font-weight:700;margin-bottom:8px">Kurulum Başarıyla Tamamlandı!</h3>
                <p style="color:#5a6a7a;font-size:14px;margin-bottom:24px">Sisteminiz kullanıma hazır. Güvenlik için kurulum dosyasını silin veya yeniden adlandırın.</p>
                <ul class="feature-list" style="text-align:left;margin-bottom:24px">
                    <li><i class="bi bi-check-circle-fill"></i> Veritabanı ve tablolar oluşturuldu</li>
                    <li><i class="bi bi-check-circle-fill"></i> Yönetici hesabı oluşturuldu</li>
                    <li><i class="bi bi-check-circle-fill"></i> Firma bilgileri kaydedildi</li>
                    <li><i class="bi bi-check-circle-fill"></i> Varsayılan şablon oluşturuldu</li>
                    <li><i class="bi bi-check-circle-fill"></i> Ürün kategorileri oluşturuldu</li>
                </ul>
                <a href="login.php" style="display:inline-flex;align-items:center;gap:8px;padding:13px 28px;background:linear-gradient(135deg,#0066cc,#1a8cff);color:white;border-radius:10px;text-decoration:none;font-size:15px;font-weight:600;box-shadow:0 4px 16px rgba(0,102,204,.35)">
                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
