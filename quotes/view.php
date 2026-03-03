<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT q.*, c.company_name, c.contact_name, c.email as c_email, c.phone as c_phone, c.address as c_address, c.city as c_city, c.country as c_country, c.tax_number as c_tax_number, c.tax_office as c_tax_office FROM quotes q LEFT JOIN customers c ON q.customer_id = c.id WHERE q.id=?");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) { flash('error', 'Teklif bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = 'Teklif: ' . $q['quote_number'];

$items = $db->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order");
$items->execute([$id]);
$items = $items->fetchAll();

$activity = $db->prepare("SELECT qa.*, u.name as user_name FROM quote_activity qa LEFT JOIN users u ON qa.user_id = u.id WHERE qa.quote_id=? ORDER BY qa.created_at DESC LIMIT 10");
$activity->execute([$id]);
$activity = $activity->fetchAll();

// Status change
if (isset($_GET['set_status'])) {
    $ns = $_GET['set_status'];
    $valid = ['draft','sent','accepted','rejected','expired'];
    if (in_array($ns, $valid)) {
        $db->prepare("UPDATE quotes SET status=? WHERE id=?")->execute([$ns, $id]);
        $db->prepare("INSERT INTO quote_activity (quote_id,user_id,action,description) VALUES (?,?,?,?)")->execute([$id, $_SESSION['user_id'], 'status_changed', 'Durum değiştirildi: ' . getStatusText($ns)]);
        flash('success', 'Durum güncellendi.');
        header('Location: view.php?id=' . $id);
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Teklifler</a> <span class="separator">/</span> <?= sanitize($q['quote_number']) ?>
</div>

<!-- Action Bar -->
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
    <?= getStatusBadge($q['status']) ?>
    <div class="ms-auto d-flex gap-2 flex-wrap">
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Düzenle</a>
        <a href="pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="bi bi-printer me-1"></i>PDF / Yazdır</a>
        <div class="dropdown">
            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots me-1"></i>İşlemler</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-header">Durum Değiştir</li>
                <?php foreach (['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul Edildi','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'] as $st => $stl): ?>
                    <?php if ($st !== $q['status']): ?>
                        <li><a class="dropdown-item" href="view.php?id=<?= $id ?>&set_status=<?= $st ?>"><i class="bi bi-arrow-right-circle me-2"></i><?= $stl ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="index.php?duplicate=<?= $id ?>"><i class="bi bi-copy me-2"></i>Kopyala</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger confirm-delete" href="index.php?delete=<?= $id ?>"><i class="bi bi-trash me-2"></i>Sil</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Quote Preview Card -->
        <div class="card mb-3">
            <div style="background:linear-gradient(135deg,#0d1b2a,#1a3a5c);color:white;padding:24px 28px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
                <div>
                    <?php $logo = getSetting('company_logo'); if ($logo): ?>
                        <img src="<?= BASE_URL ?>/<?= sanitize($logo) ?>" style="max-height:50px;max-width:140px;object-fit:contain;background:rgba(255,255,255,.1);padding:4px;border-radius:6px;margin-bottom:12px">
                    <?php else: ?>
                        <div style="font-size:22px;font-weight:800;margin-bottom:8px"><?= sanitize(getSetting('company_name', 'HSG Aviation')) ?></div>
                    <?php endif; ?>
                    <?php $cAddr = getSetting('company_address'); if ($cAddr): ?><div style="font-size:12.5px;color:rgba(255,255,255,.65);margin-top:4px"><?= nl2br(sanitize($cAddr)) ?></div><?php endif; ?>
                    <?php $cEmail = getSetting('company_email'); if ($cEmail): ?><div style="font-size:12.5px;color:rgba(255,255,255,.65)"><?= sanitize($cEmail) ?></div><?php endif; ?>
                    <?php $cPhone = getSetting('company_phone'); if ($cPhone): ?><div style="font-size:12.5px;color:rgba(255,255,255,.65)"><?= sanitize($cPhone) ?></div><?php endif; ?>
                </div>
                <div style="text-align:right">
                    <div style="font-size:26px;font-weight:800;font-family:'Courier New',monospace"><?= sanitize($q['quote_number']) ?></div>
                    <div style="color:rgba(255,255,255,.6);font-size:12px;margin-top:4px">Teklif Tarihi: <?= formatDate($q['issue_date']) ?></div>
                    <?php if ($q['valid_until']): ?>
                    <div style="color:rgba(255,255,255,.6);font-size:12px">Geçerlilik: <?= formatDate($q['valid_until']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($q['title']): ?>
                <div style="font-size:17px;font-weight:700;margin-bottom:16px"><?= sanitize($q['title']) ?></div>
                <?php endif; ?>

                <?php if ($q['header_text']): ?>
                <div class="alert alert-info mb-3" style="font-size:13.5px"><?= nl2br(sanitize($q['header_text'])) ?></div>
                <?php endif; ?>

                <!-- Parties -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div style="background:var(--bg-body);border-radius:8px;padding:14px">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:8px">Teklif Veren</div>
                            <strong style="font-size:14px"><?= sanitize(getSetting('company_name', 'HSG Aviation')) ?></strong>
                            <?php $ct = getSetting('company_tax'); if ($ct): ?><div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px">Vergi No: <?= sanitize($ct) ?><?= getSetting('company_tax_office') ? ' / ' . sanitize(getSetting('company_tax_office')) : '' ?></div><?php endif; ?>
                            <?php $bname = getSetting('bank_name'); if ($bname): ?><div style="font-size:12px;color:var(--text-muted);margin-top:4px"><i class="bi bi-bank me-1"></i><?= sanitize($bname) ?> - IBAN: <?= sanitize(getSetting('bank_iban', '')) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:var(--bg-body);border-radius:8px;padding:14px">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:8px">Teklif Alıcı</div>
                            <?php if ($q['company_name'] || $q['contact_name']): ?>
                                <strong style="font-size:14px"><?= sanitize($q['company_name'] ?: $q['contact_name']) ?></strong>
                                <?php if ($q['company_name'] && $q['contact_name']): ?><div style="font-size:13px;color:var(--text-secondary)"><?= sanitize($q['contact_name']) ?></div><?php endif; ?>
                                <?php if ($q['c_email']): ?><div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px"><?= sanitize($q['c_email']) ?></div><?php endif; ?>
                                <?php if ($q['c_phone']): ?><div style="font-size:12.5px;color:var(--text-secondary)"><?= sanitize($q['c_phone']) ?></div><?php endif; ?>
                                <?php if ($q['c_city']): ?><div style="font-size:12.5px;color:var(--text-secondary)"><?= sanitize($q['c_city']) ?><?= $q['c_country'] ? ', ' . sanitize($q['c_country']) : '' ?></div><?php endif; ?>
                                <?php if ($q['c_tax_number']): ?><div style="font-size:12px;color:var(--text-muted)">Vergi: <?= sanitize($q['c_tax_number']) ?><?= $q['c_tax_office'] ? ' / ' . sanitize($q['c_tax_office']) : '' ?></div><?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">Müşteri belirtilmemiş</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-3">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#0d1b2a;color:white">
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px">#</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px">Ürün/Hizmet</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:center">Birim</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:right">Miktar</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:right">Birim Fiyat</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:right">İsk.%</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:right">KDV%</th>
                                <th style="padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;text-align:right">Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <tr style="<?= $i%2?'background:rgba(0,0,0,.02)':'' ?>">
                                <td style="padding:11px 14px;font-size:13px;color:var(--text-muted)"><?= $i+1 ?></td>
                                <td style="padding:11px 14px">
                                    <div style="font-weight:600;font-size:13.5px"><?= sanitize($item['name']) ?></div>
                                    <?php if ($item['code']): ?><div style="font-size:11.5px;color:var(--text-muted)">SKU: <?= sanitize($item['code']) ?></div><?php endif; ?>
                                    <?php if ($item['description']): ?><div style="font-size:12px;color:var(--text-secondary);margin-top:3px"><?= nl2br(sanitize($item['description'])) ?></div><?php endif; ?>
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-size:13px"><?= sanitize($item['unit']) ?></td>
                                <td style="padding:11px 14px;text-align:right;font-size:13px"><?= number_format($item['quantity'], $item['quantity'] == intval($item['quantity']) ? 0 : 2, ',', '.') ?></td>
                                <td style="padding:11px 14px;text-align:right;font-size:13px"><?= formatMoney($item['unit_price'], $q['currency']) ?></td>
                                <td style="padding:11px 14px;text-align:right;font-size:13px"><?= $item['discount_percent'] > 0 ? '%' . number_format($item['discount_percent'], 0) : '—' ?></td>
                                <td style="padding:11px 14px;text-align:right;font-size:13px">%<?= number_format($item['tax_rate'], 0) ?></td>
                                <td style="padding:11px 14px;text-align:right;font-weight:700;font-size:13.5px;color:var(--primary)"><?= formatMoney($item['total'], $q['currency']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="d-flex justify-content-end">
                    <div style="min-width:280px">
                        <div class="totals-print-row"><span>Ara Toplam</span><span><?= formatMoney($q['subtotal'], $q['currency']) ?></span></div>
                        <?php if ($q['discount_amount'] > 0): ?>
                        <div class="totals-print-row" style="color:var(--danger)">
                            <span>İskonto<?= $q['discount_type']==='percent' ? ' (%' . number_format($q['discount_value'],0) . ')' : '' ?></span>
                            <span>-<?= formatMoney($q['discount_amount'], $q['currency']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="totals-print-row"><span>KDV Toplam</span><span><?= formatMoney($q['tax_total'], $q['currency']) ?></span></div>
                        <div class="totals-print-row grand-total"><span>GENEL TOPLAM</span><span><?= formatMoney($q['total'], $q['currency']) ?></span></div>
                        <div style="font-size:12px;color:var(--text-muted);font-style:italic;margin-top:8px;text-align:right">
                            <?= numberToWords($q['total'], $q['currency']) ?></div>
                    </div>
                </div>

                <?php if ($q['notes']): ?>
                <div class="mt-4 p-3" style="background:rgba(0,102,204,.05);border-left:3px solid var(--primary);border-radius:0 8px 8px 0">
                    <div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--primary);margin-bottom:6px">Notlar</div>
                    <div style="font-size:13.5px"><?= nl2br(sanitize($q['notes'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($q['terms']): ?>
                <div class="mt-3 p-3" style="background:var(--bg-body);border-radius:8px">
                    <div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:6px">Şartlar & Koşullar</div>
                    <div style="font-size:13px;color:var(--text-secondary)"><?= nl2br(sanitize($q['terms'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($q['footer_text']): ?>
                <div class="mt-3 text-center" style="font-size:12.5px;color:var(--text-muted);border-top:1px solid var(--border);padding-top:14px">
                    <?= nl2br(sanitize($q['footer_text'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right sidebar -->
    <div class="col-lg-4">
        <!-- Summary -->
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-info-circle-fill text-primary me-2"></i>Teklif Bilgileri</span></div>
            <div class="card-body">
                <div class="mb-2"><span style="font-size:12px;color:var(--text-muted)">Teklif No:</span> <strong><?= sanitize($q['quote_number']) ?></strong></div>
                <div class="mb-2"><span style="font-size:12px;color:var(--text-muted)">Tarih:</span> <?= formatDate($q['issue_date']) ?></div>
                <div class="mb-2"><span style="font-size:12px;color:var(--text-muted)">Geçerlilik:</span> <?= formatDate($q['valid_until']) ?></div>
                <div class="mb-2"><span style="font-size:12px;color:var(--text-muted)">Para Birimi:</span> <?= sanitize($q['currency']) ?></div>
                <div class="mb-2"><span style="font-size:12px;color:var(--text-muted)">Toplam:</span> <strong style="color:var(--primary);font-size:16px"><?= formatMoney($q['total'], $q['currency']) ?></strong></div>
                <hr>
                <div style="font-size:12px;color:var(--text-muted)">Oluşturulma: <?= formatDate($q['created_at']) ?></div>
                <div style="font-size:12px;color:var(--text-muted)">Son Güncelleme: <?= formatDate($q['updated_at']) ?></div>
            </div>
        </div>

        <!-- Status Change -->
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-arrow-repeat text-primary me-2"></i>Durum Değiştir</span></div>
            <div class="card-body d-grid gap-2">
                <?php
                $statusBtns = ['draft'=>['Taslak','btn-secondary','bi-file-earmark'], 'sent'=>['Gönderildi','btn-info','bi-send'], 'accepted'=>['Kabul Edildi','btn-success','bi-check-circle'], 'rejected'=>['Reddedildi','btn-danger','bi-x-circle'], 'expired'=>['Süresi Doldu','btn-warning','bi-clock']];
                foreach ($statusBtns as $st => [$lbl, $cls, $icon]):
                    if ($st === $q['status']): ?>
                        <button class="btn <?= $cls ?> disabled" style="opacity:.7"><i class="bi <?= $icon ?> me-1"></i><?= $lbl ?> (Mevcut)</button>
                    <?php else: ?>
                        <a href="view.php?id=<?= $id ?>&set_status=<?= $st ?>" class="btn btn-secondary"><i class="bi <?= $icon ?> me-1"></i><?= $lbl ?></a>
                    <?php endif;
                endforeach; ?>
            </div>
        </div>

        <!-- Activity -->
        <?php if ($activity): ?>
        <div class="card">
            <div class="card-header"><span><i class="bi bi-activity text-primary me-2"></i>Aktiviteler</span></div>
            <div class="card-body p-0">
                <?php foreach ($activity as $act): ?>
                <div class="px-3 py-2 border-bottom">
                    <div style="font-size:13px"><?= sanitize($act['description']) ?></div>
                    <div style="font-size:11.5px;color:var(--text-muted)"><?= sanitize($act['user_name'] ?? 'Sistem') ?> &bull; <?= date('d.m.Y H:i', strtotime($act['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
