<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { flash('error', 'Müşteri bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = $c['company_name'] ?: $c['contact_name'];

$quotes = $db->prepare("SELECT * FROM quotes WHERE customer_id = ? ORDER BY created_at DESC");
$quotes->execute([$id]);
$quotes = $quotes->fetchAll();

$totalQuoted = array_sum(array_column($quotes, 'total'));
$accepted = array_filter($quotes, fn($q) => $q['status'] === 'accepted');
$acceptedTotal = array_sum(array_column($accepted, 'total'));

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Müşteriler</a> <span class="separator">/</span> <?= sanitize($pageTitle) ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm" style="width:48px;height:48px;font-size:18px"><?= strtoupper(substr($c['company_name'] ?: $c['contact_name'], 0, 1)) ?></div>
                    <div>
                        <div style="font-size:18px;font-weight:700"><?= sanitize($c['company_name'] ?: $c['contact_name']) ?></div>
                        <?php if ($c['company_name'] && $c['contact_name']): ?><div style="color:var(--text-muted);font-size:13px"><?= sanitize($c['contact_name']) ?></div><?php endif; ?>
                    </div>
                    <span class="badge <?= $c['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?> ms-auto"><?= $c['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Düzenle</a>
                    <a href="../quotes/create.php?customer_id=<?= $id ?>" class="btn btn-sm btn-accent" style="background:var(--accent);color:white"><i class="bi bi-plus me-1"></i>Teklif Oluştur</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if ($c['email']): ?>
                    <div class="col-md-6">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">E-posta</div>
                        <a href="mailto:<?= sanitize($c['email']) ?>" style="color:var(--primary)"><?= sanitize($c['email']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['phone']): ?>
                    <div class="col-md-6">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Telefon</div>
                        <a href="tel:<?= sanitize($c['phone']) ?>"><?= sanitize($c['phone']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['address'] || $c['city']): ?>
                    <div class="col-md-6">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Adres</div>
                        <div><?= sanitize($c['address']) ?><?= $c['city'] ? ', ' . sanitize($c['city']) : '' ?><?= $c['country'] ? ', ' . sanitize($c['country']) : '' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['tax_number']): ?>
                    <div class="col-md-6">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Vergi Bilgisi</div>
                        <div><?= sanitize($c['tax_number']) ?><?= $c['tax_office'] ? ' / ' . sanitize($c['tax_office']) : '' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['notes']): ?>
                    <div class="col-12">
                        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Notlar</div>
                        <div style="background:var(--bg-body);padding:12px;border-radius:8px;font-size:13.5px"><?= nl2br(sanitize($c['notes'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="stat-card p-0 border-0 shadow-none mb-3">
                    <div class="stat-icon blue"><i class="bi bi-file-earmark-text-fill"></i></div>
                    <div class="stat-info"><div class="stat-value"><?= count($quotes) ?></div><div class="stat-label">Toplam Teklif</div></div>
                </div>
                <div class="stat-card p-0 border-0 shadow-none">
                    <div class="stat-icon green"><i class="bi bi-currency-exchange"></i></div>
                    <div class="stat-info"><div class="stat-value" style="font-size:16px"><?= formatMoney($acceptedTotal) ?></div><div class="stat-label">Kabul Edilen Toplam</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quotes -->
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-file-earmark-text-fill text-primary me-2"></i>Teklifler</span>
        <a href="../quotes/create.php?customer_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Yeni Teklif</a>
    </div>
    <div class="card-body p-0">
        <?php if ($quotes): ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>Teklif No</th><th>Başlık</th><th>Tarih</th><th>Geçerlilik</th><th>Tutar</th><th>Durum</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td><span class="text-mono" style="color:var(--primary);font-weight:600"><?= sanitize($q['quote_number']) ?></span></td>
                        <td><?= sanitize($q['title'] ?? '—') ?></td>
                        <td><?= formatDate($q['issue_date']) ?></td>
                        <td><?= formatDate($q['valid_until']) ?></td>
                        <td style="font-weight:600"><?= formatMoney($q['total'], $q['currency']) ?></td>
                        <td><?= getStatusBadge($q['status']) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="../quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary btn-icon"><i class="bi bi-eye"></i></a>
                                <a href="../quotes/edit.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-file-earmark"></i><h5>Teklif bulunamadı</h5><p>Bu müşteri için henüz teklif oluşturulmamış</p></div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
