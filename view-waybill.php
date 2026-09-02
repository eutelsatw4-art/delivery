<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'delivery';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo '<html><body style="font-family: Arial; padding: 40px; text-align: center;"><h1>Database Connection Error</h1><p style="color: red;">Cannot connect to the database. Please ensure the database service is linked and environment variables are set correctly in Dokploy.</p><p style="color: #666; font-size: 12px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
    exit;
}

$noteNumber = $_GET['note'] ?? '';
$noteNumber = preg_replace('/[^A-Za-z0-9\-]/', '', $noteNumber);

if (!$noteNumber) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM delivery_notes WHERE note_number = ?');
$stmt->execute([$noteNumber]);
$deliveryNote = $stmt->fetch();

if (!$deliveryNote) {
    die('Delivery Note not found.');
}

$itemStmt = $pdo->prepare('SELECT * FROM delivery_note_items WHERE delivery_note_id = ?');
$itemStmt->execute([$deliveryNote['id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Delivery Note - <?= htmlspecialchars($deliveryNote['note_number']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #222;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.5;
            background: #f4f6f8;
        }
        .page {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #1a73e8;
            flex-wrap: wrap;
            gap: 15px;
        }
        .brand {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .brand-logo {
            height: 42px;
            width: auto;
            max-width: 120px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .brand h1 {
            margin: 0;
            font-size: 20px;
            color: #111;
            text-transform: uppercase;
        }
        .brand p {
            margin: 3px 0 0;
            color: #666;
            font-size: 12px;
        }
        .doc-title {
            text-align: right;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 22px;
            color: #1a73e8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-title .badge {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 12px;
            background: #e8f0fe;
            color: #1a73e8;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .meta-card {
            background: #f8f9fa;
            border-left: 4px solid #1a73e8;
            padding: 15px 18px;
            border-radius: 6px;
        }
        .meta-card h3 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }
        .meta-card p {
            margin: 3px 0;
            color: #222;
        }
        .meta-card strong {
            color: #111;
        }
        .items-section {
            margin-bottom: 25px;
        }
        .items-section h3 {
            font-size: 14px;
            text-transform: uppercase;
            color: #444;
            margin: 0 0 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table thead th {
            background: #1a73e8;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e8e8e8;
        }
        table tbody tr:nth-child(even) {
            background: #fafafa;
        }
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
            margin-bottom: 25px;
        }
        .sig-box {
            text-align: center;
            padding-top: 10px;
            border-top: 1.5px solid #333;
            font-size: 12px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-height: 60px;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1557b0;
        }
        .btn-secondary {
            background: #fff;
            color: #333;
            border: 1px solid #ccc;
        }
        .btn-secondary:hover {
            background: #f5f5f5;
        }
        .btn-warning {
            background: #ff9800;
        }
        .btn-warning:hover {
            background: #e68900;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .page { box-shadow: none; border-radius: 0; padding: 20px; max-width: 100%; }
            .actions { display: none !important; }
            table thead th { background: #ddd !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 15mm; }
        }
        @media (max-width: 640px) {
            .page { padding: 20px; }
            .meta-grid { grid-template-columns: 1fr; }
            .signatures { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .doc-title { text-align: left; }
        }
        .fab-menu {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        .fab-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1a73e8;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(26,115,232,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .fab-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(26,115,232,0.5);
        }
        .fab-items {
            display: none;
            position: absolute;
            bottom: 70px;
            right: 0;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }
        .fab-items.show {
            display: flex;
        }
        .fab-item {
            padding: 10px 18px;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            text-decoration: none;
            color: #333;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: transform 0.2s;
        }
        .fab-item:hover {
            transform: translateX(-5px);
            background: #f0f4ff;
        }
        .fab-item.view { border-left: 3px solid #1a73e8; }
        .fab-item.print { border-left: 3px solid #28a745; }
        .fab-item.edit { border-left: 3px solid #ff9800; }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-bar">
            <div>
                <div style="text-align: center; margin-bottom: 10px;">
                    <img src="images/logo.png" alt="Logo" class="brand-logo">
                </div>
                <div>
                    <h1>COPPER, DIAMOND, AND GOLD WAREHOUSE</h1>
                    <p>SHAFA, DEI-DEI</p>
                    <p>ABUJA</p>
                </div>
            </div>
            <div class="doc-title">
                <h2>Delivery Note / Waybill</h2>
                <span class="badge"><?= htmlspecialchars($deliveryNote['note_number']) ?></span>
                <p style="margin: 6px 0 0; font-size: 12px; color: #666;">Order: #<?= htmlspecialchars($deliveryNote['order_id']) ?> | Date: <?= htmlspecialchars($deliveryNote['created_at']) ?></p>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-card">
                <h3>Deliver To</h3>
                <p><strong><?= htmlspecialchars($deliveryNote['customer_name']) ?></strong></p>
                <p><?= nl2br(htmlspecialchars($deliveryNote['shipping_address'])) ?></p>
            </div>
            <div class="meta-card">
                <h3>Logistics & Transport</h3>
                <p><strong>Transporter:</strong> <?= htmlspecialchars($deliveryNote['transporter_name'] ?? 'N/A') ?></p>
                <p><strong>Driver:</strong> <?= htmlspecialchars($deliveryNote['driver_name'] ?? 'N/A') ?> (<?= htmlspecialchars($deliveryNote['driver_phone'] ?? 'N/A') ?>)</p>
                <p><strong>Vehicle:</strong> <?= htmlspecialchars($deliveryNote['vehicle_number'] ?? 'N/A') ?></p>
            </div>
        </div>

        <div class="items-section">
            <h3>Itemized List</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">S/N</th>
                        <th>Item Description</th>
                        <th style="width: 22%;">Model Number</th>
                        <th style="width: 15%; text-align: right;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 20px;">No items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= htmlspecialchars($item['product_sku']) ?></td>
                            <td style="text-align: right;"><strong><?= htmlspecialchars($item['quantity_shipped']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="signatures">
            <div class="sig-box">Released by</div>
            <div class="sig-box">Received by</div>
            <div class="sig-box">Security</div>
        </div>

        <div class="actions">
            <a href="print-waybill.php?note=<?= urlencode($deliveryNote['note_number']) ?>" class="btn" target="_blank">Print</a>
            <a href="edit-waybill.php?note=<?= urlencode($deliveryNote['note_number']) ?>" class="btn btn-warning">Edit</a>
            <a href="index.php" class="btn btn-secondary">Back to Records</a>
        </div>
    </div>

    <div class="fab-menu">
        <div class="fab-items" id="fabItems">
            <a href="print-waybill.php?note=<?= urlencode($deliveryNote['note_number']) ?>" class="fab-item print" target="_blank">Print</a>
            <a href="edit-waybill.php?note=<?= urlencode($deliveryNote['note_number']) ?>" class="fab-item edit">Edit</a>
            <a href="index.php" class="fab-item view">All Records</a>
        </div>
        <button class="fab-btn" onclick="toggleFab()">+</button>
    </div>

    <script>
        function toggleFab() {
            document.getElementById('fabItems').classList.toggle('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.fab-menu')) {
                document.getElementById('fabItems').classList.remove('show');
            }
        });
    </script>
</body>
</html>
