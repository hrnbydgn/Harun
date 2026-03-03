<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Yeni Müşteri';
requireLogin();

$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'contact_name' => trim($_POST['contact_name'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'phone'        => trim($_POST['phone'] ?? ''),
        'address'      => trim($_POST['address'] ?? ''),
        'city'         => trim($_POST['city'] ?? ''),
        'country'      => trim($_POST['country'] ?? 'Türkiye'),
        'tax_number'   => trim($_POST['tax_number'] ?? ''),
        'tax_office'   => trim($_POST['tax_office'] ?? ''),
        'notes'        => trim($_POST['notes'] ?? ''),
        'status'       => $_POST['status'] ?? 'active',
    ];
    if (empty($data['company_name']) && empty($data['contact_name'])) {
        $errors[] = 'Firma adı veya iletişim kişisi zorunludur.';
    }
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO customers (company_name,contact_name,email,phone,address,city,country,tax_number,tax_office,notes,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute(array_values($data));
        $id = $db->lastInsertId();
        flash('success', 'Müşteri başarıyla eklendi.');
        header('Location: view.php?id=' . $id);
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Müşteriler</a> <span class="separator">/</span> Yeni Müşteri
</div>

<div class="row justify-content-center">
<div class="col-lg-10">
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-2"></i><?= sanitize($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span><i class="bi bi-person-plus-fill text-primary me-2"></i>Yeni Müşteri Ekle</span>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Firma Adı</label>
                <input type="text" name="company_name" class="form-control" value="<?= sanitize($_POST['company_name'] ?? '') ?>" placeholder="Şirket adı">
            </div>
            <div class="col-md-6">
                <label class="form-label">İletişim Kişisi</label>
                <input type="text" name="contact_name" class="form-control" value="<?= sanitize($_POST['contact_name'] ?? '') ?>" placeholder="Ad Soyad">
            </div>
            <div class="col-md-6">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="ornek@email.com">
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>" placeholder="+90 5xx xxx xx xx">
            </div>
            <div class="col-12">
                <label class="form-label">Adres</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Sokak, Mahalle..."><?= sanitize($_POST['address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Şehir</label>
                <input type="text" name="city" class="form-control" value="<?= sanitize($_POST['city'] ?? '') ?>" placeholder="İstanbul">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ülke</label>
                <input type="text" name="country" class="form-control" value="<?= sanitize($_POST['country'] ?? 'Türkiye') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vergi Numarası</label>
                <input type="text" name="tax_number" class="form-control" value="<?= sanitize($_POST['tax_number'] ?? '') ?>" placeholder="1234567890">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vergi Dairesi</label>
                <input type="text" name="tax_office" class="form-control" value="<?= sanitize($_POST['tax_office'] ?? '') ?>" placeholder="Vergi dairesi adı">
            </div>
            <div class="col-12">
                <label class="form-label">Notlar</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Müşteri hakkında özel notlar..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="index.php" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Müşteri Kaydet</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>

<?php include '../includes/footer.php'; ?>
