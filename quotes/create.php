<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Yeni Teklif Oluştur';
requireLogin();
$db = getDB();

$customers  = $db->query("SELECT id, company_name, contact_name FROM customers WHERE status='active' ORDER BY company_name, contact_name")->fetchAll();
$templates  = $db->query("SELECT * FROM quote_templates ORDER BY is_default DESC, name")->fetchAll();
$products   = $db->query("SELECT p.*, pc.name as cat_name FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.status='active' ORDER BY p.name")->fetchAll();

$defaultTemplate = null;
foreach ($templates as $t) { if ($t['is_default']) { $defaultTemplate = $t; break; } }
if (!$defaultTemplate && $templates) $defaultTemplate = $templates[0];

$defaultCurrency = getSetting('default_currency', 'TRY');
$defaultTaxRate  = getSetting('default_tax_rate', '20');
$validityDays    = getSetting('quote_validity_days', '30');
$quoteNumber     = generateQuoteNumber();

$preCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $quoteNum    = trim($_POST['quote_number'] ?? $quoteNumber);
    $customerId  = (int)($_POST['customer_id'] ?? 0) ?: null;
    $templateId  = (int)($_POST['template_id'] ?? 0) ?: null;
    $title       = trim($_POST['title'] ?? '');
    $status      = $_POST['status'] ?? 'draft';
    $issueDate   = $_POST['issue_date'] ?? date('Y-m-d');
    $validUntil  = $_POST['valid_until'] ?? '';
    $currency    = $_POST['currency'] ?? 'TRY';
    $discType    = $_POST['discount_type'] ?? 'percent';
    $discVal     = (float)str_replace(',', '.', $_POST['discount_value'] ?? 0);
    $notes       = trim($_POST['notes'] ?? '');
    $terms       = trim($_POST['terms'] ?? '');
    $headerText  = trim($_POST['header_text'] ?? '');
    $footerText  = trim($_POST['footer_text'] ?? '');

    // Check quote number unique
    $exist = $db->prepare("SELECT COUNT(*) FROM quotes WHERE quote_number=?");
    $exist->execute([$quoteNum]);
    if ($exist->fetchColumn()) $errors[] = 'Bu teklif numarası zaten kullanılıyor.';

    $items = [];
    $itemNames = $_POST['item_name'] ?? [];
    for ($i = 0; $i < count($itemNames); $i++) {
        $name = trim($itemNames[$i] ?? '');
        if (empty($name)) continue;
        $qty = (float)str_replace(',', '.', $_POST['item_qty'][$i] ?? 1);
        $price = (float)str_replace(',', '.', $_POST['item_price'][$i] ?? 0);
        $taxRate = (float)str_replace(',', '.', $_POST['item_tax'][$i] ?? $defaultTaxRate);
        $discPct = (float)str_replace(',', '.', $_POST['item_disc'][$i] ?? 0);
        $prodId = isset($_POST['item_product_id'][$i]) && $_POST['item_product_id'][$i] ? (int)$_POST['item_product_id'][$i] : null;
        $unit = trim($_POST['item_unit'][$i] ?? 'Adet');
        $desc = trim($_POST['item_desc'][$i] ?? '');
        $code = trim($_POST['item_code'][$i] ?? '');

        $lineSubtotal = $qty * $price;
        $lineDisc = $lineSubtotal * ($discPct / 100);
        $lineAfterDisc = $lineSubtotal - $lineDisc;
        $lineTax = $lineAfterDisc * ($taxRate / 100);
        $lineTotal = $lineAfterDisc + $lineTax;

        $items[] = compact('name','qty','price','taxRate','discPct','prodId','unit','desc','code','lineSubtotal','lineDisc','lineAfterDisc','lineTax','lineTotal');
    }

    if (empty($items)) $errors[] = 'En az bir teklif kalemi eklemelisiniz.';

    if (empty($errors)) {
        $subtotal = array_sum(array_column($items, 'lineAfterDisc'));
        $taxTotal = array_sum(array_column($items, 'lineTax'));

        if ($discType === 'percent') {
            $discAmount = $subtotal * ($discVal / 100);
        } else {
            $discAmount = $discVal;
        }
        $finalSubtotal = $subtotal - $discAmount;
        $finalTax = $taxTotal;
        $finalTotal = $finalSubtotal + $finalTax;

        $stmt = $db->prepare("INSERT INTO quotes (quote_number,customer_id,template_id,title,status,issue_date,valid_until,currency,subtotal,discount_type,discount_value,discount_amount,tax_total,total,notes,terms,footer_text,header_text,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$quoteNum,$customerId,$templateId,$title,$status,$issueDate,$validUntil ?: null,$currency,$subtotal,$discType,$discVal,$discAmount,$taxTotal,$finalTotal,$notes,$terms,$footerText,$headerText,$_SESSION['user_id']]);
        $quoteId = $db->lastInsertId();

        foreach ($items as $idx => $item) {
            $si = $db->prepare("INSERT INTO quote_items (quote_id,product_id,code,name,description,unit,quantity,unit_price,tax_rate,discount_percent,subtotal,tax_amount,total,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $si->execute([$quoteId,$item['prodId'],$item['code'],$item['name'],$item['desc'],$item['unit'],$item['qty'],$item['price'],$item['taxRate'],$item['discPct'],$item['lineAfterDisc'],$item['lineTax'],$item['lineTotal'],$idx]);
        }

        $db->prepare("INSERT INTO quote_activity (quote_id,user_id,action,description) VALUES (?,?,?,?)")->execute([$quoteId,$_SESSION['user_id'],'created','Teklif oluşturuldu.']);

        flash('success', 'Teklif #' . $quoteNum . ' başarıyla oluşturuldu.');
        header('Location: view.php?id=' . $quoteId);
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Teklifler</a> <span class="separator">/</span> Yeni Teklif
</div>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-2"></i><?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" id="quoteForm">
<div class="row g-3">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Basic Info -->
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-info-circle-fill text-primary me-2"></i>Teklif Bilgileri</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Teklif Numarası</label>
                        <input type="text" name="quote_number" class="form-control text-mono" value="<?= sanitize($quoteNumber) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Teklif Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?= sanitize($_POST['title'] ?? '') ?>" placeholder="DroFarm D10 Plus - Tarım Drone Teklifi">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Müşteri</label>
                        <select name="customer_id" class="form-select select2" id="customerSelect">
                            <option value="">Müşteri Seçin (İsteğe Bağlı)</option>
                            <?php foreach ($customers as $cu): ?>
                                <option value="<?= $cu['id'] ?>" <?= ($preCustomerId == $cu['id'] || ($_POST['customer_id'] ?? 0) == $cu['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($cu['company_name'] ?: $cu['contact_name']) ?>
                                    <?= ($cu['company_name'] && $cu['contact_name']) ? ' - ' . sanitize($cu['contact_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şablon</label>
                        <select name="template_id" class="form-select" id="templateSelect">
                            <option value="">Şablon Seçin</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>"
                                    data-validity="<?= $t['validity_days'] ?>"
                                    data-footer="<?= htmlspecialchars($t['footer_text'] ?? '') ?>"
                                    data-terms="<?= htmlspecialchars($t['terms_conditions'] ?? '') ?>"
                                    data-header="<?= htmlspecialchars($t['header_text'] ?? '') ?>"
                                    data-notes="<?= htmlspecialchars($t['notes'] ?? '') ?>"
                                    data-disc-type="<?= $t['discount_type'] ?>"
                                    data-disc-val="<?= $t['discount_value'] ?>"
                                    <?= (($_POST['template_id'] ?? 0) == $t['id'] || (!isset($_POST['template_id']) && $t['is_default'])) ? 'selected' : '' ?>>
                                    <?= sanitize($t['name']) ?><?= $t['is_default'] ? ' (Varsayılan)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teklif Tarihi</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= $_POST['issue_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Geçerlilik Tarihi</label>
                        <input type="date" name="valid_until" class="form-control" id="validUntilInput" value="<?= $_POST['valid_until'] ?? date('Y-m-d', strtotime("+$validityDays days")) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Para Birimi</label>
                        <select name="currency" class="form-select" id="currencySelect">
                            <?php foreach (['TRY'=>'₺ TRY','USD'=>'$ USD','EUR'=>'€ EUR','GBP'=>'£ GBP'] as $c => $l): ?>
                                <option value="<?= $c ?>" <?= ($defaultCurrency === $c || ($_POST['currency'] ?? '') === $c) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durum</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Taslak</option>
                            <option value="sent" <?= ($_POST['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Gönderildi</option>
                            <option value="accepted" <?= ($_POST['status'] ?? '') === 'accepted' ? 'selected' : '' ?>>Kabul Edildi</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header text -->
        <div class="card mb-3">
            <div class="card-header" style="cursor:pointer" id="headerToggle">
                <span><i class="bi bi-card-heading text-primary me-2"></i>Teklif Başlık Metni</span>
                <i class="bi bi-chevron-down" id="headerChevron"></i>
            </div>
            <div class="card-body" id="headerBody" style="display:none">
                <textarea name="header_text" class="form-control" rows="3" id="headerTextArea" placeholder="Teklif üstünde görünecek hoş geldiniz metni..."><?= sanitize($_POST['header_text'] ?? $defaultTemplate['header_text'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Quote Items -->
        <div class="card mb-3">
            <div class="card-header">
                <span><i class="bi bi-list-ul text-primary me-2"></i>Teklif Kalemleri</span>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-search me-1"></i>Ürün Ara
                        </button>
                        <div class="dropdown-menu p-3" style="width:380px;max-height:400px;overflow-y:auto" id="productSearchDropdown">
                            <input type="text" class="form-control form-control-sm mb-2" id="productSearchInput" placeholder="Ürün adı veya kodu ile ara...">
                            <div id="productSearchList">
                                <?php foreach ($products as $p): ?>
                                <div class="product-search-item" 
                                     data-id="<?= $p['id'] ?>"
                                     data-name="<?= htmlspecialchars($p['name']) ?>"
                                     data-code="<?= htmlspecialchars($p['code'] ?? '') ?>"
                                     data-price="<?= $p['price'] ?>"
                                     data-unit="<?= htmlspecialchars($p['unit']) ?>"
                                     data-tax="<?= $p['tax_rate'] ?>"
                                     data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                     onclick="addProductFromSearch(this)">
                                    <div>
                                        <div class="prod-name"><?= sanitize($p['name']) ?></div>
                                        <div class="prod-code"><?= sanitize($p['code'] ?? $p['cat_name'] ?? '') ?></div>
                                    </div>
                                    <span class="prod-price"><?= formatMoney($p['price'], $p['currency']) ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (!$products): ?>
                                <div class="text-center text-muted py-3" style="font-size:13px">Ürün bulunamadı</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addItemRow()">
                        <i class="bi bi-plus-lg me-1"></i>Kalem Ekle
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="quote-items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:28px"></th>
                            <th style="width:80px">Kod</th>
                            <th>Ürün / Açıklama</th>
                            <th style="width:70px">Birim</th>
                            <th style="width:75px">Miktar</th>
                            <th style="width:110px">Birim Fiyat</th>
                            <th style="width:65px">İsk.%</th>
                            <th style="width:60px">KDV%</th>
                            <th style="width:110px">Toplam</th>
                            <th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- JS ile doldurulacak -->
                    </tbody>
                </table>
                </div>
                <div class="p-3">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addItemRow()">
                        <i class="bi bi-plus me-1"></i>Satır Ekle
                    </button>
                </div>
            </div>
        </div>

        <!-- Notes & Terms -->
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-chat-left-text-fill text-primary me-2"></i>Notlar & Koşullar</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Teklif Notu <small class="text-muted">(Müşteriye görünür)</small></label>
                        <textarea name="notes" class="form-control" rows="4" id="notesArea" placeholder="Müşteriye özel notlar, teslimat bilgileri..."><?= sanitize($_POST['notes'] ?? $defaultTemplate['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şartlar & Koşullar</label>
                        <textarea name="terms" class="form-control" rows="4" id="termsArea" placeholder="Ödeme koşulları, garanti bilgileri..."><?= sanitize($_POST['terms'] ?? $defaultTemplate['terms_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alt Bilgi Metni</label>
                        <textarea name="footer_text" class="form-control" rows="2" id="footerArea" placeholder="Teklif altında görünecek bilgiler..."><?= sanitize($_POST['footer_text'] ?? $defaultTemplate['footer_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Totals -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top:80px">
            <div class="card-header"><span><i class="bi bi-calculator-fill text-primary me-2"></i>Teklif Özeti</span></div>
            <div class="card-body">
                <div class="quote-totals">
                    <div class="totals-row">
                        <span>Ara Toplam</span>
                        <span id="dispSubtotal">₺0,00</span>
                    </div>
                    <div class="row g-2 mb-2 mt-1">
                        <div class="col-5">
                            <select name="discount_type" class="form-select form-select-sm" id="discTypeSelect">
                                <option value="percent" <?= ($_POST['discount_type'] ?? $defaultTemplate['discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>İskonto %</option>
                                <option value="amount" <?= ($_POST['discount_type'] ?? '') === 'amount' ? 'selected' : '' ?>>İskonto Tutar</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <input type="number" name="discount_value" step="0.01" min="0" class="form-control form-control-sm" id="discValueInput" value="<?= $_POST['discount_value'] ?? $defaultTemplate['discount_value'] ?? '0' ?>" placeholder="0">
                        </div>
                    </div>
                    <div class="totals-row" id="discountRow" style="<?= ($_POST['discount_value'] ?? $defaultTemplate['discount_value'] ?? 0) > 0 ? '' : 'display:none' ?>">
                        <span>İskonto</span>
                        <span id="dispDiscount">-₺0,00</span>
                    </div>
                    <div class="totals-row">
                        <span>KDV Toplam</span>
                        <span id="dispTax">₺0,00</span>
                    </div>
                    <div class="totals-row total-final">
                        <span>GENEL TOPLAM</span>
                        <span id="dispTotal">₺0,00</span>
                    </div>
                </div>
                <div id="totalInWords" class="mt-2 p-2 rounded" style="background:var(--bg-body);font-size:12px;color:var(--text-muted);font-style:italic"></div>
            </div>
            <div class="card-footer">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle me-2"></i>Teklifi Oluştur</button>
                    <a href="index.php" class="btn btn-secondary">İptal</a>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<?php
$extraJs = '<script>
const BASE_URL = "' . BASE_URL . '";
const DEFAULT_TAX = ' . (float)$defaultTaxRate . ';
const DEFAULT_CURRENCY = "' . $defaultCurrency . '";
</script>';
$extraJs .= '<script src="' . BASE_URL . '/assets/js/quote-builder.js"></script>';
?>
<?php ob_start(); ?>
<script>
// Init from POST data if validation failed
<?php if (!empty($_POST['item_name'] ?? [])): ?>
$(function() {
    <?php for ($i = 0; $i < count($_POST['item_name'] ?? []); $i++): ?>
    <?php if (!empty($_POST['item_name'][$i])): ?>
    addItemRow({
        id: <?= (int)($_POST['item_product_id'][$i] ?? 0) ?>,
        name: <?= json_encode($_POST['item_name'][$i] ?? '') ?>,
        code: <?= json_encode($_POST['item_code'][$i] ?? '') ?>,
        desc: <?= json_encode($_POST['item_desc'][$i] ?? '') ?>,
        unit: <?= json_encode($_POST['item_unit'][$i] ?? 'Adet') ?>,
        price: <?= (float)str_replace(',', '.', $_POST['item_price'][$i] ?? 0) ?>,
        tax: <?= (float)str_replace(',', '.', $_POST['item_tax'][$i] ?? $defaultTaxRate) ?>,
        qty: <?= (float)str_replace(',', '.', $_POST['item_qty'][$i] ?? 1) ?>,
        disc: <?= (float)str_replace(',', '.', $_POST['item_disc'][$i] ?? 0) ?>
    });
    <?php endif; ?>
    <?php endfor; ?>
});
<?php else: ?>
$(function() { addItemRow(); });
<?php endif; ?>

// Template select handler
$('#templateSelect').on('change', function() {
    var opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    var validity = opt.dataset.validity;
    var footer = opt.dataset.footer;
    var terms = opt.dataset.terms;
    var header = opt.dataset.header;
    var notes = opt.dataset.notes;
    var discType = opt.dataset.discType;
    var discVal = opt.dataset.discVal;
    
    if (validity) {
        var issueDate = new Date($('[name=issue_date]').val() || new Date());
        var validDate = new Date(issueDate);
        validDate.setDate(validDate.getDate() + parseInt(validity));
        $('#validUntilInput').val(validDate.toISOString().substr(0,10));
    }
    if (footer) $('#footerArea').val(footer);
    if (terms) $('#termsArea').val(terms);
    if (notes) $('#notesArea').val(notes);
    if (header) { $('#headerTextArea').val(header); if (header.trim()) { $('#headerBody').show(); } }
    if (discType) { $('#discTypeSelect').val(discType); }
    if (discVal) { $('#discValueInput').val(discVal); }
    updateTotals();
});

// Header toggle
$('#headerToggle').on('click', function() {
    $('#headerBody').toggle();
    $('#headerChevron').toggleClass('bi-chevron-down bi-chevron-up');
});

// Product search filter
$('#productSearchInput').on('keyup', function() {
    var q = $(this).val().toLowerCase();
    $('#productSearchList .product-search-item').each(function() {
        var name = $(this).data('name').toLowerCase();
        var code = ($(this).data('code') || '').toLowerCase();
        $(this).toggle(name.includes(q) || code.includes(q));
    });
});
</script>
<?php $extraJs .= ob_get_clean(); ?>

<?php include '../includes/footer.php'; ?>
