<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Ayarlar';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'company') {
        $fields = ['company_name','company_email','company_phone','company_address','company_city','company_website','company_tax','company_tax_office','quote_prefix','default_currency','default_tax_rate','quote_validity_days'];
        foreach ($fields as $f) {
            setSetting($f, trim($_POST[$f] ?? ''));
        }
        // Logo upload
        if (!empty($_FILES['logo']['name'])) {
            $result = uploadLogo($_FILES['logo']);
            if ($result['success']) {
                setSetting('company_logo', $result['filename']);
            } else {
                flash('error', $result['error']);
            }
        }
        flash('success', 'Firma bilgileri güncellendi.');
        header('Location: index.php');
        exit;
    }

    if ($action === 'bank') {
        $fields = ['bank_name','bank_branch','bank_iban','bank_swift','bank_account_name'];
        foreach ($fields as $f) {
            setSetting($f, trim($_POST[$f] ?? ''));
        }
        flash('success', 'Banka bilgileri güncellendi.');
        header('Location: index.php?tab=bank');
        exit;
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';
        $user = getCurrentUser();
        if (!password_verify($current, $user['password'])) {
            flash('error', 'Mevcut şifre hatalı.');
        } elseif ($new !== $new2) {
            flash('error', 'Yeni şifreler eşleşmiyor.');
        } elseif (strlen($new) < 6) {
            flash('error', 'Şifre en az 6 karakter olmalıdır.');
        } else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flash('success', 'Şifre güncellendi.');
        }
        header('Location: index.php?tab=security');
        exit;
    }
}

$tab = $_GET['tab'] ?? 'company';

$s = [];
$keys = ['company_name','company_email','company_phone','company_address','company_city','company_website','company_tax','company_tax_office','quote_prefix','default_currency','default_tax_rate','quote_validity_days','company_logo','bank_name','bank_branch','bank_iban','bank_swift','bank_account_name'];
foreach ($keys as $k) $s[$k] = getSetting($k, '');

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span> Ayarlar
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body p-2">
                <nav class="d-flex flex-column gap-1">
                    <?php
                    $tabs = [
                        'company'  => ['bi-building','Firma Bilgileri'],
                        'bank'     => ['bi-bank','Banka Bilgileri'],
                        'security' => ['bi-shield-lock','Güvenlik'],
                    ];
                    foreach ($tabs as $tid => [$icon, $label]):
                    ?>
                    <a href="?tab=<?= $tid ?>" class="nav-item <?= $tab === $tid ? 'active' : '' ?>">
                        <i class="bi <?= $icon ?>"></i> <span><?= $label ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>

    <div class="col-md-9">

    <?php if ($tab === 'company'): ?>
    <div class="card">
        <div class="card-header"><span><i class="bi bi-building text-primary me-2"></i>Firma Bilgileri</span></div>
        <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="company">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Firma Logosu</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <?php if ($s['company_logo']): ?>
                            <img src="<?= BASE_URL ?>/<?= sanitize($s['company_logo']) ?>" style="max-height:60px;max-width:160px;object-fit:contain;background:#f0f4f8;padding:6px;border-radius:8px;border:1.5px solid var(--border)">
                        <?php else: ?>
                            <div style="width:100px;height:60px;background:var(--bg-body);border:1.5px dashed var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-muted)"><i class="bi bi-image" style="font-size:24px"></i></div>
                        <?php endif; ?>
                        <div>
                            <input type="file" name="logo" class="form-control" accept="image/*" style="max-width:300px">
                            <div style="font-size:12px;color:var(--text-muted);margin-top:4px">JPG, PNG, SVG, WebP - Max 5MB</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Firma Adı <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control" value="<?= sanitize($s['company_name'] ?: 'HSG Aviation') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Teklif No Ön Eki</label>
                    <input type="text" name="quote_prefix" class="form-control" value="<?= sanitize($s['quote_prefix'] ?: 'HSG') ?>" placeholder="HSG, TKL...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="company_email" class="form-control" value="<?= sanitize($s['company_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="company_phone" class="form-control" value="<?= sanitize($s['company_phone']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Adres</label>
                    <textarea name="company_address" class="form-control" rows="2"><?= sanitize($s['company_address']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Şehir</label>
                    <input type="text" name="company_city" class="form-control" value="<?= sanitize($s['company_city']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Web Sitesi</label>
                    <input type="url" name="company_website" class="form-control" value="<?= sanitize($s['company_website'] ?: 'https://hsgaviation.com') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vergi Numarası</label>
                    <input type="text" name="company_tax" class="form-control" value="<?= sanitize($s['company_tax']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vergi Dairesi</label>
                    <input type="text" name="company_tax_office" class="form-control" value="<?= sanitize($s['company_tax_office']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Varsayılan Para Birimi</label>
                    <select name="default_currency" class="form-select">
                        <option value="TRY" <?= $s['default_currency'] === 'TRY' ? 'selected' : '' ?>>₺ Türk Lirası (TRY)</option>
                        <option value="USD" <?= $s['default_currency'] === 'USD' ? 'selected' : '' ?>>$ Dolar (USD)</option>
                        <option value="EUR" <?= $s['default_currency'] === 'EUR' ? 'selected' : '' ?>>€ Euro (EUR)</option>
                        <option value="GBP" <?= $s['default_currency'] === 'GBP' ? 'selected' : '' ?>>£ Sterlin (GBP)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Varsayılan KDV Oranı (%)</label>
                    <select name="default_tax_rate" class="form-select">
                        <?php foreach ([0,1,8,10,18,20] as $t): ?>
                            <option value="<?= $t ?>" <?= $s['default_tax_rate'] == $t ? 'selected' : '' ?>>%<?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Teklif Geçerlilik (Gün)</label>
                    <input type="number" name="quote_validity_days" class="form-control" value="<?= sanitize($s['quote_validity_days'] ?: '30') ?>" min="1">
                </div>
            </div>
            <hr class="section-divider">
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Kaydet</button>
            </div>
        </form>
        </div>
    </div>

    <?php elseif ($tab === 'bank'): ?>
    <div class="card">
        <div class="card-header"><span><i class="bi bi-bank text-primary me-2"></i>Banka Bilgileri</span></div>
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="bank">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Banka Adı</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= sanitize($s['bank_name']) ?>" placeholder="Ziraat Bankası, İş Bankası...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Şube</label>
                    <input type="text" name="bank_branch" class="form-control" value="<?= sanitize($s['bank_branch']) ?>" placeholder="Şube adı">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hesap Sahibi</label>
                    <input type="text" name="bank_account_name" class="form-control" value="<?= sanitize($s['bank_account_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">SWIFT/BIC Kodu</label>
                    <input type="text" name="bank_swift" class="form-control" value="<?= sanitize($s['bank_swift']) ?>" placeholder="TCZBTR2A">
                </div>
                <div class="col-12">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="bank_iban" class="form-control text-mono" value="<?= sanitize($s['bank_iban']) ?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
                </div>
            </div>
            <hr class="section-divider">
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Kaydet</button>
            </div>
        </form>
        </div>
    </div>

    <?php elseif ($tab === 'security'): ?>
    <div class="card">
        <div class="card-header"><span><i class="bi bi-shield-lock text-primary me-2"></i>Şifre Değiştir</span></div>
        <div class="card-body">
        <form method="post" style="max-width:400px">
            <input type="hidden" name="action" value="password">
            <div class="mb-3">
                <label class="form-label">Mevcut Şifre</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Yeni Şifre</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label">Yeni Şifre (Tekrar)</label>
                <input type="password" name="new_password2" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Şifreyi Güncelle</button>
        </form>
        </div>
    </div>
    <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
