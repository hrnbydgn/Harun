<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Yeni Ürün/Hizmet';
requireLogin();
$db = getDB();

$categories = $db->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_id' => $_POST['category_id'] ?: null,
        'code'        => trim($_POST['code'] ?? ''),
        'name'        => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'unit'        => trim($_POST['unit'] ?? 'Adet'),
        'price'       => (float)str_replace(',', '.', $_POST['price'] ?? 0),
        'currency'    => $_POST['currency'] ?? 'TRY',
        'tax_rate'    => (float)str_replace(',', '.', $_POST['tax_rate'] ?? 20),
        'status'      => $_POST['status'] ?? 'active',
    ];
    if (empty($data['name'])) $errors[] = 'Ürün adı zorunludur.';
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO products (category_id,code,name,description,unit,price,currency,tax_rate,status) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute(array_values($data));
        flash('success', 'Ürün başarıyla eklendi.');
        header('Location: index.php');
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Ürünler</a> <span class="separator">/</span> Yeni Ürün/Hizmet
</div>
<div class="row justify-content-center">
<div class="col-lg-8">
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-plus-circle-fill text-primary me-2"></i>Yeni Ürün/Hizmet Ekle</span>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Ürün/Hizmet Adı <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required placeholder="Ürün veya hizmet adı">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ürün Kodu</label>
                <input type="text" name="code" class="form-control" value="<?= sanitize($_POST['code'] ?? '') ?>" placeholder="SKU-001">
            </div>
            <div class="col-md-6">
                <label class="form-label">Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">Kategori Seçin</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Birim</label>
                <select name="unit" class="form-select">
                    <?php foreach (['Adet', 'Kg', 'Lt', 'M', 'M²', 'M³', 'Paket', 'Kutu', 'Saat', 'Gün', 'Ay', 'Yıl', 'Set', 'Takım', 'Hizmet'] as $u): ?>
                        <option value="<?= $u ?>" <?= ($_POST['unit'] ?? 'Adet') === $u ? 'selected' : '' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Birim Fiyat</label>
                <div class="input-group">
                    <select name="currency" class="form-select" style="max-width:90px">
                        <option value="TRY" <?= ($_POST['currency'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>₺</option>
                        <option value="USD" <?= ($_POST['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>$</option>
                        <option value="EUR" <?= ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>€</option>
                        <option value="GBP" <?= ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>£</option>
                    </select>
                    <input type="number" name="price" step="0.01" min="0" class="form-control" value="<?= $_POST['price'] ?? '0.00' ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">KDV Oranı (%)</label>
                <select name="tax_rate" class="form-select">
                    <?php foreach ([0, 1, 8, 10, 18, 20] as $t): ?>
                        <option value="<?= $t ?>" <?= ($_POST['tax_rate'] ?? 20) == $t ? 'selected' : '' ?>>%<?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="active">Aktif</option>
                    <option value="inactive">Pasif</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Açıklama</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Ürün veya hizmet hakkında detaylı açıklama..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="index.php" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Ürünü Kaydet</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
