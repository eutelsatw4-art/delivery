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

$search = trim($_GET['search'] ?? '');
$notes = [];

if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM delivery_notes 
        WHERE note_number LIKE ? OR order_id LIKE ? OR customer_name LIKE ? 
        ORDER BY created_at DESC LIMIT 100');
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $notes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM delivery_notes ORDER BY created_at DESC LIMIT 100');
    $stmt->execute();
    $notes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Notes - Records</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #222;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
            background: #f4f6f8;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        h1 {
            color: #1a73e8;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 250px;
        }
        .btn {
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
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        table thead th {
            background: #1a73e8;
            color: #fff;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        table tbody td {
            padding: 12px;
            border-bottom: 1px solid #e8e8e8;
        }
        table tbody tr:nth-child(even) {
            background: #fafafa;
        }
        table tbody tr:hover {
            background: #f0f4ff;
        }
        .actions-cell {
            display: flex;
            gap: 8px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .search-box input { flex: 1; }
            table { font-size: 12px; }
            table thead th, table tbody td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h1>Delivery Notes Records</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form class="search-box" method="GET" action="">
                    <input type="text" name="search" placeholder="Search note #, order #, customer..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn">Search</button>
                </form>
                <a href="create-waybill.php" class="btn">+ New Delivery Note</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>

        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <p>No delivery notes found. <?= $search ? 'Try a different search term.' : 'Create your first delivery note to get started.' ?></p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Note Number</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $note): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($note['note_number']) ?></strong></td>
                        <td><?= htmlspecialchars($note['order_id']) ?></td>
                        <td><?= htmlspecialchars($note['customer_name']) ?></td>
                        <td><?= htmlspecialchars($note['created_at']) ?></td>
                        <td class="actions-cell">
                            <a href="view-waybill.php?note=<?= urlencode($note['note_number']) ?>" class="btn btn-sm">View</a>
                            <a href="print-waybill.php?note=<?= urlencode($note['note_number']) ?>" class="btn btn-sm btn-secondary" target="_blank">Print</a>
                            <a href="edit-waybill.php?note=<?= urlencode($note['note_number']) ?>" class="btn btn-sm" style="background: #ff9800;">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="fab-menu">
        <div class="fab-items" id="fabItems">
            <a href="create-waybill.php" class="fab-item view">+ New Note</a>
            <a href="index.php" class="fab-item print">All Records</a>
            <a href="logout.php" class="fab-item edit">Logout</a>
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
