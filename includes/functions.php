<?php
if (!defined('APP_NAME')) {
    require_once dirname(__DIR__) . '/config.php';
}

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function getSetting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT key_name, value FROM company_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['key_name']] = $row['value'];
            }
        } catch (Exception $e) {
            return $default;
        }
    }
    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO company_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->execute([$key, $value, $value]);
}

function generateQuoteNumber() {
    $db = getDB();
    $prefix = getSetting('quote_prefix', 'TKL');
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) FROM quotes WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn() + 1;
    return $prefix . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function formatMoney($amount, $currency = null) {
    if ($currency === null) $currency = getSetting('default_currency', 'TRY');
    $symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format((float)$amount, 2, ',', '.');
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('d.m.Y', strtotime($date));
}

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function flash($type, $message) {
    startSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    startSession();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function getStatusBadge($status) {
    $badges = [
        'draft'    => ['class' => 'badge-secondary', 'text' => 'Taslak'],
        'sent'     => ['class' => 'badge-info', 'text' => 'Gönderildi'],
        'accepted' => ['class' => 'badge-success', 'text' => 'Kabul Edildi'],
        'rejected' => ['class' => 'badge-danger', 'text' => 'Reddedildi'],
        'expired'  => ['class' => 'badge-warning', 'text' => 'Süresi Doldu'],
    ];
    $b = $badges[$status] ?? ['class' => 'badge-secondary', 'text' => $status];
    return '<span class="badge ' . $b['class'] . '">' . $b['text'] . '</span>';
}

function getStatusText($status) {
    $texts = [
        'draft'    => 'Taslak',
        'sent'     => 'Gönderildi',
        'accepted' => 'Kabul Edildi',
        'rejected' => 'Reddedildi',
        'expired'  => 'Süresi Doldu',
    ];
    return $texts[$status] ?? $status;
}

function uploadLogo($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Geçersiz dosya türü.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Dosya boyutu 5MB\'dan büyük olamaz.'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '.' . $ext;
    $dest = UPLOAD_PATH . 'logo/' . $filename;
    if (!is_dir(UPLOAD_PATH . 'logo/')) {
        mkdir(UPLOAD_PATH . 'logo/', 0755, true);
    }
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => true, 'filename' => 'assets/uploads/logo/' . $filename];
    }
    return ['success' => false, 'error' => 'Dosya yüklenemedi.'];
}

function getCurrencySymbol($currency) {
    $symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
    return $symbols[$currency] ?? $currency;
}

function numberToWords($number, $currency = 'TRY') {
    $ones = ['', 'Bir', 'İki', 'Üç', 'Dört', 'Beş', 'Altı', 'Yedi', 'Sekiz', 'Dokuz'];
    $tens = ['', 'On', 'Yirmi', 'Otuz', 'Kırk', 'Elli', 'Altmış', 'Yetmiş', 'Seksen', 'Doksan'];
    
    $int = (int)$number;
    $dec = round(($number - $int) * 100);
    
    $currencyNames = ['TRY' => ['Türk Lirası', 'Kuruş'], 'USD' => ['Dolar', 'Sent'], 'EUR' => ['Euro', 'Sent']];
    $cName = $currencyNames[$currency] ?? [$currency, 'Kuruş'];
    
    $result = convertIntToWords($int, $ones, $tens) . ' ' . $cName[0];
    if ($dec > 0) {
        $result .= ' ' . convertIntToWords($dec, $ones, $tens) . ' ' . $cName[1];
    }
    return $result;
}

function convertIntToWords($n, $ones, $tens) {
    if ($n == 0) return 'Sıfır';
    $result = '';
    if ($n >= 1000000) {
        $result .= convertIntToWords((int)($n / 1000000), $ones, $tens) . ' Milyon ';
        $n %= 1000000;
    }
    if ($n >= 1000) {
        $thousands = (int)($n / 1000);
        if ($thousands == 1) $result .= 'Bin ';
        else $result .= convertIntToWords($thousands, $ones, $tens) . ' Bin ';
        $n %= 1000;
    }
    if ($n >= 100) {
        if ((int)($n / 100) == 1) $result .= 'Yüz ';
        else $result .= $ones[(int)($n / 100)] . ' Yüz ';
        $n %= 100;
    }
    if ($n >= 10) {
        $result .= $tens[(int)($n / 10)] . ' ';
        $n %= 10;
    }
    if ($n > 0) $result .= $ones[$n] . ' ';
    return trim($result);
}
