<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { flash('error', 'Ürün bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = 'Ürün Düzenle';
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
        $stmt = $db->prepare("UPDATE products SET category_id=?,code=?,name=?,description=?,unit=?,price=?,currency=?,tax_rate=?,status=? WHERE id=?");
        $stmt->execute([...array_values($data), $id]);
        flash('success', 'Ürün güncellendi.');
        header('Location: index.php');
        exit;
    }
    $product = array_merge($product, $data);
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Ürünler</a> <span class="separator">/</span> Düzenle
</div>
<div class="row justify-content-center">
<div class="col-lg-8">
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-pencil-fill text-primary me-2"></i>Ürün Düzenle</span>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Ürün/Hizmet Adı <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($product['name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Ürün Kodu</label>
                <input type="text" name="code" class="form-control" value="<?= sanitize($product['code']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">Kategori Seçin</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Birim</label>
                <select name="unit" class="form-select">
                    <?php foreach (['Adet', 'Kg', 'Lt', 'M', 'M²', 'M³', 'Paket', 'Kutu', 'Saat', 'Gün', 'Ay', 'Yıl', 'Set', 'Takım', 'Hizmet'] as $u): ?>
                        <option value="<?= $u ?>" <?= $product['unit'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Birim Fiyat</label>
                <div class="input-group">
                    <select name="currency" class="form-select" style="max-width:90px">
                        <option value="TRY" <?= $product['currency'] === 'TRY' ? 'selected' : '' ?>>₺</option>
                        <option value="USD" <?= $product['currency'] === 'USD' ? 'selected' : '' ?>>$</option>
                        <option value="EUR" <?= $product['currency'] === 'EUR' ? 'selected' : '' ?>>€</option>
                        <option value="GBP" <?= $product['currency'] === 'GBP' ? 'selected' : '' ?>>£</option>
                    </select>
                    <input type="number" name="price" step="0.01" min="0" class="form-control" value="<?= $product['price'] ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">KDV Oranı (%)</label>
                <select name="tax_rate" class="form-select">
                    <?php foreach ([0, 1, 8, 10, 18, 20] as $t): ?>
                        <option value="<?= $t ?>" <?= $product['tax_rate'] == $t ? 'selected' : '' ?>>%<?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Açıklama</label>
                <textarea name="description" class="form-control" rows="3"><?= sanitize($product['description']) ?></textarea>
            </div>
        </div>
        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="index.php" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Güncelle</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
