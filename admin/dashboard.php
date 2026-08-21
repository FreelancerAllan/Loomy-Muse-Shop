
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
| These are temporary values.
| Later we can replace them with MySQL queries.
*/

$pending_orders = 5;
$paid_delivered_orders = 7;
$total_products = 10;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <!-- Bootstrap 5 -->
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

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: var(--primary-color);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-logo {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            padding: 15px;
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
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 13px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            transition: 0.2s;
            font-size: 15px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
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

        /* =========================
           MAIN CONTENT
        ========================= */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* =========================
           TOP NAVBAR
        ========================= */

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
            color: #27332d;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
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

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 30px;
        }

        .welcome-box {
            background: var(--primary-color);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .welcome-box h4 {
            margin-bottom: 5px;
            font-weight: 600;
        }

        .welcome-box p {
            margin: 0;
            opacity: 0.85;
        }

        /* =========================
           STAT CARDS
        ========================= */

        .stat-card {
            background: white;
            border: none;
            border-radius: 15px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0,0,0,0.08);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            margin-bottom: 20px;
        }

        .pending-icon {
            background: #fff3cd;
            color: #856404;
        }

        .delivered-icon {
            background: #d1e7dd;
            color: #0f5132;
        }

        .products-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #27332d;
            margin-bottom: 5px;
        }

        .stat-title {
            color: #777;
            font-size: 15px;
        }

        /* =========================
           MOBILE
        ========================= */

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

            .topbar {
                height: 70px;
            }

            .sidebar-overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.4);
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

            .welcome-box {
                padding: 20px;
            }

            .stat-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-logo">

        <!-- Replace ../images/logo.jpg with your actual logo -->
        <img src="../images/logo.jpg" alt="Logo">

    </div>

    <nav class="sidebar-menu">

        <a href="dashboard.php" class="active">
            <i class="bi bi-house-door"></i>
            <span>Home</span>
        </a>

        <a href="orders.php">
            <i class="bi bi-cart3"></i>
            <span>Orders</span>
        </a>

        <a href="products.php">
            <i class="bi bi-box-seam"></i>
            <span>Products</span>
        </a>

        <a href="logout.php" class="logout-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </nav>

</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>


<!-- =========================
     MAIN CONTENT
========================= -->

<div class="main-content">

    <!-- Mobile Header -->
    <div class="mobile-header">

        <button
            class="menu-button"
            id="menuButton"
            type="button"
        >
            <i class="bi bi-list"></i>
        </button>

        <strong>Admin Panel</strong>

        <div></div>

    </div>


    <!-- Topbar -->
    <header class="topbar">

        <h1 class="page-title">
            Dashboard
        </h1>

        <div class="admin-info">

            <div class="admin-avatar">
                <i class="bi bi-person"></i>
            </div>

            <span>
                <?= htmlspecialchars($_SESSION["admin_email"]) ?>
            </span>

        </div>

    </header>


    <!-- Page Content -->
    <main class="content">

        <!-- Welcome -->
        <div class="welcome-box">

            <h4>
                Welcome back, Admin!
            </h4>

            <p>
                Here's an overview of your store today.
            </p>

        </div>


        <!-- Statistics -->
        <div class="row g-4">

            <!-- Pending Orders -->
            <div class="col-12 col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-icon pending-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div class="stat-number">
                        <?= $pending_orders ?>
                    </div>

                    <div class="stat-title">
                        Pending Orders
                    </div>

                </div>

            </div>


            <!-- Paid & Delivered -->
            <div class="col-12 col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-icon delivered-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>

                    <div class="stat-number">
                        <?= $paid_delivered_orders ?>
                    </div>

                    <div class="stat-title">
                        Paid & Delivered Orders
                    </div>

                </div>

            </div>


            <!-- Products -->
            <div class="col-12 col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-icon products-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>

                    <div class="stat-number">
                        <?= $total_products ?>
                    </div>

                    <div class="stat-title">
                        Total Products
                    </div>

                </div>

            </div>

        </div>

    </main>

</div>


<!-- =========================
     JAVASCRIPT
========================= -->

<script>

    const sidebar = document.getElementById("sidebar");
    const menuButton = document.getElementById("menuButton");
    const overlay = document.getElementById("sidebarOverlay");

    menuButton.addEventListener("click", function () {
        sidebar.classList.toggle("show");
        overlay.classList.toggle("show");
    });

    overlay.addEventListener("click", function () {
        sidebar.classList.remove("show");
        overlay.classList.remove("show");
    });

</script>

</body>
</html>

