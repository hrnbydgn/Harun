<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM quotes WHERE id=?");
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) { flash('error', 'Teklif bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = 'Teklif Düzenle: ' . $quote['quote_number'];
$existingItems = $db->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order");
$existingItems->execute([$id]);
$existingItems = $existingItems->fetchAll();

$customers  = $db->query("SELECT id, company_name, contact_name FROM customers WHERE status='active' ORDER BY company_name, contact_name")->fetchAll();
$templates  = $db->query("SELECT * FROM quote_templates ORDER BY is_default DESC, name")->fetchAll();
$products   = $db->query("SELECT p.*, pc.name as cat_name FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.status='active' ORDER BY p.name")->fetchAll();

$defaultCurrency = $quote['currency'] ?? getSetting('default_currency', 'TRY');
$defaultTaxRate  = getSetting('default_tax_rate', '20');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $quoteNum   = trim($_POST['quote_number'] ?? $quote['quote_number']);
    $customerId = (int)($_POST['customer_id'] ?? 0) ?: null;
    $templateId = (int)($_POST['template_id'] ?? 0) ?: null;
    $title      = trim($_POST['title'] ?? '');
    $status     = $_POST['status'] ?? 'draft';
    $issueDate  = $_POST['issue_date'] ?? date('Y-m-d');
    $validUntil = $_POST['valid_until'] ?? '';
    $currency   = $_POST['currency'] ?? 'TRY';
    $discType   = $_POST['discount_type'] ?? 'percent';
    $discVal    = (float)str_replace(',', '.', $_POST['discount_value'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');
    $terms      = trim($_POST['terms'] ?? '');
    $headerText = trim($_POST['header_text'] ?? '');
    $footerText = trim($_POST['footer_text'] ?? '');

    // Check quote number unique (excluding current)
    $exist = $db->prepare("SELECT COUNT(*) FROM quotes WHERE quote_number=? AND id!=?");
    $exist->execute([$quoteNum, $id]);
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
        $discAmount = $discType === 'percent' ? $subtotal * ($discVal / 100) : $discVal;
        $finalTotal = ($subtotal - $discAmount) + $taxTotal;

        $stmt = $db->prepare("UPDATE quotes SET quote_number=?,customer_id=?,template_id=?,title=?,status=?,issue_date=?,valid_until=?,currency=?,subtotal=?,discount_type=?,discount_value=?,discount_amount=?,tax_total=?,total=?,notes=?,terms=?,footer_text=?,header_text=? WHERE id=?");
        $stmt->execute([$quoteNum,$customerId,$templateId,$title,$status,$issueDate,$validUntil ?: null,$currency,$subtotal,$discType,$discVal,$discAmount,$taxTotal,$finalTotal,$notes,$terms,$footerText,$headerText,$id]);

        $db->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$id]);
        foreach ($items as $idx => $item) {
            $si = $db->prepare("INSERT INTO quote_items (quote_id,product_id,code,name,description,unit,quantity,unit_price,tax_rate,discount_percent,subtotal,tax_amount,total,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $si->execute([$id,$item['prodId'],$item['code'],$item['name'],$item['desc'],$item['unit'],$item['qty'],$item['price'],$item['taxRate'],$item['discPct'],$item['lineAfterDisc'],$item['lineTax'],$item['lineTotal'],$idx]);
        }

        $db->prepare("INSERT INTO quote_activity (quote_id,user_id,action,description) VALUES (?,?,?,?)")->execute([$id,$_SESSION['user_id'],'updated','Teklif güncellendi.']);
        flash('success', 'Teklif güncellendi.');
        header('Location: view.php?id=' . $id);
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Teklifler</a> <span class="separator">/</span>
    <a href="view.php?id=<?= $id ?>"><?= sanitize($quote['quote_number']) ?></a> <span class="separator">/</span> Düzenle
</div>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" id="quoteForm">
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-info-circle-fill text-primary me-2"></i>Teklif Bilgileri</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Teklif Numarası</label>
                        <input type="text" name="quote_number" class="form-control text-mono" value="<?= sanitize($quote['quote_number']) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Teklif Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?= sanitize($quote['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Müşteri</label>
                        <select name="customer_id" class="form-select select2">
                            <option value="">Müşteri Seçin</option>
                            <?php foreach ($customers as $cu): ?>
                                <option value="<?= $cu['id'] ?>" <?= $quote['customer_id'] == $cu['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($cu['company_name'] ?: $cu['contact_name']) ?><?= ($cu['company_name'] && $cu['contact_name']) ? ' - ' . sanitize($cu['contact_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şablon</label>
                        <select name="template_id" class="form-select" id="templateSelect">
                            <option value="">Şablon Seçin</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>" data-validity="<?= $t['validity_days'] ?>" data-footer="<?= htmlspecialchars($t['footer_text'] ?? '') ?>" data-terms="<?= htmlspecialchars($t['terms_conditions'] ?? '') ?>" data-header="<?= htmlspecialchars($t['header_text'] ?? '') ?>" data-notes="<?= htmlspecialchars($t['notes'] ?? '') ?>"
                                    <?= $quote['template_id'] == $t['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teklif Tarihi</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= $quote['issue_date'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Geçerlilik Tarihi</label>
                        <input type="date" name="valid_until" class="form-control" id="validUntilInput" value="<?= $quote['valid_until'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Para Birimi</label>
                        <select name="currency" class="form-select" id="currencySelect">
                            <?php foreach (['TRY'=>'₺ TRY','USD'=>'$ USD','EUR'=>'€ EUR','GBP'=>'£ GBP'] as $c => $l): ?>
                                <option value="<?= $c ?>" <?= $quote['currency'] === $c ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durum</label>
                        <select name="status" class="form-select">
                            <?php foreach (['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul Edildi','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'] as $st => $stl): ?>
                                <option value="<?= $st ?>" <?= $quote['status'] === $st ? 'selected' : '' ?>><?= $stl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header" style="cursor:pointer" id="headerToggle">
                <span><i class="bi bi-card-heading text-primary me-2"></i>Teklif Başlık Metni</span>
                <i class="bi bi-chevron-<?= !empty($quote['header_text']) ? 'up' : 'down' ?>" id="headerChevron"></i>
            </div>
            <div class="card-body" id="headerBody" <?= empty($quote['header_text']) ? 'style="display:none"' : '' ?>>
                <textarea name="header_text" class="form-control" rows="3" id="headerTextArea"><?= sanitize($quote['header_text'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Items -->
        <div class="card mb-3">
            <div class="card-header">
                <span><i class="bi bi-list-ul text-primary me-2"></i>Teklif Kalemleri</span>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-search me-1"></i>Ürün Ara</button>
                        <div class="dropdown-menu p-3" style="width:380px;max-height:400px;overflow-y:auto">
                            <input type="text" class="form-control form-control-sm mb-2" id="productSearchInput" placeholder="Ürün adı veya kodu...">
                            <div id="productSearchList">
                                <?php foreach ($products as $p): ?>
                                <div class="product-search-item" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-code="<?= htmlspecialchars($p['code'] ?? '') ?>" data-price="<?= $p['price'] ?>" data-unit="<?= htmlspecialchars($p['unit']) ?>" data-tax="<?= $p['tax_rate'] ?>" data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>" onclick="addProductFromSearch(this)">
                                    <div><div class="prod-name"><?= sanitize($p['name']) ?></div><div class="prod-code"><?= sanitize($p['code'] ?? $p['cat_name'] ?? '') ?></div></div>
                                    <span class="prod-price"><?= formatMoney($p['price'], $p['currency']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addItemRow()"><i class="bi bi-plus-lg me-1"></i>Kalem Ekle</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="quote-items-table">
                    <thead>
                        <tr>
                            <th style="width:28px"></th><th style="width:80px">Kod</th><th>Ürün / Açıklama</th>
                            <th style="width:70px">Birim</th><th style="width:75px">Miktar</th><th style="width:110px">Birim Fiyat</th>
                            <th style="width:65px">İsk.%</th><th style="width:60px">KDV%</th><th style="width:110px">Toplam</th><th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
                </div>
                <div class="p-3"><button type="button" class="btn btn-sm btn-secondary" onclick="addItemRow()"><i class="bi bi-plus me-1"></i>Satır Ekle</button></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><span><i class="bi bi-chat-left-text-fill text-primary me-2"></i>Notlar & Koşullar</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Teklif Notu</label>
                        <textarea name="notes" class="form-control" rows="4" id="notesArea"><?= sanitize($quote['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Şartlar & Koşullar</label>
                        <textarea name="terms" class="form-control" rows="4" id="termsArea"><?= sanitize($quote['terms'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alt Bilgi Metni</label>
                        <textarea name="footer_text" class="form-control" rows="2" id="footerArea"><?= sanitize($quote['footer_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card sticky-top" style="top:80px">
            <div class="card-header"><span><i class="bi bi-calculator-fill text-primary me-2"></i>Teklif Özeti</span></div>
            <div class="card-body">
                <div class="quote-totals">
                    <div class="totals-row"><span>Ara Toplam</span><span id="dispSubtotal">₺0,00</span></div>
                    <div class="row g-2 mb-2 mt-1">
                        <div class="col-5">
                            <select name="discount_type" class="form-select form-select-sm" id="discTypeSelect">
                                <option value="percent" <?= $quote['discount_type'] === 'percent' ? 'selected' : '' ?>>İskonto %</option>
                                <option value="amount" <?= $quote['discount_type'] === 'amount' ? 'selected' : '' ?>>İskonto Tutar</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <input type="number" name="discount_value" step="0.01" min="0" class="form-control form-control-sm" id="discValueInput" value="<?= $quote['discount_value'] ?? '0' ?>">
                        </div>
                    </div>
                    <div class="totals-row" id="discountRow"><span>İskonto</span><span id="dispDiscount">-₺0,00</span></div>
                    <div class="totals-row"><span>KDV Toplam</span><span id="dispTax">₺0,00</span></div>
                    <div class="totals-row total-final"><span>GENEL TOPLAM</span><span id="dispTotal">₺0,00</span></div>
                </div>
                <div id="totalInWords" class="mt-2 p-2 rounded" style="background:var(--bg-body);font-size:12px;color:var(--text-muted);font-style:italic"></div>
            </div>
            <div class="card-footer">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle me-2"></i>Teklifi Güncelle</button>
                    <div class="d-flex gap-2">
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary flex-1">Görüntüle</a>
                        <a href="pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary flex-1"><i class="bi bi-printer me-1"></i>PDF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const DEFAULT_TAX = <?= (float)$defaultTaxRate ?>;
const DEFAULT_CURRENCY = "<?= $defaultCurrency ?>";
</script>
<script src="<?= BASE_URL ?>/assets/js/quote-builder.js"></script>
<script>
$(function() {
    <?php foreach ($existingItems as $item): ?>
    addItemRow({
        id: <?= (int)($item['product_id'] ?? 0) ?>,
        name: <?= json_encode($item['name']) ?>,
        code: <?= json_encode($item['code'] ?? '') ?>,
        desc: <?= json_encode($item['description'] ?? '') ?>,
        unit: <?= json_encode($item['unit']) ?>,
        price: <?= (float)$item['unit_price'] ?>,
        tax: <?= (float)$item['tax_rate'] ?>,
        qty: <?= (float)$item['quantity'] ?>,
        disc: <?= (float)$item['discount_percent'] ?>
    });
    <?php endforeach; ?>
    if ($('#itemsBody tr').length === 0) addItemRow();
    
    $('#headerToggle').on('click', function() {
        $('#headerBody').toggle();
        $('#headerChevron').toggleClass('bi-chevron-down bi-chevron-up');
    });
    $('#productSearchInput').on('keyup', function() {
        var q = $(this).val().toLowerCase();
        $('#productSearchList .product-search-item').each(function() {
            var name = $(this).data('name').toLowerCase();
            var code = ($(this).data('code') || '').toLowerCase();
            $(this).toggle(name.includes(q) || code.includes(q));
        });
    });
    $('#templateSelect').on('change', function() {
        var opt = this.options[this.selectedIndex];
        if (!opt.value) return;
        if (opt.dataset.footer) $('#footerArea').val(opt.dataset.footer);
        if (opt.dataset.terms) $('#termsArea').val(opt.dataset.terms);
        if (opt.dataset.notes) $('#notesArea').val(opt.dataset.notes);
        if (opt.dataset.header) { $('#headerTextArea').val(opt.dataset.header); if (opt.dataset.header.trim()) $('#headerBody').show(); }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
