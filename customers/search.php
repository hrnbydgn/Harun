<?php
require_once '../config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo '[]'; exit; }
$q = trim($_GET['q'] ?? '');
$db = getDB();
$stmt = $db->prepare("SELECT id, CONCAT_WS(' - ', company_name, contact_name) as text FROM customers WHERE (company_name LIKE ? OR contact_name LIKE ?) AND status='active' LIMIT 20");
$s = "%$q%";
$stmt->execute([$s, $s]);
$results = $stmt->fetchAll();
echo json_encode($results);
