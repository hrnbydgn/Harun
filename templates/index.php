<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Teklif Şablonları';
requireLogin();
$db = getDB();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM quote_templates WHERE id=?")->execute([$id]);
    flash('success', 'Şablon silindi.');
    header('Location: index.php');
    exit;
}

if (isset($_GET['set_default']) && is_numeric($_GET['set_default'])) {
    $id = (int)$_GET['set_default'];
    $db->exec("UPDATE quote_templates SET is_default=0");
    $db->prepare("UPDATE quote_templates SET is_default=1 WHERE id=?")->execute([$id]);
    flash('success', 'Varsayılan şablon güncellendi.');
    header('Location: index.php');
    exit;
}

$templates = $db->query("SELECT t.*, (SELECT COUNT(*) FROM quotes q WHERE q.template_id = t.id) as usage_count FROM quote_templates t ORDER BY t.is_default DESC, t.name ASC")->fetchAll();

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span> Şablonlar
</div>

<div class="card">
    <div class="card-header">
        <span><i class="bi bi-layout-text-window-reverse text-primary me-2"></i>Teklif Şablonları</span>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Yeni Şablon</a>
    </div>
    <div class="card-body">
        <?php if ($templates): ?>
        <div class="row g-3">
            <?php foreach ($templates as $t): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100" style="border:2px solid <?= $t['is_default'] ? 'var(--primary)' : 'var(--border)' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 style="font-size:15px;font-weight:700;margin-bottom:4px"><?= sanitize($t['name']) ?></h5>
                                <?php if ($t['is_default']): ?>
                                    <span class="badge badge-primary">Varsayılan</span>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-secondary"><?= $t['usage_count'] ?> kullanım</span>
                        </div>
                        <?php if ($t['description']): ?>
                        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px"><?= sanitize($t['description']) ?></p>
                        <?php endif; ?>
                        <div class="row g-2 mb-3" style="font-size:12.5px">
                            <div class="col-6"><i class="bi bi-calendar me-1 text-muted"></i>Geçerlilik: <?= $t['validity_days'] ?> gün</div>
                            <div class="col-6"><i class="bi bi-percent me-1 text-muted"></i>İsk. Tipi: <?= $t['discount_type'] === 'percent' ? 'Yüzde' : 'Tutar' ?></div>
                            <div class="col-6"><i class="bi bi-bank me-1 text-muted"></i>Banka: <?= $t['show_bank_info'] ? 'Evet' : 'Hayır' ?></div>
                            <div class="col-6"><i class="bi bi-file-text me-1 text-muted"></i>Koşullar: <?= $t['show_terms'] ? 'Evet' : 'Hayır' ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary flex-1"><i class="bi bi-pencil me-1"></i>Düzenle</a>
                            <?php if (!$t['is_default']): ?>
                            <a href="index.php?set_default=<?= $t['id'] ?>" class="btn btn-sm btn-secondary" title="Varsayılan Yap"><i class="bi bi-star"></i></a>
                            <?php endif; ?>
                            <a href="index.php?delete=<?= $t['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete" title="Sil"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-layout-text-window"></i>
                <h5>Henüz şablon oluşturulmamış</h5>
                <p>Tekliflerinizde kullanmak için şablon oluşturun</p>
                <a href="add.php" class="btn btn-primary mt-3"><i class="bi bi-plus me-2"></i>İlk Şablonu Oluştur</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
