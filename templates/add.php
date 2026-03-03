<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
$pageTitle = 'Yeni Şablon';
requireLogin();
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) $errors[] = 'Şablon adı zorunludur.';
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO quote_templates (name,description,header_text,footer_text,terms_conditions,notes,validity_days,discount_type,discount_value,show_bank_info,show_terms,template_style,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $name,
            trim($_POST['description'] ?? ''),
            trim($_POST['header_text'] ?? ''),
            trim($_POST['footer_text'] ?? ''),
            trim($_POST['terms_conditions'] ?? ''),
            trim($_POST['notes'] ?? ''),
            (int)($_POST['validity_days'] ?? 30),
            $_POST['discount_type'] ?? 'percent',
            (float)str_replace(',', '.', $_POST['discount_value'] ?? 0),
            isset($_POST['show_bank_info']) ? 1 : 0,
            isset($_POST['show_terms']) ? 1 : 0,
            $_POST['template_style'] ?? 'modern',
            isset($_POST['is_default']) ? 1 : 0,
        ]);
        if (isset($_POST['is_default'])) {
            $newId = $db->lastInsertId();
            $db->exec("UPDATE quote_templates SET is_default=0 WHERE id != $newId");
        }
        flash('success', 'Şablon oluşturuldu.');
        header('Location: index.php');
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Şablonlar</a> <span class="separator">/</span> Yeni Şablon
</div>
<div class="row justify-content-center">
<div class="col-lg-10">
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-plus-circle-fill text-primary me-2"></i>Yeni Teklif Şablonu</span>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Temel Bilgiler</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-texts">Metinler</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-settings">Ayarlar</a></li>
        </ul>
        <div class="tab-content">
            <!-- Basic -->
            <div class="tab-pane fade show active" id="tab-basic">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Şablon Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" placeholder="Standart Teklif, Yazılım Teklifi..." required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Geçerlilik Süresi (Gün)</label>
                        <input type="number" name="validity_days" class="form-control" value="<?= (int)($_POST['validity_days'] ?? 30) ?>" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Şablon hakkında kısa açıklama"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">İskonto Tipi</label>
                        <select name="discount_type" class="form-select">
                            <option value="percent">Yüzde (%)</option>
                            <option value="amount">Tutar</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Varsayılan İskonto Değeri</label>
                        <input type="number" name="discount_value" step="0.01" min="0" class="form-control" value="<?= $_POST['discount_value'] ?? '0' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Şablon Stili</label>
                        <select name="template_style" class="form-select">
                            <option value="modern">Modern</option>
                            <option value="classic">Klasik</option>
                            <option value="minimal">Minimal</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_bank_info" id="showBank" value="1" checked>
                                <label class="form-check-label" for="showBank">Banka bilgilerini göster</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_terms" id="showTerms" value="1" checked>
                                <label class="form-check-label" for="showTerms">Şartlar & koşullar bölümünü göster</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" value="1">
                                <label class="form-check-label" for="isDefault"><strong>Varsayılan şablon olarak ayarla</strong></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Texts -->
            <div class="tab-pane fade" id="tab-texts">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Başlık Metni <small class="text-muted">(Teklif içeriğinden önce görünür)</small></label>
                        <textarea name="header_text" class="form-control" rows="4" placeholder="Sayın Müşterimiz, ürün ve hizmetlerimize gösterdiğiniz ilgi için teşekkür ederiz..."><?= sanitize($_POST['header_text'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notlar <small class="text-muted">(Teklif notları - müşteriye görünür)</small></label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Teslimat süresi, kurulum bilgileri..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Şartlar & Koşullar</label>
                        <textarea name="terms_conditions" class="form-control" rows="6" placeholder="Teklif geçerlilik süresi 30 gündür.&#10;Ödemeler peşin veya mutabık kalınan koşullara göre gerçekleştirilir.&#10;KDV fiyatlara dahil değildir."><?= sanitize($_POST['terms_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alt Bilgi Metni <small class="text-muted">(Teklifin en altında görünür)</small></label>
                        <textarea name="footer_text" class="form-control" rows="3" placeholder="Bu teklif firmamız tarafından hazırlanmıştır. Sorularınız için lütfen bize ulaşın."><?= sanitize($_POST['footer_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="tab-pane fade" id="tab-settings">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Bu şablonun varsayılan ayarları teklif oluşturulurken otomatik olarak uygulanacaktır. Teklif oluştururken değiştirebilirsiniz.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <h6 style="font-weight:700;margin-bottom:12px">Önizleme</h6>
                        <div style="background:var(--bg-body);border-radius:8px;padding:16px;font-size:13px">
                            <strong>Bu şablon şu özelliklere sahip olacak:</strong>
                            <ul style="margin-top:8px;padding-left:20px;line-height:2">
                                <li>Geçerlilik süresi: <span id="prevValidity">30</span> gün</li>
                                <li>Banka bilgileri: Gösterilecek</li>
                                <li>Şartlar & koşullar: Gösterilecek</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="index.php" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Şablonu Kaydet</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>
<script>
$('[name=validity_days]').on('input', function() { $('#prevValidity').text($(this).val()); });
</script>
<?php include '../includes/footer.php'; ?>
