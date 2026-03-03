<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');

startSession();
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT q.*, c.company_name, c.contact_name, c.email as c_email, c.phone as c_phone, c.address as c_address, c.city as c_city, c.country as c_country, c.tax_number as c_tax_number, c.tax_office as c_tax_office FROM quotes q LEFT JOIN customers c ON q.customer_id = c.id WHERE q.id=?");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) die('Teklif bulunamadı.');

$items = $db->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order");
$items->execute([$id]);
$items = $items->fetchAll();

$companyName    = getSetting('company_name', 'HSG Aviation');
$companyAddress = getSetting('company_address', '');
$companyPhone   = getSetting('company_phone', '');
$companyEmail   = getSetting('company_email', '');
$companyWeb     = getSetting('company_website', '');
$companyTax     = getSetting('company_tax', '');
$companyTaxOff  = getSetting('company_tax_office', '');
$companyLogo    = getSetting('company_logo', '');
$bankName       = getSetting('bank_name', '');
$bankIban       = getSetting('bank_iban', '');
$bankBranch     = getSetting('bank_branch', '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teklif <?= sanitize($q['quote_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #e8ecf0; padding: 20px; color: #1a2a3a; font-size: 13px; line-height: 1.6; }
        
        .actions-bar {
            max-width: 900px; margin: 0 auto 16px;
            display: flex; gap: 10px; align-items: center;
        }
        .btn-action { padding: 9px 18px; border: none; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; display: flex; align-items: center; gap: 7px; text-decoration: none; }
        .btn-print { background: #0066cc; color: white; }
        .btn-back { background: #e5eaf2; color: #5a6a7a; }
        .btn-action:hover { opacity: .9; }

        .quote-wrap {
            max-width: 900px; margin: 0 auto;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,.15);
            border-radius: 12px;
            overflow: hidden;
        }

        .q-header {
            background: linear-gradient(135deg, #0d1b2a 0%, #1a3a5c 100%);
            color: white;
            padding: 28px 36px;
        }
        .q-header-inner { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .q-logo { max-height: 60px; max-width: 160px; object-fit: contain; }
        .q-company-name { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .q-company-info { font-size: 12px; color: rgba(255,255,255,.65); line-height: 1.8; }
        .q-number-wrap { text-align: right; }
        .q-number { font-size: 26px; font-weight: 800; font-family: 'Courier New', monospace; letter-spacing: 1px; }
        .q-label { font-size: 11px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .q-date { font-size: 12.5px; color: rgba(255,255,255,.7); margin-top: 6px; }
        .q-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; margin-top: 8px; background: rgba(255,255,255,.15); }

        .q-body { padding: 28px 36px; }

        .q-title { font-size: 18px; font-weight: 700; color: #0d1b2a; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 2px solid #e8ecf0; }

        .q-header-text { background: #eff6ff; border-left: 4px solid #0066cc; padding: 12px 16px; border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 13.5px; color: #1e3a8a; }

        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .party { background: #f8fafc; border-radius: 8px; padding: 14px 16px; }
        .party-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #8a9ab0; margin-bottom: 8px; }
        .party-name { font-size: 15px; font-weight: 700; color: #0d1b2a; margin-bottom: 4px; }
        .party-info { font-size: 12.5px; color: #5a6a7a; line-height: 1.8; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table thead tr { background: #0d1b2a; color: white; }
        .items-table th { padding: 11px 13px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .items-table th:last-child, .items-table td:last-child { text-align: right; }
        .items-table td { padding: 12px 13px; border-bottom: 1px solid #e8ecf0; font-size: 13px; vertical-align: top; }
        .items-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .item-name { font-weight: 600; color: #0d1b2a; }
        .item-code { font-size: 11.5px; color: #8a9ab0; font-family: monospace; }
        .item-desc { font-size: 12px; color: #5a6a7a; margin-top: 3px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 300px; }
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13.5px; border-bottom: 1px solid #f0f4f8; }
        .total-row:last-child { border-bottom: none; }
        .grand-total { font-size: 17px; font-weight: 800; color: #0066cc; padding-top: 10px; margin-top: 4px; border-top: 2px solid #0066cc !important; }
        .total-words { font-size: 11.5px; color: #8a9ab0; font-style: italic; text-align: right; margin-top: 6px; }
        .total-discount { color: #ef4444; }

        .notes-box { background: rgba(0,102,204,.05); border-left: 4px solid #0066cc; padding: 14px 16px; border-radius: 0 8px 8px 0; margin-bottom: 16px; }
        .notes-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #0066cc; margin-bottom: 6px; }
        .notes-text { font-size: 13px; color: #1a2a3a; }

        .terms-box { background: #f8fafc; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
        .terms-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #8a9ab0; margin-bottom: 6px; }
        .terms-text { font-size: 12.5px; color: #5a6a7a; }

        .bank-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
        .bank-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #0369a1; margin-bottom: 8px; }
        .bank-info { font-size: 13px; color: #0c4a6e; }

        .q-footer {
            background: #f8fafc; border-top: 1px solid #e8ecf0;
            padding: 16px 36px;
            text-align: center;
            font-size: 12px; color: #8a9ab0;
        }

        .sig-area { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; margin-top: 28px; }
        .sig-box { border-top: 2px solid #e8ecf0; padding-top: 10px; text-align: center; }
        .sig-label { font-size: 12px; color: #8a9ab0; font-weight: 600; }
        .sig-space { height: 50px; }

        @media print {
            body { background: white; padding: 0; }
            .actions-bar { display: none !important; }
            .quote-wrap { box-shadow: none; border-radius: 0; }
            @page { margin: 10mm; }
        }

        @media (max-width: 640px) {
            .q-header-inner { flex-direction: column; }
            .q-number-wrap { text-align: left; }
            .parties { grid-template-columns: 1fr; }
            .q-body { padding: 20px 16px; }
            .q-header { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<div class="actions-bar no-print">
    <a href="../quotes/view.php?id=<?= $id ?>" class="btn-action btn-back">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
        Geri Dön
    </a>
    <button onclick="window.print()" class="btn-action btn-print">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/></svg>
        Yazdır / PDF Kaydet
    </button>
    <span style="font-size:12.5px;color:#8a9ab0">Tarayıcı yazdır penceresinde "Hedef: PDF olarak kaydet" seçeneğini kullanabilirsiniz.</span>
</div>

<div class="quote-wrap">
    <div class="q-header">
        <div class="q-header-inner">
            <div>
                <?php if ($companyLogo): ?>
                    <img src="<?= BASE_URL ?>/<?= sanitize($companyLogo) ?>" class="q-logo" style="margin-bottom:12px">
                <?php else: ?>
                    <div class="q-company-name"><?= sanitize($companyName) ?></div>
                <?php endif; ?>
                <div class="q-company-info">
                    <?php if ($companyAddress): ?><?= nl2br(sanitize($companyAddress)) ?><br><?php endif; ?>
                    <?php if ($companyPhone): ?><?= sanitize($companyPhone) ?><br><?php endif; ?>
                    <?php if ($companyEmail): ?><?= sanitize($companyEmail) ?><br><?php endif; ?>
                    <?php if ($companyWeb): ?><?= sanitize($companyWeb) ?><?php endif; ?>
                    <?php if ($companyTax): ?><br>Vergi No: <?= sanitize($companyTax) ?><?= $companyTaxOff ? ' / ' . sanitize($companyTaxOff) : '' ?><?php endif; ?>
                </div>
            </div>
            <div class="q-number-wrap">
                <div class="q-label">Teklif</div>
                <div class="q-number"><?= sanitize($q['quote_number']) ?></div>
                <div class="q-date">Tarih: <?= formatDate($q['issue_date']) ?></div>
                <?php if ($q['valid_until']): ?><div class="q-date">Geçerlilik: <?= formatDate($q['valid_until']) ?></div><?php endif; ?>
                <?php
                $statusColors = ['draft'=>'rgba(255,255,255,.15)','sent'=>'rgba(59,130,246,.5)','accepted'=>'rgba(16,185,129,.5)','rejected'=>'rgba(239,68,68,.5)','expired'=>'rgba(245,158,11,.5)'];
                $statusTexts = ['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul Edildi','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'];
                ?>
                <div class="q-status" style="background:<?= $statusColors[$q['status']] ?>"><?= $statusTexts[$q['status']] ?></div>
            </div>
        </div>
    </div>

    <div class="q-body">
        <?php if ($q['title']): ?>
        <div class="q-title"><?= sanitize($q['title']) ?></div>
        <?php endif; ?>

        <?php if ($q['header_text']): ?>
        <div class="q-header-text"><?= nl2br(sanitize($q['header_text'])) ?></div>
        <?php endif; ?>

        <div class="parties">
            <div class="party">
                <div class="party-label">Teklif Veren</div>
                <div class="party-name"><?= sanitize($companyName) ?></div>
                <div class="party-info">
                    <?php if ($companyAddress): ?><?= nl2br(sanitize($companyAddress)) ?><br><?php endif; ?>
                    <?php if ($companyTax): ?>Vergi: <?= sanitize($companyTax) ?><?= $companyTaxOff ? ' / ' . sanitize($companyTaxOff) : '' ?><br><?php endif; ?>
                </div>
            </div>
            <div class="party">
                <div class="party-label">Teklif Alıcı</div>
                <?php if ($q['company_name'] || $q['contact_name']): ?>
                <div class="party-name"><?= sanitize($q['company_name'] ?: $q['contact_name']) ?></div>
                <div class="party-info">
                    <?php if ($q['company_name'] && $q['contact_name']): ?><?= sanitize($q['contact_name']) ?><br><?php endif; ?>
                    <?php if ($q['c_email']): ?><?= sanitize($q['c_email']) ?><br><?php endif; ?>
                    <?php if ($q['c_phone']): ?><?= sanitize($q['c_phone']) ?><br><?php endif; ?>
                    <?php if ($q['c_city']): ?><?= sanitize($q['c_city']) ?><?= $q['c_country'] ? ', ' . sanitize($q['c_country']) : '' ?><br><?php endif; ?>
                    <?php if ($q['c_tax_number']): ?>Vergi: <?= sanitize($q['c_tax_number']) ?><?= $q['c_tax_office'] ? ' / ' . sanitize($q['c_tax_office']) : '' ?><?php endif; ?>
                </div>
                <?php else: ?>
                <div class="party-info" style="color:#8a9ab0;font-style:italic">Belirtilmemiş</div>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th>Ürün / Hizmet</th>
                    <th style="width:60px" class="text-center">Birim</th>
                    <th style="width:65px" class="text-right">Miktar</th>
                    <th style="width:100px" class="text-right">Birim Fiyat</th>
                    <th style="width:55px" class="text-right">İsk.%</th>
                    <th style="width:55px" class="text-right">KDV%</th>
                    <th style="width:100px" class="text-right">Toplam</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td style="color:#8a9ab0"><?= $i+1 ?></td>
                    <td>
                        <div class="item-name"><?= sanitize($item['name']) ?></div>
                        <?php if ($item['code']): ?><div class="item-code"><?= sanitize($item['code']) ?></div><?php endif; ?>
                        <?php if ($item['description']): ?><div class="item-desc"><?= nl2br(sanitize($item['description'])) ?></div><?php endif; ?>
                    </td>
                    <td class="text-center"><?= sanitize($item['unit']) ?></td>
                    <td class="text-right"><?= number_format($item['quantity'], $item['quantity'] == intval($item['quantity']) ? 0 : 2, ',', '.') ?></td>
                    <td class="text-right"><?= formatMoney($item['unit_price'], $q['currency']) ?></td>
                    <td class="text-right"><?= $item['discount_percent'] > 0 ? '%' . number_format($item['discount_percent'], 0) : '—' ?></td>
                    <td class="text-right">%<?= number_format($item['tax_rate'], 0) ?></td>
                    <td class="text-right" style="font-weight:700;color:#0066cc"><?= formatMoney($item['total'], $q['currency']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-wrap">
            <div class="totals-box">
                <div class="total-row"><span>Ara Toplam</span><span><?= formatMoney($q['subtotal'], $q['currency']) ?></span></div>
                <?php if ($q['discount_amount'] > 0): ?>
                <div class="total-row total-discount">
                    <span>İskonto<?= $q['discount_type']==='percent' ? ' (%'.number_format($q['discount_value'],0).')' : '' ?></span>
                    <span>-<?= formatMoney($q['discount_amount'], $q['currency']) ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row"><span>KDV Toplam</span><span><?= formatMoney($q['tax_total'], $q['currency']) ?></span></div>
                <div class="total-row grand-total"><span>GENEL TOPLAM</span><span><?= formatMoney($q['total'], $q['currency']) ?></span></div>
                <div class="total-words"><?= numberToWords($q['total'], $q['currency']) ?></div>
            </div>
        </div>

        <?php if ($q['notes']): ?>
        <div class="notes-box">
            <div class="notes-label">Notlar</div>
            <div class="notes-text"><?= nl2br(sanitize($q['notes'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($bankName): ?>
        <div class="bank-box">
            <div class="bank-label">Banka Bilgileri</div>
            <div class="bank-info">
                <strong><?= sanitize($bankName) ?></strong><?= $bankBranch ? ' - ' . sanitize($bankBranch) . ' Şubesi' : '' ?><br>
                IBAN: <strong><?= sanitize($bankIban) ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($q['terms']): ?>
        <div class="terms-box">
            <div class="terms-label">Şartlar & Koşullar</div>
            <div class="terms-text"><?= nl2br(sanitize($q['terms'])) ?></div>
        </div>
        <?php endif; ?>

        <div class="sig-area">
            <div class="sig-box">
                <div class="sig-space"></div>
                <div class="sig-label">İmza / Kaşe (Teklif Veren)</div>
                <div style="font-size:12px;color:#8a9ab0;margin-top:4px"><?= sanitize($companyName) ?></div>
            </div>
            <div class="sig-box">
                <div class="sig-space"></div>
                <div class="sig-label">İmza / Kaşe (Teklif Alan)</div>
                <div style="font-size:12px;color:#8a9ab0;margin-top:4px"><?= sanitize($q['company_name'] ?: $q['contact_name'] ?: '—') ?></div>
            </div>
        </div>
    </div>

    <?php if ($q['footer_text']): ?>
    <div class="q-footer"><?= nl2br(sanitize($q['footer_text'])) ?></div>
    <?php else: ?>
    <div class="q-footer"><?= sanitize($companyName) ?> &bull; <?= sanitize($companyWeb ?: $companyEmail) ?></div>
    <?php endif; ?>
</div>

</body>
</html>
