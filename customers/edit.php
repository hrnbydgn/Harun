<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = $db->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$id]);
$customer = $customer->fetch();
if (!$customer) { flash('error', 'Müşteri bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = 'Müşteri Düzenle: ' . ($customer['company_name'] ?: $customer['contact_name']);
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
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE customers SET company_name=?,contact_name=?,email=?,phone=?,address=?,city=?,country=?,tax_number=?,tax_office=?,notes=?,status=? WHERE id=?");
        $stmt->execute([...array_values($data), $id]);
        flash('success', 'Müşteri güncellendi.');
        header('Location: view.php?id=' . $id);
        exit;
    }
    $customer = array_merge($customer, $data);
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Müşteriler</a> <span class="separator">/</span> Düzenle
</div>
<div class="row justify-content-center">
<div class="col-lg-10">
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-pencil-fill text-primary me-2"></i>Müşteri Düzenle</span>
        <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Firma Adı</label>
                <input type="text" name="company_name" class="form-control" value="<?= sanitize($customer['company_name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">İletişim Kişisi</label>
                <input type="text" name="contact_name" class="form-control" value="<?= sanitize($customer['contact_name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($customer['email']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" value="<?= sanitize($customer['phone']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Adres</label>
                <textarea name="address" class="form-control" rows="2"><?= sanitize($customer['address']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Şehir</label>
                <input type="text" name="city" class="form-control" value="<?= sanitize($customer['city']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ülke</label>
                <input type="text" name="country" class="form-control" value="<?= sanitize($customer['country'] ?: 'Türkiye') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $customer['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $customer['status'] === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vergi Numarası</label>
                <input type="text" name="tax_number" class="form-control" value="<?= sanitize($customer['tax_number']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vergi Dairesi</label>
                <input type="text" name="tax_office" class="form-control" value="<?= sanitize($customer['tax_office']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notlar</label>
                <textarea name="notes" class="form-control" rows="3"><?= sanitize($customer['notes']) ?></textarea>
            </div>
        </div>
        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Güncelle</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
