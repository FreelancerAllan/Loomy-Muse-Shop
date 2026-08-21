<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config.php";

$order_id = (int)($_GET["id"] ?? $_POST["order_id"] ?? 0);

if ($order_id <= 0) {
    header("Location: orders.php");
    exit;
}

$error = "";


/*
|--------------------------------------------------------------------------
| UPDATE ORDER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_order"])) {

    $customer_name = trim($_POST["customer_name"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $delivery_location = trim($_POST["delivery_location"] ?? "");
    $status = $_POST["status"] ?? "Pending";

    $items = $_POST["item_name"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $prices = $_POST["price"] ?? [];

    $allowed_statuses = [
        "Pending",
        "Paid",
        "Delivered",
        "Cancelled"
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        $status = "Pending";
    }

    if (
        empty($customer_name) ||
        empty($phone_number) ||
        empty($delivery_location)
    ) {

        $error = "Please fill in all customer details.";

    } elseif (empty($items)) {

        $error = "Please add at least one item.";

    } else {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | UPDATE ORDER
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE orders
                SET
                    customer_name = ?,
                    phone_number = ?,
                    delivery_location = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $customer_name,
                $phone_number,
                $delivery_location,
                $status,
                $order_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | DELETE OLD ITEMS
            |--------------------------------------------------------------------------
            */

            $delete_items = $pdo->prepare("
                DELETE FROM order_items
                WHERE order_id = ?
            ");

            $delete_items->execute([
                $order_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | INSERT UPDATED ITEMS
            |--------------------------------------------------------------------------
            */

            $item_stmt = $pdo->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    item_name,
                    quantity,
                    price
                )
                VALUES (?, ?, ?, ?)
            ");


            foreach ($items as $index => $item_name) {

                $item_name = trim($item_name);

                $quantity = isset($quantities[$index])
                    ? (int)$quantities[$index]
                    : 1;

                $price = isset($prices[$index])
                    ? (float)$prices[$index]
                    : 0;


                if (!empty($item_name)) {

                    $item_stmt->execute([
                        $order_id,
                        $item_name,
                        max(1, $quantity),
                        $price
                    ]);

                }

            }


            $pdo->commit();


            header(
                "Location: orders.php?success=updated"
            );

            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                "Unable to update the order. Please try again.";
        }
    }
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

$stmt->execute([
    $order_id
]);

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
        id,
        item_name,
        quantity,
        price
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");

$item_stmt->execute([
    $order_id
]);

$items = $item_stmt->fetchAll();

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
        Edit Order #<?= $order["id"] ?>
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        :root {
            --primary-color: #3F6656;
            --primary-dark: #315044;
            --light-bg: #f5f7f6;
        }

        body {
            background: var(--light-bg);
            font-family: Arial, sans-serif;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: var(--primary-color);
            color: white;
            z-index: 1000;
        }

        .sidebar-logo {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }

        .sidebar-logo img {
            max-width: 150px;
            max-height: 55px;
            object-fit: contain;
        }

        .sidebar-menu {
            padding: 20px 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255,255,255,.85);
            text-decoration: none;
            padding: 13px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,.15);
            color: white;
        }

        .logout-link {
            position: absolute;
            bottom: 20px;
            left: 15px;
            right: 15px;
        }

        /* MAIN */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        .topbar {
            height: 80px;
            background: white;
            border-bottom: 1px solid #e5e7e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .page-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            padding: 30px;
        }

        /* CARD */

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,.05);
        }

        .section-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            min-height: 45px;
        }

        .item-row {
            background: #f8faf9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* MOBILE */

        .mobile-header {
            display: none;
            height: 65px;
            background: var(--primary-color);
            color: white;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }

        .menu-button {
            border: none;
            background: transparent;
            color: white;
            font-size: 25px;
        }

        .sidebar-overlay {
            display: none;
        }

        @media (max-width: 991px) {

            .sidebar {
                left: -250px;
                transition: .3s;
            }

            .sidebar.show {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-header {
                display: flex;
            }

            .sidebar-overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.4);
                z-index: 999;
            }

        }

        @media (max-width: 576px) {

            .content {
                padding: 20px 15px;
            }

            .topbar {
                padding: 0 15px;
            }

            .admin-info span {
                display: none;
            }

            .form-card {
                padding: 20px 15px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-logo">

        <img
            src="../images/logo.jpg"
            alt="Logo"
        >

    </div>


    <nav class="sidebar-menu">

        <a href="dashboard.php">

            <i class="bi bi-house-door"></i>

            Home

        </a>


        <a href="orders.php" class="active">

            <i class="bi bi-cart3"></i>

            Orders

        </a>


        <a href="products.php">

            <i class="bi bi-box-seam"></i>

            Products

        </a>


        <a
            href="logout.php"
            class="logout-link"
        >

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>

    </nav>

</aside>


<!-- MOBILE -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<div class="mobile-header">

    <button
        class="menu-button"
        id="menuButton"
    >

        <i class="bi bi-list"></i>

    </button>

    <strong>
        Admin Panel
    </strong>

    <div></div>

</div>


<!-- MAIN -->

<div class="main-content">


    <!-- TOPBAR -->

    <header class="topbar">

        <h1 class="page-title">

            Edit Order #<?= $order["id"] ?>

        </h1>


        <div class="admin-info">

            <div class="admin-avatar">

                <i class="bi bi-person"></i>

            </div>

            <span>

                <?= htmlspecialchars(
                    $_SESSION["admin_email"]
                ) ?>

            </span>

        </div>

    </header>


    <!-- CONTENT -->

    <main class="content">


        <div class="mb-4">

            <a
                href="orders.php"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Back to Orders

            </a>

        </div>


        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-circle me-2"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="form-card">


            <form method="POST">

                <input
                    type="hidden"
                    name="order_id"
                    value="<?= $order["id"] ?>"
                >


                <!-- CUSTOMER DETAILS -->

                <div class="section-title">

                    <i class="bi bi-person me-2"></i>

                    Customer Details

                </div>


                <div class="row g-3 mb-5">


                    <div class="col-md-6">

                        <label class="form-label">
                            Customer Name
                        </label>

                        <input
                            type="text"
                            name="customer_name"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $order["customer_name"]
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            name="phone_number"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $order["phone_number"]
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-8">

                        <label class="form-label">
                            Delivery Location
                        </label>

                        <textarea
                            name="delivery_location"
                            class="form-control"
                            rows="3"
                            required
                        ><?= htmlspecialchars(
                            $order["delivery_location"]
                        ) ?></textarea>

                    </div>


                    <div class="col-md-4">

                        <label class="form-label">
                            Order Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option
                                value="Pending"
                                <?= $order["status"] === "Pending"
                                    ? "selected"
                                    : "" ?>
                            >
                                Pending
                            </option>

                            <option
                                value="Paid"
                                <?= $order["status"] === "Paid"
                                    ? "selected"
                                    : "" ?>
                            >
                                Paid
                            </option>

                            <option
                                value="Delivered"
                                <?= $order["status"] === "Delivered"
                                    ? "selected"
                                    : "" ?>
                            >
                                Delivered
                            </option>

                            <option
                                value="Cancelled"
                                <?= $order["status"] === "Cancelled"
                                    ? "selected"
                                    : "" ?>
                            >
                                Cancelled
                            </option>

                        </select>

                    </div>

                </div>


                <!-- ORDER ITEMS -->

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div class="section-title mb-0">

                        <i class="bi bi-box-seam me-2"></i>

                        Items Ordered

                    </div>


                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        id="addItemButton"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Add Item

                    </button>

                </div>


                <div id="itemsContainer">


                    <?php if (count($items) > 0): ?>


                        <?php foreach ($items as $item): ?>

                            <div class="item-row">

                                <div class="row g-2 align-items-end">


                                    <div class="col-md-5">

                                        <label class="form-label">
                                            Item
                                        </label>

                                        <input
                                            type="text"
                                            name="item_name[]"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                $item["item_name"]
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-3">

                                        <label class="form-label">
                                            Quantity
                                        </label>

                                        <input
                                            type="number"
                                            name="quantity[]"
                                            class="form-control"
                                            min="1"
                                            value="<?= $item["quantity"] ?>"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-3">

                                        <label class="form-label">
                                            Price
                                        </label>

                                        <input
                                            type="number"
                                            name="price[]"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="<?= $item["price"] ?>"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-1">

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger remove-item"
                                        >

                                            <i class="bi bi-trash"></i>

                                        </button>

                                    </div>


                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="item-row">

                            <div class="row g-2 align-items-end">

                                <div class="col-md-5">

                                    <label class="form-label">
                                        Item
                                    </label>

                                    <input
                                        type="text"
                                        name="item_name[]"
                                        class="form-control"
                                        required
                                    >

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        Quantity
                                    </label>

                                    <input
                                        type="number"
                                        name="quantity[]"
                                        class="form-control"
                                        min="1"
                                        value="1"
                                        required
                                    >

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        Price
                                    </label>

                                    <input
                                        type="number"
                                        name="price[]"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        required
                                    >

                                </div>


                                <div class="col-md-1">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger remove-item"
                                    >

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </div>

                            </div>

                        </div>


                    <?php endif; ?>


                </div>


                <!-- BUTTONS -->

                <div class="d-flex gap-2 mt-4 pt-4 border-top">

                    <a
                        href="orders.php"
                        class="btn btn-secondary"
                    >

                        Cancel

                    </a>


                    <button
                        type="submit"
                        name="update_order"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-check-lg me-1"></i>

                        Save Changes

                    </button>


                    <a
                        href="invoice.php?id=<?= $order["id"] ?>"
                        target="_blank"
                        class="btn btn-outline-success ms-auto"
                    >

                        <i class="bi bi-receipt me-1"></i>

                        View Invoice

                    </a>

                </div>


            </form>

        </div>

    </main>

</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | MOBILE SIDEBAR
    |--------------------------------------------------------------------------
    */

    const sidebar =
        document.getElementById("sidebar");

    const menuButton =
        document.getElementById("menuButton");

    const overlay =
        document.getElementById("sidebarOverlay");


    menuButton.addEventListener("click", function () {

        sidebar.classList.toggle("show");

        overlay.classList.toggle("show");

    });


    overlay.addEventListener("click", function () {

        sidebar.classList.remove("show");

        overlay.classList.remove("show");

    });


    /*
    |--------------------------------------------------------------------------
    | ADD ITEMS
    |--------------------------------------------------------------------------
    */

    const addItemButton =
        document.getElementById("addItemButton");

    const itemsContainer =
        document.getElementById("itemsContainer");


    addItemButton.addEventListener("click", function () {

        const itemRow =
            document.createElement("div");

        itemRow.className = "item-row";


        itemRow.innerHTML = `

            <div class="row g-2 align-items-end">

                <div class="col-md-5">

                    <label class="form-label">
                        Item
                    </label>

                    <input
                        type="text"
                        name="item_name[]"
                        class="form-control"
                        placeholder="Item name"
                        required
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        Quantity
                    </label>

                    <input
                        type="number"
                        name="quantity[]"
                        class="form-control"
                        min="1"
                        value="1"
                        required
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        Price
                    </label>

                    <input
                        type="number"
                        name="price[]"
                        class="form-control"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        required
                    >

                </div>


                <div class="col-md-1">

                    <button
                        type="button"
                        class="btn btn-outline-danger remove-item"
                    >

                        <i class="bi bi-trash"></i>

                    </button>

                </div>

            </div>

        `;


        itemsContainer.appendChild(itemRow);

    });


    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEMS
    |--------------------------------------------------------------------------
    */

    itemsContainer.addEventListener(
        "click",
        function (event) {

            const button =
                event.target.closest(".remove-item");


            if (!button) {
                return;
            }


            const rows =
                itemsContainer.querySelectorAll(
                    ".item-row"
                );


            // Keep at least one item

            if (rows.length > 1) {

                button
                    .closest(".item-row")
                    .remove();

            }

        }
    );

</script>

</body>
</html>

