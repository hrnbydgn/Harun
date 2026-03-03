<?php
require_once '../config.php';
require_once '../includes/functions.php';
define('BASE_URL', '..');
requireLogin();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM quote_templates WHERE id=?");
$stmt->execute([$id]);
$tpl = $stmt->fetch();
if (!$tpl) { flash('error', 'Şablon bulunamadı.'); header('Location: index.php'); exit; }

$pageTitle = 'Şablon Düzenle';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) $errors[] = 'Şablon adı zorunludur.';
    if (empty($errors)) {
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        if ($isDefault) $db->exec("UPDATE quote_templates SET is_default=0");
        $stmt = $db->prepare("UPDATE quote_templates SET name=?,description=?,header_text=?,footer_text=?,terms_conditions=?,notes=?,validity_days=?,discount_type=?,discount_value=?,show_bank_info=?,show_terms=?,template_style=?,is_default=? WHERE id=?");
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
            $isDefault,
            $id
        ]);
        flash('success', 'Şablon güncellendi.');
        header('Location: index.php');
        exit;
    }
    $tpl = array_merge($tpl, $_POST);
}

include '../includes/header.php';
?>
<div class="page-breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Dashboard</a> <span class="separator">/</span>
    <a href="index.php">Şablonlar</a> <span class="separator">/</span> Düzenle
</div>
<div class="row justify-content-center">
<div class="col-lg-10">
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-pencil-fill text-primary me-2"></i>Şablon Düzenle: <?= sanitize($tpl['name']) ?></span>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
    </div>
    <div class="card-body">
    <form method="post">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Temel Bilgiler</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-texts">Metinler</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-basic">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Şablon Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= sanitize($tpl['name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Geçerlilik Süresi (Gün)</label>
                        <input type="number" name="validity_days" class="form-control" value="<?= $tpl['validity_days'] ?>" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="2"><?= sanitize($tpl['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">İskonto Tipi</label>
                        <select name="discount_type" class="form-select">
                            <option value="percent" <?= $tpl['discount_type'] === 'percent' ? 'selected' : '' ?>>Yüzde (%)</option>
                            <option value="amount" <?= $tpl['discount_type'] === 'amount' ? 'selected' : '' ?>>Tutar</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Varsayılan İskonto Değeri</label>
                        <input type="number" name="discount_value" step="0.01" min="0" class="form-control" value="<?= $tpl['discount_value'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Şablon Stili</label>
                        <select name="template_style" class="form-select">
                            <option value="modern" <?= $tpl['template_style'] === 'modern' ? 'selected' : '' ?>>Modern</option>
                            <option value="classic" <?= $tpl['template_style'] === 'classic' ? 'selected' : '' ?>>Klasik</option>
                            <option value="minimal" <?= $tpl['template_style'] === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_bank_info" value="1" <?= $tpl['show_bank_info'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Banka bilgilerini göster</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_terms" value="1" <?= $tpl['show_terms'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Şartlar & koşullar bölümünü göster</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" <?= $tpl['is_default'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><strong>Varsayılan şablon</strong></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="tab-texts">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Başlık Metni</label>
                        <textarea name="header_text" class="form-control" rows="4"><?= sanitize($tpl['header_text'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notlar</label>
                        <textarea name="notes" class="form-control" rows="4"><?= sanitize($tpl['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Şartlar & Koşullar</label>
                        <textarea name="terms_conditions" class="form-control" rows="6"><?= sanitize($tpl['terms_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alt Bilgi Metni</label>
                        <textarea name="footer_text" class="form-control" rows="3"><?= sanitize($tpl['footer_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <hr class="section-divider">
        <div class="d-flex gap-2 justify-content-end">
            <a href="index.php" class="btn btn-secondary">İptal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Güncelle</button>
        </div>
    </form>
    </div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
