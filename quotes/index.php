<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Teklifler';
requireLogin();
$db = getDB();

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM quotes WHERE id=?")->execute([$id]);
    flash('success', 'Teklif silindi.');
    header('Location: index.php');
    exit;
}

// Status change
if (isset($_GET['status_change']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['status_change'];
    $valid = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
    if (in_array($newStatus, $valid)) {
        $db->prepare("UPDATE quotes SET status=? WHERE id=?")->execute([$newStatus, $id]);
        flash('success', 'Teklif durumu güncellendi.');
    }
    header('Location: index.php');
    exit;
}

// Duplicate
if (isset($_GET['duplicate']) && is_numeric($_GET['duplicate'])) {
    $id = (int)$_GET['duplicate'];
    $orig = $db->prepare("SELECT * FROM quotes WHERE id=?"); $orig->execute([$id]); $orig = $orig->fetch();
    if ($orig) {
        $newNum = generateQuoteNumber();
        $stmt = $db->prepare("INSERT INTO quotes (quote_number,customer_id,template_id,title,status,issue_date,valid_until,currency,subtotal,discount_type,discount_value,discount_amount,tax_total,total,notes,terms,footer_text,header_text,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$newNum,$orig['customer_id'],$orig['template_id'],$orig['title'].' (Kopya)','draft',date('Y-m-d'),date('Y-m-d', strtotime('+30 days')),$orig['currency'],$orig['subtotal'],$orig['discount_type'],$orig['discount_value'],$orig['discount_amount'],$orig['tax_total'],$orig['total'],$orig['notes'],$orig['terms'],$orig['footer_text'],$orig['header_text'],$_SESSION['user_id']]);
        $newId = $db->lastInsertId();
        $items = $db->prepare("SELECT * FROM quote_items WHERE quote_id=?"); $items->execute([$id]);
        foreach ($items->fetchAll() as $item) {
            $stmt2 = $db->prepare("INSERT INTO quote_items (quote_id,product_id,code,name,description,unit,quantity,unit_price,tax_rate,discount_percent,subtotal,tax_amount,total,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt2->execute([$newId,$item['product_id'],$item['code'],$item['name'],$item['description'],$item['unit'],$item['quantity'],$item['unit_price'],$item['tax_rate'],$item['discount_percent'],$item['subtotal'],$item['tax_amount'],$item['total'],$item['sort_order']]);
        }
        flash('success', 'Teklif kopyalandı.');
        header('Location: edit.php?id=' . $newId);
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(q.quote_number LIKE ? OR q.title LIKE ? OR c.company_name LIKE ? OR c.contact_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status) { $where[] = "q.status = ?"; $params[] = $status; }
if ($customerId) { $where[] = "q.customer_id = ?"; $params[] = $customerId; }
if ($dateFrom) { $where[] = "q.issue_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "q.issue_date <= ?"; $params[] = $dateTo; }

$sql = "SELECT q.*, c.company_name, c.contact_name FROM quotes q LEFT JOIN customers c ON q.customer_id = c.id WHERE " . implode(' AND ', $where) . " ORDER BY q.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();

$statusCounts = [];
foreach (['draft','sent','accepted','rejected','expired'] as $s) {
    $st = $db->query("SELECT COUNT(*) FROM quotes WHERE status='$s'")->fetchColumn();
    $statusCounts[$s] = $st;
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span> Teklifler
</div>

<!-- Status filter tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="index.php" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-secondary' ?>">
        Tümü <span class="badge <?= !$status ? 'bg-white text-primary' : 'badge-secondary' ?> ms-1"><?= array_sum($statusCounts) ?></span>
    </a>
    <?php
    $labels = ['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'];
    foreach ($labels as $st => $lbl): ?>
    <a href="index.php?status=<?= $st ?>" class="btn btn-sm <?= $status === $st ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $lbl ?> <span class="badge <?= $status === $st ? 'bg-white text-primary' : 'badge-secondary' ?> ms-1"><?= $statusCounts[$st] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="bi bi-file-earmark-text-fill text-primary me-2"></i>Teklif Listesi</span>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Yeni Teklif</a>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= sanitize($status) ?>"><?php endif; ?>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Teklif no, müşteri, başlık..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>" placeholder="Başlangıç">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>" placeholder="Bitiş">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-1">Ara</button>
                <a href="index.php" class="btn btn-secondary">Sıfırla</a>
                <a href="create.php" class="btn btn-accent d-none d-md-flex" style="background:var(--accent);color:white"><i class="bi bi-plus"></i></a>
            </div>
        </form>

        <?php if ($quotes): ?>
        <div class="table-responsive">
            <table class="table datatable">
                <thead>
                    <tr>
                        <th>Teklif No</th>
                        <th>Müşteri</th>
                        <th>Başlık</th>
                        <th>Tarih</th>
                        <th>Geçerlilik</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quotes as $q):
                    $isExpired = $q['status'] === 'sent' && $q['valid_until'] && strtotime($q['valid_until']) < time();
                    $soonExpiring = $q['status'] === 'sent' && $q['valid_until'] && strtotime($q['valid_until']) <= strtotime('+7 days') && strtotime($q['valid_until']) >= time();
                ?>
                    <tr>
                        <td>
                            <a href="view.php?id=<?= $q['id'] ?>" class="text-mono fw-600" style="color:var(--primary);text-decoration:none">
                                <?= sanitize($q['quote_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($q['company_name']): ?>
                                <div style="font-weight:500"><?= sanitize($q['company_name']) ?></div>
                                <?php if ($q['contact_name']): ?><div style="font-size:12px;color:var(--text-muted)"><?= sanitize($q['contact_name']) ?></div><?php endif; ?>
                            <?php elseif ($q['contact_name']): ?>
                                <div style="font-weight:500"><?= sanitize($q['contact_name']) ?></div>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:200px">
                            <div class="text-truncate"><?= sanitize($q['title'] ?: '—') ?></div>
                        </td>
                        <td style="white-space:nowrap"><?= formatDate($q['issue_date']) ?></td>
                        <td style="white-space:nowrap">
                            <?= formatDate($q['valid_until']) ?>
                            <?php if ($isExpired): ?>
                                <span class="badge badge-danger ms-1">Geçmiş</span>
                            <?php elseif ($soonExpiring): ?>
                                <span class="badge badge-warning ms-1">!</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;white-space:nowrap;color:var(--primary)"><?= formatMoney($q['total'], $q['currency']) ?></td>
                        <td><?= getStatusBadge($q['status']) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Görüntüle"><i class="bi bi-eye"></i></a>
                                <a href="edit.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-primary btn-icon" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-secondary btn-icon dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="pdf.php?id=<?= $q['id'] ?>" target="_blank"><i class="bi bi-printer me-2"></i>PDF / Yazdır</a></li>
                                        <li><a class="dropdown-item" href="index.php?duplicate=<?= $q['id'] ?>"><i class="bi bi-copy me-2"></i>Kopyala</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li class="dropdown-header">Durum Değiştir</li>
                                        <?php foreach (['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'] as $st => $stl): ?>
                                            <?php if ($st !== $q['status']): ?>
                                                <li><a class="dropdown-item" href="index.php?status_change=<?= $st ?>&id=<?= $q['id'] ?>"><?= $stl ?></a></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger confirm-delete" href="index.php?delete=<?= $q['id'] ?>"><i class="bi bi-trash me-2"></i>Sil</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <h5>Teklif bulunamadı</h5>
                <p>Henüz teklif oluşturulmamış veya arama kriterlerine uygun teklif yok</p>
                <a href="create.php" class="btn btn-primary mt-3"><i class="bi bi-plus me-2"></i>İlk Teklifi Oluştur</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
