<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";

$message = "";
$error = "";


/*
|--------------------------------------------------------------------------
| ADD ORDER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_order"])) {

    $customer_name = trim($_POST["customer_name"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $delivery_location = trim($_POST["delivery_location"] ?? "");

    $items = $_POST["item_name"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $prices = $_POST["price"] ?? [];

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
            | INSERT ORDER
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO orders
                (
                    customer_name,
                    phone_number,
                    delivery_location
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $customer_name,
                $phone_number,
                $delivery_location
            ]);

            $order_id = $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER ITEMS
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


            $valid_items = 0;


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
                        max(0, $price)
                    ]);

                    $valid_items++;
                }
            }


            if ($valid_items === 0) {

                throw new Exception(
                    "Please add at least one valid item."
                );
            }


            $pdo->commit();


            header("Location: orders.php?success=1");
            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to create the order. Please try again.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE ORDER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_order"])
) {

    $order_id = (int)($_POST["order_id"] ?? 0);

    if ($order_id > 0) {

        try {

            $pdo->beginTransaction();


            /*
            | Delete items first
            */

            $stmt = $pdo->prepare("
                DELETE FROM order_items
                WHERE order_id = ?
            ");

            $stmt->execute([
                $order_id
            ]);


            /*
            | Delete order
            */

            $stmt = $pdo->prepare("
                DELETE FROM orders
                WHERE id = ?
            ");

            $stmt->execute([
                $order_id
            ]);


            $pdo->commit();


            header("Location: orders.php?deleted=1");
            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to delete the order.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS / ERROR MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {

    $message = "Order added successfully.";

}

if (isset($_GET["deleted"])) {

    $message = "Order deleted successfully.";

}

if (isset($_GET["updated"])) {

    $message = "Order updated successfully.";

}


/*
|--------------------------------------------------------------------------
| FETCH ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        o.id,
        o.customer_name,
        o.phone_number,
        o.delivery_location,
        o.status,
        o.created_at,

        COALESCE(
            SUM(oi.quantity * oi.price),
            0
        ) AS total_amount

    FROM orders o

    LEFT JOIN order_items oi
        ON o.id = oi.order_id

    GROUP BY
        o.id,
        o.customer_name,
        o.phone_number,
        o.delivery_location,
        o.status,
        o.created_at

    ORDER BY o.id DESC
");

$orders = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FETCH ALL ORDER ITEMS
|--------------------------------------------------------------------------
|
| Instead of querying the database once for every order,
| we load all items in one query.
|
|--------------------------------------------------------------------------
*/

$item_stmt = $pdo->query("
    SELECT
        order_id,
        item_name,
        quantity

    FROM order_items

    ORDER BY id ASC
");

$all_order_items = $item_stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| ORGANIZE ITEMS BY ORDER ID
|--------------------------------------------------------------------------
*/

$order_items = [];

foreach ($all_order_items as $item) {

    $order_id = $item["order_id"];

    if (!isset($order_items[$order_id])) {

        $order_items[$order_id] = [];

    }

    $order_items[$order_id][] = $item;
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
        Orders - Admin Dashboard
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


        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: var(--light-bg);

            font-family: Arial, sans-serif;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {

            position: fixed;

            top: 0;

            left: 0;

            width: 250px;

            height: 100vh;

            background: var(--primary-color);

            color: white;

            z-index: 1000;

            transition: 0.3s;

        }


        .sidebar-logo {

            height: 80px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-bottom:
                1px solid
                rgba(255,255,255,.15);

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

            transition: .2s;

        }


        .sidebar-menu a:hover,

        .sidebar-menu a.active {

            background:
                rgba(255,255,255,.15);

            color: white;

        }


        .sidebar-menu i {

            font-size: 19px;

        }


        .logout-link {

            position: absolute;

            bottom: 20px;

            left: 15px;

            right: 15px;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN CONTENT
        |--------------------------------------------------------------------------
        */

        .main-content {

            margin-left: 250px;

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 80px;

            background: white;

            border-bottom:
                1px solid
                #e5e7e6;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

        }


        .page-title {

            margin: 0;

            font-size: 24px;

            font-weight: 600;

            color: #27332d;

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


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {

            padding: 30px;

        }


        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

        }


        .page-header h4 {

            margin: 0;

            font-weight: 600;

        }


        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .btn-primary {

            background:
                var(--primary-color);

            border-color:
                var(--primary-color);

        }


        .btn-primary:hover {

            background:
                var(--primary-dark);

            border-color:
                var(--primary-dark);

        }


        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .table-card {

            background: white;

            border-radius: 15px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,.05);

            overflow: hidden;

        }


        .table {

            margin-bottom: 0;

        }


        .table thead th {

            background: #f8faf9;

            color: #46544d;

            font-size: 14px;

            padding: 16px;

            white-space: nowrap;

        }


        .table tbody td {

            padding: 16px;

            vertical-align: middle;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status {

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

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


        /*
        |--------------------------------------------------------------------------
        | MOBILE HEADER
        |--------------------------------------------------------------------------
        */

        .mobile-header {

            display: none;

            height: 65px;

            background:
                var(--primary-color);

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


        /*
        |--------------------------------------------------------------------------
        | ADD ORDER MODAL
        |--------------------------------------------------------------------------
        */

        .modal-header {

            background:
                var(--primary-color);

            color: white;

            padding: 14px 20px;

        }


        .modal-header .btn-close {

            filter:
                brightness(0)
                invert(1);

        }


        .modal-content {

            border: none;

            border-radius: 14px;

            overflow: hidden;

        }


        /*
        |--------------------------------------------------------------------------
        | MODAL BODY
        |--------------------------------------------------------------------------
        |
        | This is the important fix.
        |
        | At 100% Chrome zoom, the modal body gets its own
        | scroll area instead of pushing the footer off-screen.
        |
        */

        .modal-body {

            padding: 20px;

            max-height:
                calc(100vh - 180px);

            overflow-y: auto;

        }


        /*
        |--------------------------------------------------------------------------
        | MODAL FOOTER
        |--------------------------------------------------------------------------
        */

        .modal-footer {

            background: white;

            border-top:
                1px solid
                #e5e7e6;

            padding: 12px 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM INPUTS
        |--------------------------------------------------------------------------
        */

        .form-control,

        .form-select {

            border-radius: 8px;

        }


        .modal-body .form-label {

            font-size: 14px;

            margin-bottom: 5px;

        }


        .modal-body .form-control {

            min-height: 40px;

        }


        /*
        |--------------------------------------------------------------------------
        | ITEM ROW
        |--------------------------------------------------------------------------
        */

        .item-row {

            background: #f8faf9;

            padding: 12px;

            border-radius: 10px;

            margin-bottom: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | MODAL SCROLLBAR
        |--------------------------------------------------------------------------
        */

        .modal-body::-webkit-scrollbar {

            width: 7px;

        }


        .modal-body::-webkit-scrollbar-track {

            background: #f1f3f2;

        }


        .modal-body::-webkit-scrollbar-thumb {

            background: #b7c4be;

            border-radius: 10px;

        }


        .modal-body::-webkit-scrollbar-thumb:hover {

            background:
                var(--primary-color);

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 991px) {

            .sidebar {

                left: -250px;

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

                background:
                    rgba(0,0,0,.4);

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


            .page-title {

                font-size: 20px;

            }


            .admin-info span {

                display: none;

            }


            .page-header {

                align-items: flex-start;

                gap: 15px;

                flex-direction: column;

            }


            .page-header .btn {

                width: 100%;

            }


            /*
            |--------------------------------------------------------------------------
            | MOBILE MODAL
            |--------------------------------------------------------------------------
            */

            .modal-dialog {

                margin: 10px;

            }


            .modal-body {

                padding: 15px;

                max-height:
                    calc(100vh - 140px);

            }


            .modal-footer {

                padding: 10px 15px;

            }


            .modal-footer .btn {

                flex: 1;

            }

        }

    </style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
-->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-logo">

        <img
            src="../images/logo.jpg"
            alt="Logo"
        >

    </div>


    <nav class="sidebar-menu">

        <a
            href="dashboard.php"
        >

            <i class="bi bi-house-door"></i>

            <span>
                Home
            </span>

        </a>


        <a
            href="orders.php"
            class="active"
        >

            <i class="bi bi-cart3"></i>

            <span>
                Orders
            </span>

        </a>


        <a
            href="products.php"
        >

            <i class="bi bi-box-seam"></i>

            <span>
                Products
            </span>

        </a>


        <a
            href="logout.php"
            class="logout-link"
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </nav>

</aside>


<!--
|--------------------------------------------------------------------------
| MOBILE HEADER
|--------------------------------------------------------------------------
-->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<div class="mobile-header">

    <button
        class="menu-button"
        id="menuButton"
        type="button"
    >

        <i class="bi bi-list"></i>

    </button>


    <strong>
        Admin Panel
    </strong>


    <div></div>

</div>


<!--
|--------------------------------------------------------------------------
| MAIN CONTENT
|--------------------------------------------------------------------------
-->

<div class="main-content">


    <!-- TOPBAR -->

    <header class="topbar">

        <h1 class="page-title">

            Orders

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


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h4>
                    Manage Orders
                </h4>


                <small class="text-muted">

                    Create and manage customer orders

                </small>

            </div>


            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addOrderModal"
            >

                <i class="bi bi-plus-lg"></i>

                Add Order

            </button>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        -->

        <?php if (!empty($message)): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >

                <i
                    class="bi bi-check-circle me-2"
                ></i>


                <?= htmlspecialchars($message) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!--
        |--------------------------------------------------------------------------
        | ERROR MESSAGE
        |--------------------------------------------------------------------------
        -->

        <?php if (!empty($error)): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >

                <i
                    class="bi bi-exclamation-circle me-2"
                ></i>


                <?= htmlspecialchars($error) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!--
        |--------------------------------------------------------------------------
        | ORDERS TABLE
        |--------------------------------------------------------------------------
        -->

        <div class="table-card">

            <div class="table-responsive">

                <table class="table">

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>


                            <th>
                                Customer
                            </th>


                            <th>
                                Phone
                            </th>


                            <th>
                                Delivery Location
                            </th>


                            <th>
                                Items
                            </th>


                            <th>
                                Total
                            </th>


                            <th>
                                Status
                            </th>


                            <th>
                                Date
                            </th>


                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (count($orders) > 0): ?>


                        <?php foreach ($orders as $order): ?>


                            <tr>


                                <td>

                                    #<?= $order["id"] ?>

                                </td>


                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $order["customer_name"]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $order["phone_number"]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $order["delivery_location"]
                                    ) ?>

                                </td>


                                <!-- ITEMS -->

                                <td>

                                    <?php

                                    $current_items =
                                        $order_items[$order["id"]]
                                        ?? [];

                                    ?>


                                    <?php if (
                                        count($current_items) > 0
                                    ): ?>


                                        <?php foreach (
                                            $current_items
                                            as $item
                                        ): ?>

                                            <div class="small mb-1">

                                                <?= htmlspecialchars(
                                                    $item["item_name"]
                                                ) ?>


                                                <span
                                                    class="text-muted"
                                                >

                                                    x<?= $item["quantity"] ?>

                                                </span>

                                            </div>

                                        <?php endforeach; ?>


                                    <?php else: ?>


                                        <span class="text-muted">

                                            No items

                                        </span>


                                    <?php endif; ?>

                                </td>


                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        <?= number_format(
                                            $order["total_amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $status_class =
                                        "status-pending";


                                    if (
                                        $order["status"]
                                        === "Paid"
                                    ) {

                                        $status_class =
                                            "status-paid";

                                    }


                                    if (
                                        $order["status"]
                                        === "Delivered"
                                    ) {

                                        $status_class =
                                            "status-delivered";

                                    }


                                    if (
                                        $order["status"]
                                        === "Cancelled"
                                    ) {

                                        $status_class =
                                            "status-cancelled";

                                    }

                                    ?>


                                    <span
                                        class="status
                                        <?= $status_class ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $order["status"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $order["created_at"]
                                        )
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="d-flex gap-2"
                                    >


                                        <!-- INVOICE -->

                                        <a
                                            href="invoice.php?id=<?= $order["id"] ?>"
                                            class="btn btn-sm btn-outline-success"
                                            title="Generate Invoice"
                                        >

                                            <i
                                                class="bi bi-receipt"
                                            ></i>

                                        </a>


                                        <!-- EDIT -->

                                        <a
                                            href="edit_order.php?id=<?= $order["id"] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit Order"
                                        >

                                            <i
                                                class="bi bi-pencil"
                                            ></i>

                                        </a>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this order?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= $order["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="delete_order"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete Order"
                                            >

                                                <i
                                                    class="bi bi-trash"
                                                ></i>

                                            </button>

                                        </form>

                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="9"
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-cart-x fs-1 text-muted"
                                ></i>


                                <p
                                    class="mt-3 mb-0 text-muted"
                                >

                                    No orders found.

                                </p>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>


<!--
|--------------------------------------------------------------------------
| ADD ORDER MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="addOrderModal"
    tabindex="-1"
    aria-labelledby="addOrderModalLabel"
    aria-hidden="true"
>


    <div
        class="modal-dialog modal-lg modal-dialog-centered"
    >


        <div class="modal-content">


            <!-- MODAL HEADER -->

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="addOrderModalLabel"
                >

                    <i
                        class="bi bi-cart-plus me-2"
                    ></i>

                    Add New Order

                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <!-- FORM -->

            <form
                method="POST"
                id="addOrderForm"
            >


                <!-- MODAL BODY -->

                <div class="modal-body">


                    <!-- CUSTOMER DETAILS -->

                    <h6 class="mb-2">

                        Customer Details

                    </h6>


                    <div class="row g-2 mb-3">


                        <!-- NAME -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Customer Name

                            </label>


                            <input
                                type="text"
                                name="customer_name"
                                class="form-control"
                                placeholder="Enter customer name"
                                required
                            >

                        </div>


                        <!-- PHONE -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Phone Number

                            </label>


                            <input
                                type="tel"
                                name="phone_number"
                                class="form-control"
                                placeholder="Enter phone number"
                                required
                            >

                        </div>


                        <!-- LOCATION -->

                        <div class="col-12">

                            <label class="form-label">

                                Delivery Location

                            </label>


                            <textarea
                                name="delivery_location"
                                class="form-control"
                                rows="2"
                                placeholder="Enter delivery location"
                                required
                            ></textarea>

                        </div>

                    </div>


                    <!-- ORDER ITEMS -->

                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >

                        <h6 class="mb-0">

                            Items Ordered

                        </h6>


                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="addItemButton"
                        >

                            <i
                                class="bi bi-plus"
                            ></i>

                            Add Item

                        </button>

                    </div>


                    <!-- ITEMS CONTAINER -->

                    <div id="itemsContainer">


                        <!-- FIRST ITEM -->

                        <div class="item-row">


                            <div
                                class="row g-2 align-items-end"
                            >


                                <!-- ITEM -->

                                <div class="col-md-5">

                                    <label
                                        class="form-label"
                                    >

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


                                <!-- QUANTITY -->

                                <div class="col-md-3">

                                    <label
                                        class="form-label"
                                    >

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


                                <!-- PRICE -->

                                <div class="col-md-3">

                                    <label
                                        class="form-label"
                                    >

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


                                <!-- DELETE -->

                                <div class="col-md-1">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger remove-item"
                                        disabled
                                        title="Remove item"
                                    >

                                        <i
                                            class="bi bi-trash"
                                        ></i>

                                    </button>

                                </div>


                            </div>

                        </div>

                    </div>


                    <!-- INFORMATION -->

                    <div
                        class="alert alert-light border mt-2 mb-0 py-2"
                    >

                        <small class="text-muted">

                            <i
                                class="bi bi-info-circle me-1"
                            ></i>

                            You can add one or multiple items
                            to this order.

                        </small>

                    </div>


                </div>


                <!--
                |--------------------------------------------------------------------------
                | MODAL FOOTER
                |--------------------------------------------------------------------------
                |
                | This stays visible at 100% zoom.
                |
                -->

                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        name="add_order"
                        class="btn btn-primary"
                    >

                        <i
                            class="bi bi-check-lg me-1"
                        ></i>

                        Submit Order

                    </button>


                </div>


            </form>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| BOOTSTRAP JS
|--------------------------------------------------------------------------
-->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


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


menuButton.addEventListener(
    "click",
    function () {

        sidebar.classList.toggle("show");

        overlay.classList.toggle("show");

    }
);


overlay.addEventListener(
    "click",
    function () {

        sidebar.classList.remove("show");

        overlay.classList.remove("show");

    }
);



/*
|--------------------------------------------------------------------------
| ADD / REMOVE ORDER ITEMS
|--------------------------------------------------------------------------
*/

const addItemButton =
    document.getElementById("addItemButton");

const itemsContainer =
    document.getElementById("itemsContainer");


/*
|--------------------------------------------------------------------------
| ADD ITEM
|--------------------------------------------------------------------------
*/

addItemButton.addEventListener(
    "click",
    function () {


        const itemRow =
            document.createElement("div");


        itemRow.className =
            "item-row";


        itemRow.innerHTML = `

            <div
                class="row g-2 align-items-end"
            >

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
                        title="Remove item"
                    >

                        <i
                            class="bi bi-trash"
                        ></i>

                    </button>

                </div>

            </div>

        `;


        itemsContainer.appendChild(itemRow);


        /*
        | Scroll to the newly added item
        */

        const modalBody =
            document.querySelector(
                "#addOrderModal .modal-body"
            );


        setTimeout(
            function () {

                modalBody.scrollTo({

                    top: modalBody.scrollHeight,

                    behavior: "smooth"

                });

            },
            100
        );

    }
);



/*
|--------------------------------------------------------------------------
| REMOVE ITEM
|--------------------------------------------------------------------------
*/

itemsContainer.addEventListener(
    "click",
    function (event) {


        const button =
            event.target.closest(
                ".remove-item"
            );


        if (!button) {

            return;

        }


        const rows =
            itemsContainer.querySelectorAll(
                ".item-row"
            );


        /*
        | Always keep at least one item
        */

        if (rows.length > 1) {

            button
                .closest(".item-row")
                .remove();

        }


        /*
        | Disable delete button when only
        | one item remains
        */

        updateRemoveButtons();

    }
);



/*
|--------------------------------------------------------------------------
| UPDATE REMOVE BUTTONS
|--------------------------------------------------------------------------
*/

function updateRemoveButtons() {


    const rows =
        itemsContainer.querySelectorAll(
            ".item-row"
        );


    rows.forEach(
        function (row, index) {


            const removeButton =
                row.querySelector(
                    ".remove-item"
                );


            if (removeButton) {

                removeButton.disabled =
                    rows.length === 1;

            }

        }
    );

}



/*
|--------------------------------------------------------------------------
| UPDATE BUTTONS AFTER ADDING
|--------------------------------------------------------------------------
*/

addItemButton.addEventListener(
    "click",
    function () {

        updateRemoveButtons();

    }
);



/*
|--------------------------------------------------------------------------
| RESET MODAL WHEN CLOSED
|--------------------------------------------------------------------------
*/

const addOrderModal =
    document.getElementById(
        "addOrderModal"
    );


addOrderModal.addEventListener(
    "hidden.bs.modal",
    function () {


        /*
        | Reset the form
        */

        const form =
            document.getElementById(
                "addOrderForm"
            );


        form.reset();


        /*
        | Remove extra item rows
        */

        const rows =
            itemsContainer.querySelectorAll(
                ".item-row"
            );


        rows.forEach(
            function (row, index) {

                if (index > 0) {

                    row.remove();

                }

            }
        );


        /*
        | Reset scroll position
        */

        const modalBody =
            document.querySelector(
                "#addOrderModal .modal-body"
            );


        modalBody.scrollTop = 0;


        updateRemoveButtons();

    }
);

</script>


</body>

</html>