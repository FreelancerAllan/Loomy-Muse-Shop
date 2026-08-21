
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config.php";

$order_id = (int)($_GET["id"] ?? 0);

if ($order_id <= 0) {
    header("Location: orders.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET ORDER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        customer_name,
        phone_number,
        delivery_location,
        status,
        created_at
    FROM orders
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$order_id]);

$order = $stmt->fetch();

if (!$order) {
    header("Location: orders.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET ORDER ITEMS
|--------------------------------------------------------------------------
*/

$item_stmt = $pdo->prepare("
    SELECT
        item_name,
        quantity,
        price
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");

$item_stmt->execute([$order_id]);

$items = $item_stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($items as $item) {
    $subtotal += $item["quantity"] * $item["price"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Invoice #<?= $order["id"] ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary-color: #3F6656;
        }

        body {
            background: #f3f5f4;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .invoice-wrapper {
            max-width: 900px;
            margin: 40px auto;
        }

        .invoice {
            background: #fff;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,.07);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--primary-color);
        }

        .logo {
            max-width: 150px;
            max-height: 70px;
            object-fit: contain;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            color: var(--primary-color);
            font-size: 34px;
            font-weight: 700;
            margin: 0;
        }

        .invoice-number {
            color: #777;
            margin-top: 5px;
        }

        .customer-section {
            margin-top: 35px;
            margin-bottom: 35px;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .customer-name {
            font-size: 18px;
            font-weight: 600;
        }

        .customer-info {
            color: #666;
            line-height: 1.7;
        }

        .invoice-table th {
            background: var(--primary-color);
            color: white;
            padding: 13px;
            border: none;
        }

        .invoice-table td {
            padding: 13px;
            vertical-align: middle;
        }

        .total-section {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
        }

        .total-box {
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: #555;
        }

        .grand-total {
            border-top: 2px solid var(--primary-color);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-delivered {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .invoice-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        .action-buttons {
            max-width: 900px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: #315044;
            border-color: #315044;
        }

        @media (max-width: 576px) {

            .invoice-wrapper {
                margin: 0;
            }

            .invoice {
                padding: 25px 18px;
                border-radius: 0;
            }

            .invoice-header {
                flex-direction: column;
                gap: 20px;
            }

            .invoice-title {
                text-align: left;
            }

            .invoice-title h1 {
                font-size: 28px;
            }

            .total-box {
                width: 100%;
            }

        }

        @media print {

            body {
                background: white;
            }

            .invoice-wrapper {
                margin: 0;
                max-width: none;
            }

            .invoice {
                box-shadow: none;
                padding: 20px;
            }

            .action-buttons {
                display: none !important;
            }

            .invoice-footer {
                margin-top: 30px;
            }

        }

    </style>

</head>

<body>


<div class="action-buttons">

    <a
        href="orders.php"
        class="btn btn-outline-secondary"
    >
        ← Back to Orders
    </a>

    <button
        onclick="window.print()"
        class="btn btn-primary"
    >
        🖨 Print Invoice
    </button>

</div>


<div class="invoice-wrapper">

    <div class="invoice">


        <!-- HEADER -->

        <div class="invoice-header">

            <div>

                <!-- Replace ../images/logo.jpg with your logo -->

                <img
                    src="../images/logo.jpg"
                    alt="Logo"
                    class="logo"
                >

            </div>


            <div class="invoice-title">

                <h1>INVOICE</h1>

                <div class="invoice-number">

                    Invoice #<?= $order["id"] ?>

                </div>

                <div class="invoice-number">

                    <?= date(
                        "d M Y",
                        strtotime($order["created_at"])
                    ) ?>

                </div>

            </div>

        </div>


        <!-- CUSTOMER -->

        <div class="customer-section">

            <div class="row">

                <div class="col-md-6 mb-4 mb-md-0">

                    <div class="section-title">
                        Bill To
                    </div>

                    <div class="customer-name">

                        <?= htmlspecialchars(
                            $order["customer_name"]
                        ) ?>

                    </div>

                    <div class="customer-info">

                        Phone:
                        <?= htmlspecialchars(
                            $order["phone_number"]
                        ) ?>

                        <br>

                        Delivery Location:

                        <?= nl2br(
                            htmlspecialchars(
                                $order["delivery_location"]
                            )
                        ) ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="section-title">
                        Order Status
                    </div>

                    <?php

                    $status_class = "status-pending";

                    if ($order["status"] === "Paid") {
                        $status_class = "status-paid";
                    }

                    if ($order["status"] === "Delivered") {
                        $status_class = "status-delivered";
                    }

                    if ($order["status"] === "Cancelled") {
                        $status_class = "status-cancelled";
                    }

                    ?>

                    <span
                        class="status-badge <?= $status_class ?>"
                    >

                        <?= htmlspecialchars(
                            $order["status"]
                        ) ?>

                    </span>

                </div>

            </div>

        </div>


        <!-- ITEMS -->

        <div class="table-responsive">

            <table class="table invoice-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Item
                        </th>

                        <th class="text-center">
                            Quantity
                        </th>

                        <th class="text-end">
                            Unit Price
                        </th>

                        <th class="text-end">
                            Total
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($items as $index => $item): ?>

                    <?php
                    $item_total =
                        $item["quantity"] * $item["price"];
                    ?>

                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $item["item_name"]
                            ) ?>
                        </td>

                        <td class="text-center">
                            <?= $item["quantity"] ?>
                        </td>

                        <td class="text-end">
                            <?= number_format(
                                $item["price"],
                                2
                            ) ?>
                        </td>

                        <td class="text-end">
                            <?= number_format(
                                $item_total,
                                2
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- TOTAL -->

        <div class="total-section">

            <div class="total-box">

                <div class="total-row">

                    <span>
                        Subtotal
                    </span>

                    <span>
                        <?= number_format(
                            $subtotal,
                            2
                        ) ?>
                    </span>

                </div>


                <div class="total-row grand-total">

                    <span>
                        TOTAL
                    </span>

                    <span>
                        <?= number_format(
                            $subtotal,
                            2
                        ) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="invoice-footer">

            <strong>
                Thank you for your order!
            </strong>

            <br>

            This invoice was generated from Loomy Muse Shop.

        </div>

    </div>

</div>

</body>
</html>

