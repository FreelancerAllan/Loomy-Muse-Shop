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
| PRODUCT CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [
    "Scarves",
    "Buckethats",
    "Beanies",
    "Cardigan",
    "Sweater",
    "Bags",
    "Belts",
    "Waist Scarf",
    "Head Wrap"
];


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$upload_directory = "../uploads/products/";

$upload_database_path = "uploads/products/";


/*
|--------------------------------------------------------------------------
| CREATE UPLOAD DIRECTORY IF IT DOES NOT EXIST
|--------------------------------------------------------------------------
*/

if (!is_dir($upload_directory)) {

    mkdir(
        $upload_directory,
        0755,
        true
    );
}


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD FUNCTION
|--------------------------------------------------------------------------
*/

function uploadProductImage($file, $upload_directory, $upload_database_path)
{
    if (
        !isset($file) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }


    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("There was an error uploading the image.");
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum file size - 5MB
    |--------------------------------------------------------------------------
    */

    if ($file["size"] > 5 * 1024 * 1024) {
        throw new Exception(
            "Product image must not be larger than 5MB."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check image type
    |--------------------------------------------------------------------------
    */

    $allowed_types = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];


    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    $mime_type = finfo_file(
        $finfo,
        $file["tmp_name"]
    );

    finfo_close($finfo);


    if (!isset($allowed_types[$mime_type])) {

        throw new Exception(
            "Only JPG, PNG and WEBP images are allowed."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate unique filename
    |--------------------------------------------------------------------------
    */

    $extension =
        $allowed_types[$mime_type];


    $filename =
        uniqid("product_", true)
        . "."
        . $extension;


    $destination =
        $upload_directory
        . $filename;


    /*
    |--------------------------------------------------------------------------
    | Move image
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $destination
        )
    ) {

        throw new Exception(
            "Unable to save the product image."
        );
    }


    return $upload_database_path . $filename;
}


/*
|--------------------------------------------------------------------------
| DELETE IMAGE FILE
|--------------------------------------------------------------------------
*/

function deleteProductImage($photo)
{
    if (empty($photo)) {
        return;
    }


    $file = "../" . $photo;


    if (file_exists($file)) {
        @unlink($file);
    }
}


/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_product"])
) {

    $product_name =
        trim($_POST["product_name"] ?? "");


    $size =
        trim($_POST["size"] ?? "");


    $category =
        trim($_POST["category"] ?? "");


    $price =
        (float)($_POST["price"] ?? 0);


    $stock_status =
        trim($_POST["stock_status"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($product_name)
        || empty($size)
        || empty($category)
        || $price < 0
        || empty($stock_status)
    ) {

        $error =
            "Please fill in all product details.";

    } elseif (
        !in_array(
            $size,
            ["Small", "Medium", "Large"],
            true
        )
    ) {

        $error =
            "Invalid product size.";

    } elseif (
        !in_array(
            $category,
            $categories,
            true
        )
    ) {

        $error =
            "Invalid product category.";

    } elseif (
        !in_array(
            $stock_status,
            ["In Stock", "Out of Stock"],
            true
        )
    ) {

        $error =
            "Invalid stock status.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Upload Image
            |--------------------------------------------------------------------------
            */

            $photo = uploadProductImage(
                $_FILES["photo"] ?? null,
                $upload_directory,
                $upload_database_path
            );


            /*
            |--------------------------------------------------------------------------
            | Insert Product
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO products
                (
                    product_name,
                    size,
                    photo,
                    category,
                    price,
                    stock_status
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");


            $stmt->execute([
                $product_name,
                $size,
                $photo,
                $category,
                $price,
                $stock_status
            ]);


            header(
                "Location: products.php?success=1"
            );

            exit;


        } catch (Exception $e) {

            $error =
                $e->getMessage();

        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_product"])
) {

    $product_id =
        (int)($_POST["product_id"] ?? 0);


    if ($product_id > 0) {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get image first
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT photo
                FROM products
                WHERE id = ?
            ");

            $stmt->execute([
                $product_id
            ]);


            $product =
                $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Delete database record
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->execute([
                $product_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Delete image
            |--------------------------------------------------------------------------
            */

            if (
                $product
                && !empty($product["photo"])
            ) {

                deleteProductImage(
                    $product["photo"]
                );
            }


            header(
                "Location: products.php?deleted=1"
            );

            exit;


        } catch (Exception $e) {

            $error =
                "Unable to delete product.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {

    $message =
        "Product added successfully.";
}


if (isset($_GET["deleted"])) {

    $message =
        "Product deleted successfully.";
}


if (isset($_GET["updated"])) {

    $message =
        "Product updated successfully.";
}


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        product_name,
        size,
        photo,
        category,
        price,
        stock_status,
        created_at

    FROM products

    ORDER BY id DESC
");


$products =
    $stmt->fetchAll();

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
        Products - Admin Dashboard
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

            background:
                var(--light-bg);

            font-family:
                Arial,
                sans-serif;

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

            background:
                var(--primary-color);

            color: white;

            z-index: 1000;

            transition: .3s;

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

            padding:
                20px 15px;

        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 14px;

            color:
                rgba(255,255,255,.85);

            text-decoration: none;

            padding:
                13px 15px;

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
        | MAIN
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

            padding:
                0 30px;

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

            background:
                var(--primary-color);

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
        | PRODUCT TABLE
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

            padding: 14px 16px;

            vertical-align: middle;

        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT IMAGE
        |--------------------------------------------------------------------------
        */

        .product-image {

            width: 60px;

            height: 60px;

            object-fit: cover;

            border-radius: 10px;

            background: #f1f3f2;

            border:
                1px solid
                #e5e7e6;

        }


        .no-image {

            width: 60px;

            height: 60px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f1f3f2;

            color: #8a9690;

            border-radius: 10px;

            font-size: 24px;

        }


        /*
        |--------------------------------------------------------------------------
        | STOCK STATUS
        |--------------------------------------------------------------------------
        */

        .stock-status {

            display: inline-block;

            padding:
                6px 11px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

        }


        .stock-in {

            background: #d1e7dd;

            color: #0f5132;

        }


        .stock-out {

            background: #f8d7da;

            color: #842029;

        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORY BADGE
        |--------------------------------------------------------------------------
        */

        .category-badge {

            background:
                #eef3f0;

            color:
                var(--primary-color);

            padding:
                6px 10px;

            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

        }


        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

        .modal-header {

            background:
                var(--primary-color);

            color: white;

            padding:
                14px 20px;

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


        .modal-body {

            padding: 20px;

            max-height:
                calc(100vh - 180px);

            overflow-y: auto;

        }


        .modal-footer {

            background: white;

            border-top:
                1px solid
                #e5e7e6;

            padding:
                12px 20px;

        }


        .form-control,

        .form-select {

            border-radius: 8px;

        }


        /*
        |--------------------------------------------------------------------------
        | IMAGE PREVIEW
        |--------------------------------------------------------------------------
        */

        .image-preview-container {

            display: none;

            margin-top: 10px;

        }


        .image-preview {

            width: 120px;

            height: 120px;

            object-fit: cover;

            border-radius: 10px;

            border:
                1px solid
                #ddd;

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

            padding:
                0 20px;

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
        | RESPONSIVE
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

                padding:
                    20px 15px;

            }


            .topbar {

                padding:
                    0 15px;

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


            .modal-dialog {

                margin: 10px;

            }


            .modal-body {

                padding: 15px;

                max-height:
                    calc(100vh - 140px);

            }


            .modal-footer {

                padding:
                    10px 15px;

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


        <!-- HOME -->

        <a href="dashboard.php">

            <i class="bi bi-house-door"></i>

            <span>
                Home
            </span>

        </a>


        <!-- ORDERS -->

        <a href="orders.php">

            <i class="bi bi-cart3"></i>

            <span>
                Orders
            </span>

        </a>


        <!-- PRODUCTS -->

        <a
            href="products.php"
            class="active"
        >

            <i class="bi bi-box-seam"></i>

            <span>
                Products
            </span>

        </a>


        <!-- LOGOUT -->

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
            Products
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
                    Manage Products
                </h4>


                <small class="text-muted">

                    Add and manage your products

                </small>

            </div>


            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addProductModal"
            >

                <i class="bi bi-plus-lg"></i>

                Add Product

            </button>

        </div>


        <!-- SUCCESS MESSAGE -->

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


        <!-- ERROR MESSAGE -->

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
        | PRODUCTS TABLE
        |--------------------------------------------------------------------------
        -->

        <div class="table-card">

            <div class="table-responsive">

                <table class="table">

                    <thead>

                        <tr>

                            <th>
                                Photo
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Size
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Stock
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


                    <?php if (
                        count($products) > 0
                    ): ?>


                        <?php foreach (
                            $products
                            as $product
                        ): ?>


                            <tr>


                                <!-- PHOTO -->

                                <td>


                                    <?php if (
                                        !empty(
                                            $product["photo"]
                                        )
                                    ): ?>


                                        <img
                                            src="../<?= htmlspecialchars(
                                                $product["photo"]
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $product["product_name"]
                                            ) ?>"
                                            class="product-image"
                                        >


                                    <?php else: ?>


                                        <div
                                            class="no-image"
                                        >

                                            <i
                                                class="bi bi-image"
                                            ></i>

                                        </div>


                                    <?php endif; ?>


                                </td>


                                <!-- PRODUCT NAME -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $product["product_name"]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- SIZE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $product["size"]
                                    ) ?>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <span
                                        class="category-badge"
                                    >

                                        <?= htmlspecialchars(
                                            $product["category"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PRICE -->

                                <td>

                                    <strong>

                                        <?= number_format(
                                            $product["price"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- STOCK -->

                                <td>


                                    <?php if (
                                        $product["stock_status"]
                                        === "In Stock"
                                    ): ?>


                                        <span
                                            class="stock-status stock-in"
                                        >

                                            <i
                                                class="bi bi-check-circle me-1"
                                            ></i>

                                            In Stock

                                        </span>


                                    <?php else: ?>


                                        <span
                                            class="stock-status stock-out"
                                        >

                                            <i
                                                class="bi bi-x-circle me-1"
                                            ></i>

                                            Out of Stock

                                        </span>


                                    <?php endif; ?>


                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $product["created_at"]
                                        )
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="d-flex gap-2"
                                    >


                                        <!-- EDIT -->

                                        <a
                                            href="edit_product.php?id=<?= $product["id"] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit Product"
                                        >

                                            <i
                                                class="bi bi-pencil"
                                            ></i>

                                        </a>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this product?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= $product["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="delete_product"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete Product"
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


                        <!-- EMPTY -->

                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-box-seam fs-1 text-muted"
                                ></i>


                                <p
                                    class="mt-3 mb-0 text-muted"
                                >

                                    No products found.

                                </p>


                                <small
                                    class="text-muted"
                                >

                                    Click "Add Product"
                                    to create your first product.

                                </small>

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
| ADD PRODUCT MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="addProductModal"
    tabindex="-1"
    aria-labelledby="addProductModalLabel"
    aria-hidden="true"
>


    <div
        class="modal-dialog modal-lg modal-dialog-centered"
    >


        <div class="modal-content">


            <!-- HEADER -->

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="addProductModalLabel"
                >

                    <i
                        class="bi bi-box-seam me-2"
                    ></i>

                    Add New Product

                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <!-- FORM -->

            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <!-- BODY -->

                <div class="modal-body">


                    <div class="row g-3">


                        <!-- PRODUCT NAME -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Product Name

                            </label>


                            <input
                                type="text"
                                name="product_name"
                                class="form-control"
                                placeholder="Enter product name"
                                required
                            >

                        </div>


                        <!-- SIZE -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Size

                            </label>


                            <select
                                name="size"
                                class="form-select"
                                required
                            >

                                <option
                                    value=""
                                >

                                    Select Size

                                </option>


                                <option value="Small">

                                    Small

                                </option>


                                <option value="Medium">

                                    Medium

                                </option>


                                <option value="Large">

                                    Large

                                </option>

                            </select>

                        </div>


                        <!-- CATEGORY -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Category

                            </label>


                            <select
                                name="category"
                                class="form-select"
                                required
                            >

                                <option
                                    value=""
                                >

                                    Select Category

                                </option>


                                <?php foreach (
                                    $categories
                                    as $category
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $category
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $category
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </div>


                        <!-- PRICE -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Price

                            </label>


                            <input
                                type="number"
                                name="price"
                                class="form-control"
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >

                        </div>


                        <!-- STOCK -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Stock Status

                            </label>


                            <select
                                name="stock_status"
                                class="form-select"
                                required
                            >

                                <option
                                    value="In Stock"
                                >

                                    In Stock

                                </option>


                                <option
                                    value="Out of Stock"
                                >

                                    Out of Stock

                                </option>

                            </select>

                        </div>


                        <!-- PHOTO -->

                        <div class="col-md-6">

                            <label
                                class="form-label"
                            >

                                Product Photo

                            </label>


                            <input
                                type="file"
                                name="photo"
                                id="productPhoto"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp"
                            >


                            <small
                                class="text-muted"
                            >

                                JPG, PNG or WEBP.
                                Maximum 5MB.

                            </small>


                            <!-- IMAGE PREVIEW -->

                            <div
                                class="image-preview-container"
                                id="imagePreviewContainer"
                            >

                                <img
                                    src=""
                                    id="imagePreview"
                                    class="image-preview"
                                    alt="Image Preview"
                                >

                            </div>

                        </div>


                    </div>


                </div>


                <!-- FOOTER -->

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
                        name="add_product"
                        class="btn btn-primary"
                    >

                        <i
                            class="bi bi-check-lg me-1"
                        ></i>

                        Save Product

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
| PRODUCT IMAGE PREVIEW
|--------------------------------------------------------------------------
*/

const productPhoto =
    document.getElementById(
        "productPhoto"
    );


const imagePreview =
    document.getElementById(
        "imagePreview"
    );


const imagePreviewContainer =
    document.getElementById(
        "imagePreviewContainer"
    );


productPhoto.addEventListener(
    "change",
    function () {


        const file =
            this.files[0];


        if (!file) {

            imagePreviewContainer.style.display =
                "none";

            imagePreview.src = "";

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | Check file size
        |--------------------------------------------------------------------------
        */

        if (
            file.size >
            5 * 1024 * 1024
        ) {

            alert(
                "The image must not be larger than 5MB."
            );

            this.value = "";

            imagePreviewContainer.style.display =
                "none";

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | Preview
        |--------------------------------------------------------------------------
        */

        const reader =
            new FileReader();


        reader.onload =
            function (event) {

                imagePreview.src =
                    event.target.result;

                imagePreviewContainer.style.display =
                    "block";

            };


        reader.readAsDataURL(file);

    }
);

</script>


</body>

</html>