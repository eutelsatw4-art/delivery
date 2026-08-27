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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $transporterName = trim($_POST['transporter_name'] ?? '');
    $driverName = trim($_POST['driver_name'] ?? '');
    $driverPhone = trim($_POST['driver_phone'] ?? '');
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $orderId = trim($_POST['order_id'] ?? '');
    
    $productNames = $_POST['product_name'] ?? [];
    $productSkus = $_POST['product_sku'] ?? [];
    $quantitiesOrdered = $_POST['quantity_ordered'] ?? [];
    $quantitiesShipped = $_POST['quantity_shipped'] ?? [];

    if (empty($customerName) || empty($shippingAddress)) {
        $error = 'Please fill in all required fields (Customer Name, Address).';
    } elseif (empty($productNames) || empty($productSkus)) {
        $error = 'Please add at least one item.';
    } else {
        try {
            if (empty($orderId)) {
                $prefix = 'ORD-' . date('my');
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM delivery_notes WHERE order_id LIKE ?');
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn();
                $orderId = $prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            }
            
            $noteNumber = 'DN-' . date('Y') . '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare('INSERT INTO delivery_notes 
                (note_number, order_id, customer_name, shipping_address, transporter_name, driver_name, driver_phone, vehicle_number, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $noteNumber, $orderId, $customerName, $shippingAddress,
                $transporterName, $driverName, $driverPhone, $vehicleNumber
            ]);
            $deliveryNoteId = $pdo->lastInsertId();

            foreach ($productNames as $index => $pName) {
                $pName = trim($pName);
                $pSku = trim($productSkus[$index]);
                $qOrdered = intval($quantitiesOrdered[$index] ?? 0);
                $qShipped = intval($quantitiesShipped[$index] ?? 0);

                if ($pName !== '' || $pSku !== '') {
                    $stmt = $pdo->prepare('INSERT INTO delivery_note_items 
                        (delivery_note_id, product_name, product_sku, quantity_ordered, quantity_shipped) 
                        VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$deliveryNoteId, $pName, $pSku, $qOrdered, $qShipped]);
                }
            }

            $_SESSION['success_note'] = $noteNumber;
            header('Location: print-waybill.php?note=' . urlencode($noteNumber));
            exit;
        } catch (\PDOException $e) {
            $error = 'Error saving delivery note: ' . $e->getMessage();
        }
    }
}

if (isset($_SESSION['success_note'])) {
    $success = 'Delivery note created successfully!';
    unset($_SESSION['success_note']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Delivery Note</title>
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
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #1a73e8;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .card h2 {
            margin: 0 0 20px;
            font-size: 18px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a73e8;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #444;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26,115,232,0.1);
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .items-header {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: start;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #1a73e8;
            color: #fff;
        }
        .btn-primary:hover {
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
        .btn-danger {
            background: #dc3545;
            color: #fff;
            padding: 10px 14px;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
            color: #fff;
        }
        .btn-success:hover {
            background: #218838;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .items-header, .item-row { grid-template-columns: 1fr 1fr; }
            .items-header .col-qty, .item-row .col-qty { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Delivery Note</h1>
        <p style="text-align: center; margin-top: -20px; margin-bottom: 30px;">
            <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 13px;">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="card">
                <h2>Delivery Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="order_id">Order ID</label>
                        <input type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($_POST['order_id'] ?? '') ?>" placeholder="Auto-generated: ORD-MMYY-XXXX">
                    </div>
                    <div class="form-group">
                        <label for="customer_name">Customer Name *</label>
                        <input type="text" id="customer_name" name="customer_name" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="shipping_address">Shipping Address *</label>
                        <textarea id="shipping_address" name="shipping_address" required><?= htmlspecialchars($_POST['shipping_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Logistics & Transport</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="transporter_name">Transporter Name</label>
                        <input type="text" id="transporter_name" name="transporter_name" value="<?= htmlspecialchars($_POST['transporter_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="driver_name">Driver Name</label>
                        <input type="text" id="driver_name" name="driver_name" value="<?= htmlspecialchars($_POST['driver_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="driver_phone">Driver Phone</label>
                        <input type="text" id="driver_phone" name="driver_phone" value="<?= htmlspecialchars($_POST['driver_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="vehicle_number">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" value="<?= htmlspecialchars($_POST['vehicle_number'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Items</h2>
                <div class="items-header">
                    <div>Item Description</div>
                    <div>Model Number</div>
                    <div class="col-qty">Qty Ordered</div>
                    <div class="col-qty">Qty Shipped</div>
                    <div></div>
                </div>
                <div id="items-container">
                    <div class="item-row">
                        <input type="text" name="product_name[]" placeholder="Product name" required>
                        <input type="text" name="product_sku[]" placeholder="Model / SKU" required>
                        <input type="number" name="quantity_ordered[]" placeholder="0" min="0">
                        <input type="number" name="quantity_shipped[]" placeholder="0" min="0">
                        <button type="button" class="btn btn-danger" onclick="removeItem(this)">X</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addItem()" style="margin-top: 10px;">+ Add Another Item</button>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save & Print Waybill</button>
                <button type="reset" class="btn btn-secondary">Clear Form</button>
            </div>
        </form>
    </div>

    <script>
        function addItem() {
            const container = document.getElementById('items-container');
            const row = document.createElement('div');
            row.className = 'item-row';
            row.innerHTML = `
                <input type="text" name="product_name[]" placeholder="Product name" required>
                <input type="text" name="product_sku[]" placeholder="Model / SKU" required>
                <input type="number" name="quantity_ordered[]" placeholder="0" min="0">
                <input type="number" name="quantity_shipped[]" placeholder="0" min="0">
                <button type="button" class="btn btn-danger" onclick="removeItem(this)">X</button>
            `;
            container.appendChild(row);
        }

        function removeItem(btn) {
            const container = document.getElementById('items-container');
            if (container.children.length > 1) {
                btn.closest('.item-row').remove();
            }
        }
    </script>
</body>
</html>
