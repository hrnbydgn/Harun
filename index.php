<?php
require_once 'config.php';
require_once 'includes/functions.php';
define('BASE_URL', '');
$pageTitle = 'Dashboard';
requireLogin();

$db = getDB();

// Stats
$totalQuotes = $db->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthTotal = $db->prepare("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status='accepted' AND issue_date BETWEEN ? AND ?");
$monthTotal->execute([$monthStart, $monthEnd]);
$monthTotal = $monthTotal->fetchColumn();

$pendingQuotes = $db->query("SELECT COUNT(*) FROM quotes WHERE status IN ('draft','sent')")->fetchColumn();
$acceptedQuotes = $db->query("SELECT COUNT(*) FROM quotes WHERE status='accepted'")->fetchColumn();
$rejectedQuotes = $db->query("SELECT COUNT(*) FROM quotes WHERE status='rejected'")->fetchColumn();

$totalAcceptedAmount = $db->query("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status='accepted'")->fetchColumn();

// Recent quotes
$recentQuotes = $db->query("
    SELECT q.*, c.company_name, c.contact_name 
    FROM quotes q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    ORDER BY q.created_at DESC LIMIT 8
")->fetchAll();

// Monthly chart data (last 6 months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM quotes WHERE status='accepted' AND DATE_FORMAT(issue_date,'%Y-%m') = ?");
    $stmt->execute([$month]);
    $row = $stmt->fetch();
    $chartData[] = ['month' => date('M Y', strtotime($month . '-01')), 'total' => $row['total'], 'cnt' => $row['cnt']];
}

// Status distribution
$statusDist = $db->query("SELECT status, COUNT(*) as cnt FROM quotes GROUP BY status")->fetchAll();
$statusMap = ['draft' => 0, 'sent' => 0, 'accepted' => 0, 'rejected' => 0, 'expired' => 0];
foreach ($statusDist as $s) $statusMap[$s['status']] = (int)$s['cnt'];

// Upcoming expirations
$expiring = $db->query("
    SELECT q.*, c.company_name 
    FROM quotes q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    WHERE q.status = 'sent' AND q.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY q.valid_until ASC LIMIT 5
")->fetchAll();

include 'includes/header.php';
?>
<div class="page-breadcrumb"><i class="bi bi-grid-fill"></i> Dashboard</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="quotes/index.php" class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalQuotes) ?></div>
                <div class="stat-label">Toplam Teklif</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="customers/index.php" class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalCustomers) ?></div>
                <div class="stat-label">Aktif Müşteri</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="quotes/index.php?status=accepted" class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= formatMoney($totalAcceptedAmount) ?></div>
                <div class="stat-label">Kabul Edilen Toplam</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="quotes/index.php?status=sent" class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($pendingQuotes) ?></div>
                <div class="stat-label">Bekleyen Teklif</div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Monthly Stats -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-bar-chart-fill text-primary me-2"></i>Aylık Teklif Özeti (Son 6 Ay)</span>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="80"></canvas>
            </div>
        </div>
    </div>
    <!-- Status Pie -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><span><i class="bi bi-pie-chart-fill text-primary me-2"></i>Teklif Durumları</span></div>
            <div class="card-body d-flex flex-column">
                <canvas id="statusChart" style="max-height:180px"></canvas>
                <div class="mt-3">
                    <?php
                    $statColors = ['draft'=>'#8a9ab0','sent'=>'#3b82f6','accepted'=>'#10b981','rejected'=>'#ef4444','expired'=>'#f59e0b'];
                    $statLabels = ['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul','rejected'=>'Reddedildi','expired'=>'Süresi Doldu'];
                    foreach ($statusMap as $status => $cnt):
                        if ($cnt == 0) continue; ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="display:flex;align-items:center;gap:6px;font-size:12.5px">
                                <span style="width:10px;height:10px;border-radius:50%;background:<?= $statColors[$status] ?>;display:inline-block"></span>
                                <?= $statLabels[$status] ?>
                            </span>
                            <span style="font-size:12.5px;font-weight:600"><?= $cnt ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Quotes -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-clock-history text-primary me-2"></i>Son Teklifler</span>
                <a href="quotes/index.php" class="btn btn-sm btn-secondary">Tümü</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recentQuotes): ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Müşteri</th>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentQuotes as $q): ?>
                            <tr>
                                <td><span class="text-mono fw-600" style="color:var(--primary)"><?= sanitize($q['quote_number']) ?></span></td>
                                <td>
                                    <div style="font-weight:500"><?= sanitize($q['company_name'] ?? $q['contact_name'] ?? '—') ?></div>
                                    <?php if ($q['title']): ?><div style="font-size:11.5px;color:var(--text-muted)"><?= sanitize($q['title']) ?></div><?php endif; ?>
                                </td>
                                <td style="white-space:nowrap"><?= formatDate($q['issue_date']) ?></td>
                                <td style="font-weight:600;white-space:nowrap"><?= formatMoney($q['total'], $q['currency']) ?></td>
                                <td><?= getStatusBadge($q['status']) ?></td>
                                <td><a href="quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary btn-icon"><i class="bi bi-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-file-earmark-text"></i><h5>Henüz teklif yok</h5><p>İlk teklifinizi oluşturun</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Expiring Quotes + Quick Actions -->
    <div class="col-lg-4">
        <?php if ($expiring): ?>
        <div class="card mb-3">
            <div class="card-header">
                <span><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Süresi Yaklaşan</span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($expiring as $eq): ?>
                <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                    <div class="flex-1">
                        <div style="font-weight:600;font-size:13px"><?= sanitize($eq['quote_number']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= sanitize($eq['company_name'] ?? '—') ?></div>
                    </div>
                    <span class="badge badge-warning"><?= formatDate($eq['valid_until']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><span><i class="bi bi-lightning-fill text-accent me-2" style="color:var(--accent)"></i>Hızlı İşlemler</span></div>
            <div class="card-body d-grid gap-2">
                <a href="quotes/create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Yeni Teklif Oluştur</a>
                <a href="customers/add.php" class="btn btn-secondary"><i class="bi bi-person-plus me-2"></i>Müşteri Ekle</a>
                <a href="products/add.php" class="btn btn-secondary"><i class="bi bi-box-seam me-2"></i>Ürün/Hizmet Ekle</a>
                <a href="templates/add.php" class="btn btn-secondary"><i class="bi bi-layout-text-window me-2"></i>Şablon Oluştur</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
const statusMap = <?= json_encode($statusMap) ?>;

// Monthly chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.month),
        datasets: [{
            label: 'Kabul Edilen Tutar',
            data: chartData.map(d => parseFloat(d.total)),
            backgroundColor: 'rgba(0,102,204,.2)',
            borderColor: '#0066cc',
            borderWidth: 2,
            borderRadius: 6,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '₺' + v.toLocaleString('tr-TR')
                },
                grid: { color: 'rgba(0,0,0,.05)' }
            },
            x: { grid: { display: false } }
        }
    }
});

// Status chart
const statusColors = { draft:'#8a9ab0', sent:'#3b82f6', accepted:'#10b981', rejected:'#ef4444', expired:'#f59e0b' };
const statusLabels = { draft:'Taslak', sent:'Gönderildi', accepted:'Kabul', rejected:'Reddedildi', expired:'Süresi Doldu' };
const statusKeys = Object.keys(statusMap).filter(k => statusMap[k] > 0);
if (statusKeys.length > 0) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusKeys.map(k => statusLabels[k]),
            datasets: [{ data: statusKeys.map(k => statusMap[k]), backgroundColor: statusKeys.map(k => statusColors[k]), borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            cutout: '65%'
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
