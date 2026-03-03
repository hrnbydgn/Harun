<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Ürün & Hizmetler';
requireLogin();
$db = getDB();

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    flash('success', 'Ürün silindi.');
    header('Location: index.php');
    exit;
}

// Delete category
if (isset($_GET['delcat']) && is_numeric($_GET['delcat'])) {
    $id = (int)$_GET['delcat'];
    $db->prepare("UPDATE products SET category_id=NULL WHERE category_id=?")->execute([$id]);
    $db->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]);
    flash('success', 'Kategori silindi.');
    header('Location: index.php');
    exit;
}

// Add category
if (isset($_POST['add_category'])) {
    $catName = trim($_POST['cat_name'] ?? '');
    if ($catName) {
        $db->prepare("INSERT INTO product_categories (name) VALUES (?)")->execute([$catName]);
        flash('success', 'Kategori eklendi.');
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$catFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$statusFilter = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($catFilter) { $where[] = "p.category_id = ?"; $params[] = $catFilter; }
if ($statusFilter) { $where[] = "p.status = ?"; $params[] = $statusFilter; }

$products = $db->prepare("SELECT p.*, pc.name as cat_name FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id WHERE " . implode(' AND ', $where) . " ORDER BY p.name ASC");
$products->execute($params);
$products = $products->fetchAll();

$categories = $db->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span> Ürün & Hizmetler
</div>

<div class="row g-3">
    <!-- Sidebar: Categories -->
    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-tag-fill text-primary me-2"></i>Kategoriler</span></div>
            <div class="card-body p-0">
                <a href="index.php" class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom <?= !$catFilter ? 'bg-primary text-white' : '' ?>" style="text-decoration:none;color:inherit">
                    <span>Tüm Kategoriler</span>
                    <span class="badge <?= !$catFilter ? 'bg-white text-primary' : 'badge-secondary' ?>"><?= count($products) ?></span>
                </a>
                <?php foreach ($categories as $cat):
                    $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?"); $cnt->execute([$cat['id']]); $cnt = $cnt->fetchColumn();
                ?>
                <div class="d-flex align-items-center px-3 py-2 border-bottom <?= $catFilter == $cat['id'] ? 'bg-primary' : '' ?>" style="gap:6px">
                    <a href="index.php?cat=<?= $cat['id'] ?>" class="flex-1 text-decoration-none <?= $catFilter == $cat['id'] ? 'text-white' : '' ?>" style="color:inherit">
                        <?= sanitize($cat['name']) ?>
                    </a>
                    <span class="badge <?= $catFilter == $cat['id'] ? 'bg-white text-primary' : 'badge-secondary' ?>"><?= $cnt ?></span>
                    <a href="index.php?delcat=<?= $cat['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete" style="padding:2px 5px;font-size:11px"><i class="bi bi-x"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer">
                <form method="post" class="d-flex gap-2">
                    <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="Yeni kategori...">
                    <button type="submit" name="add_category" class="btn btn-sm btn-primary px-2"><i class="bi bi-plus"></i></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main: Products -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-box-seam-fill text-primary me-2"></i>Ürün Listesi (<?= count($products) ?>)</span>
                <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Yeni Ürün/Hizmet</a>
            </div>
            <div class="card-body">
                <form class="row g-2 mb-3" method="get">
                    <input type="hidden" name="cat" value="<?= $catFilter ?>">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ürün adı, kodu ara..." value="<?= sanitize($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">Tüm Durumlar</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-1">Ara</button>
                        <a href="index.php" class="btn btn-secondary">Sıfırla</a>
                    </div>
                </form>
                <?php if ($products): ?>
                <div class="table-responsive">
                    <table class="table datatable">
                        <thead>
                            <tr><th>Kod</th><th>Ürün/Hizmet Adı</th><th>Kategori</th><th>Birim</th><th>Fiyat</th><th>KDV%</th><th>Durum</th><th>İşlemler</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><span class="text-mono"><?= sanitize($p['code'] ?: '—') ?></span></td>
                                <td>
                                    <div style="font-weight:600"><?= sanitize($p['name']) ?></div>
                                    <?php if ($p['description']): ?><div style="font-size:12px;color:var(--text-muted)"><?= sanitize(substr($p['description'],0,60)) ?>...</div><?php endif; ?>
                                </td>
                                <td><?= sanitize($p['cat_name'] ?? '—') ?></td>
                                <td><?= sanitize($p['unit']) ?></td>
                                <td style="font-weight:600;color:var(--primary)"><?= formatMoney($p['price'], $p['currency']) ?></td>
                                <td>%<?= number_format($p['tax_rate'], 0) ?></td>
                                <td><span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= $p['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="bi bi-pencil"></i></a>
                                        <a href="index.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-box-seam"></i>
                        <h5>Ürün bulunamadı</h5>
                        <p>Henüz ürün/hizmet eklenmemiş</p>
                        <a href="add.php" class="btn btn-primary mt-3"><i class="bi bi-plus me-2"></i>İlk Ürünü Ekle</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
