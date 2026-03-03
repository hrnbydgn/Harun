<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Müşteriler';

requireLogin();
$db = getDB();

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hasQuotes = $db->prepare("SELECT COUNT(*) FROM quotes WHERE customer_id = ?");
    $hasQuotes->execute([$id]);
    if ($hasQuotes->fetchColumn() > 0) {
        flash('warning', 'Bu müşteriye ait teklifler bulunmaktadır. Önce teklifleri silin veya müşteriyi pasif yapın.');
    } else {
        $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
        flash('success', 'Müşteri silindi.');
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(company_name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status) { $where[] = "status = ?"; $params[] = $status; }

$sql = "SELECT c.*, (SELECT COUNT(*) FROM quotes q WHERE q.customer_id = c.id) as quote_count FROM customers c WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalActive = $db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn();
$totalInactive = $db->query("SELECT COUNT(*) FROM customers WHERE status='inactive'")->fetchColumn();

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span> Müşteriler
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $totalActive ?></div><div class="stat-label">Aktif Müşteri</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-person-dash-fill"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $totalInactive ?></div><div class="stat-label">Pasif Müşteri</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="bi bi-people-fill text-primary me-2"></i>Müşteri Listesi</span>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Yeni Müşteri</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="İsim, e-posta, telefon ara..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-1">Ara</button>
                <a href="index.php" class="btn btn-secondary">Sıfırla</a>
            </div>
        </form>

        <?php if ($customers): ?>
        <div class="table-responsive">
            <table class="table datatable">
                <thead>
                    <tr>
                        <th>Firma / Müşteri</th>
                        <th>İletişim</th>
                        <th>Şehir</th>
                        <th>Vergi No</th>
                        <th>Teklif Sayısı</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm"><?= strtoupper(substr($c['company_name'] ?: $c['contact_name'], 0, 1)) ?></div>
                                <div>
                                    <div style="font-weight:600"><?= sanitize($c['company_name'] ?: $c['contact_name']) ?></div>
                                    <?php if ($c['company_name'] && $c['contact_name']): ?>
                                        <div style="font-size:12px;color:var(--text-muted)"><?= sanitize($c['contact_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($c['email']): ?><div style="font-size:13px"><i class="bi bi-envelope text-muted me-1"></i><?= sanitize($c['email']) ?></div><?php endif; ?>
                            <?php if ($c['phone']): ?><div style="font-size:13px"><i class="bi bi-phone text-muted me-1"></i><?= sanitize($c['phone']) ?></div><?php endif; ?>
                        </td>
                        <td><?= sanitize($c['city'] ?? '—') ?></td>
                        <td><?= sanitize($c['tax_number'] ?? '—') ?></td>
                        <td>
                            <a href="../quotes/index.php?customer_id=<?= $c['id'] ?>" class="badge badge-primary" style="text-decoration:none">
                                <?= $c['quote_count'] ?> teklif
                            </a>
                        </td>
                        <td>
                            <span class="badge <?= $c['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $c['status'] === 'active' ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Görüntüle"><i class="bi bi-eye"></i></a>
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary btn-icon" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                <a href="index.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete" title="Sil"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>Müşteri bulunamadı</h5>
                <p>Henüz müşteri eklenmemiş veya arama kriterlerine uygun müşteri yok</p>
                <a href="add.php" class="btn btn-primary mt-3"><i class="bi bi-plus me-2"></i>İlk Müşteriyi Ekle</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
