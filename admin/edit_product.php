<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";


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
| CREATE UPLOAD DIRECTORY
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
| GET PRODUCT ID
|--------------------------------------------------------------------------
*/

$product_id = (int)($_GET["id"] ?? 0);


if ($product_id <= 0) {

    header("Location: products.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| FETCH PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id
]);


$product = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| PRODUCT NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$product) {

    header("Location: products.php");
    exit;
}


$error = "";


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD FUNCTION
|--------------------------------------------------------------------------
*/

function uploadProductImage(
    $file,
    $upload_directory,
    $upload_database_path
) {

    if (
        !isset($file) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {

        return null;
    }


    if (
        $file["error"] !== UPLOAD_ERR_OK
    ) {

        throw new Exception(
            "There was an error uploading the image."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum 5MB
    |--------------------------------------------------------------------------
    */

    if (
        $file["size"] >
        5 * 1024 * 1024
    ) {

        throw new Exception(
            "Product image must not be larger than 5MB."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Allowed image types
    |--------------------------------------------------------------------------
    */

    $allowed_types = [

        "image/jpeg" => "jpg",

        "image/png" => "png",

        "image/webp" => "webp"

    ];


    $finfo =
        finfo_open(
            FILEINFO_MIME_TYPE
        );


    $mime_type =
        finfo_file(
            $finfo,
            $file["tmp_name"]
        );


    finfo_close($finfo);


    if (
        !isset(
            $allowed_types[$mime_type]
        )
    ) {

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
        uniqid(
            "product_",
            true
        )
        . "."
        . $extension;


    $destination =
        $upload_directory
        . $filename;


    /*
    |--------------------------------------------------------------------------
    | Move uploaded file
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


    return
        $upload_database_path
        . $filename;
}


/*
|--------------------------------------------------------------------------
| DELETE OLD IMAGE
|--------------------------------------------------------------------------
*/

function deleteProductImage($photo)
{

    if (
        empty($photo)
    ) {

        return;
    }


    $file =
        "../" . $photo;


    if (
        file_exists($file)
    ) {

        @unlink($file);
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_product"])
) {


    $product_name =
        trim(
            $_POST["product_name"] ?? ""
        );


    $size =
        trim(
            $_POST["size"] ?? ""
        );


    $category =
        trim(
            $_POST["category"] ?? ""
        );


    $price =
        (float)(
            $_POST["price"] ?? 0
        );


    $stock_status =
        trim(
            $_POST["stock_status"] ?? ""
        );


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

    }


    elseif (
        !in_array(
            $size,
            [
                "Small",
                "Medium",
                "Large"
            ],
            true
        )
    ) {

        $error =
            "Invalid product size.";

    }


    elseif (
        !in_array(
            $category,
            $categories,
            true
        )
    ) {

        $error =
            "Invalid product category.";

    }


    elseif (
        !in_array(
            $stock_status,
            [
                "In Stock",
                "Out of Stock"
            ],
            true
        )
    ) {

        $error =
            "Invalid stock status.";

    }


    else {


        try {


            /*
            |--------------------------------------------------------------------------
            | Keep existing photo
            |--------------------------------------------------------------------------
            */

            $photo =
                $product["photo"];


            /*
            |--------------------------------------------------------------------------
            | Check if new image uploaded
            |--------------------------------------------------------------------------
            */

            if (
                isset($_FILES["photo"])
                &&
                $_FILES["photo"]["error"]
                    !== UPLOAD_ERR_NO_FILE
            ) {


                /*
                |--------------------------------------------------------------------------
                | Upload new image
                |--------------------------------------------------------------------------
                */

                $new_photo =
                    uploadProductImage(
                        $_FILES["photo"],
                        $upload_directory,
                        $upload_database_path
                    );


                /*
                |--------------------------------------------------------------------------
                | Delete old image
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($product["photo"])
                ) {

                    deleteProductImage(
                        $product["photo"]
                    );
                }


                $photo =
                    $new_photo;
            }


            /*
            |--------------------------------------------------------------------------
            | Update database
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("
                    UPDATE products

                    SET
                        product_name = ?,
                        size = ?,
                        photo = ?,
                        category = ?,
                        price = ?,
                        stock_status = ?

                    WHERE id = ?
                ");


            $stmt->execute([

                $product_name,

                $size,

                $photo,

                $category,

                $price,

                $stock_status,

                $product_id

            ]);


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                "Location: products.php?updated=1"
            );

            exit;


        } catch (Exception $e) {

            $error =
                $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update values shown in form
    |--------------------------------------------------------------------------
    */

    $product["product_name"] =
        $product_name;

    $product["size"] =
        $size;

    $product["category"] =
        $category;

    $product["price"] =
        $price;

    $product["stock_status"] =
        $stock_status;
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
        Edit Product - Admin Dashboard
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

            max-width: 1000px;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM CARD
        |--------------------------------------------------------------------------
        */

        .form-card {

            background: white;

            border-radius: 15px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,.05);

            padding: 30px;

        }


        .form-title {

            font-size: 20px;

            font-weight: 600;

            color: #27332d;

            margin-bottom: 5px;

        }


        .form-description {

            color: #6c757d;

            margin-bottom: 25px;

        }


        .form-label {

            font-weight: 600;

            color: #3d4943;

        }


        .form-control,

        .form-select {

            border-radius: 8px;

            padding:
                10px 12px;

        }


        .form-control:focus,

        .form-select:focus {

            border-color:
                var(--primary-color);

            box-shadow:
                0 0 0 .2rem
                rgba(63,102,86,.15);

        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON
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
        | CURRENT IMAGE
        |--------------------------------------------------------------------------
        */

        .current-image-container {

            margin-top: 10px;

        }


        .current-image {

            width: 150px;

            height: 150px;

            object-fit: cover;

            border-radius: 12px;

            border:
                1px solid
                #ddd;

            display: block;

        }


        .image-preview-container {

            display: none;

            margin-top: 15px;

        }


        .image-preview {

            width: 150px;

            height: 150px;

            object-fit: cover;

            border-radius: 12px;

            border:
                1px solid
                #ddd;

        }


        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        .stock-help {

            font-size: 13px;

            color:
                #6c757d;

            margin-top: 5px;

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


            .content {

                max-width: 100%;

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


            .form-card {

                padding: 20px;

            }


            .current-image,

            .image-preview {

                width: 120px;

                height: 120px;

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


        <a href="dashboard.php">

            <i class="bi bi-house-door"></i>

            <span>
                Home
            </span>

        </a>


        <a href="orders.php">

            <i class="bi bi-cart3"></i>

            <span>
                Orders
            </span>

        </a>


        <a
            href="products.php"
            class="active"
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
| MAIN
|--------------------------------------------------------------------------
-->

<div class="main-content">


    <!-- TOPBAR -->

    <header class="topbar">

        <h1 class="page-title">
            Edit Product
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


        <!-- FORM CARD -->

        <div class="form-card">


            <div class="form-title">

                <i
                    class="bi bi-pencil-square me-2"
                    style="color:#3F6656;"
                ></i>

                Edit Product

            </div>


            <div class="form-description">

                Update the product information below.

            </div>


            <!-- ERROR -->

            <?php if (!empty($error)): ?>

                <div
                    class="alert alert-danger"
                >

                    <i
                        class="bi bi-exclamation-circle me-2"
                    ></i>

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- FORM -->

            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <div class="row g-4">


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
                            value="<?= htmlspecialchars(
                                $product["product_name"]
                            ) ?>"
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

                            <option value="">

                                Select Size

                            </option>


                            <option
                                value="Small"
                                <?= $product["size"] === "Small"
                                    ? "selected"
                                    : "" ?>
                            >

                                Small

                            </option>


                            <option
                                value="Medium"
                                <?= $product["size"] === "Medium"
                                    ? "selected"
                                    : "" ?>
                            >

                                Medium

                            </option>


                            <option
                                value="Large"
                                <?= $product["size"] === "Large"
                                    ? "selected"
                                    : "" ?>
                            >

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

                            <option value="">

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
                                    <?= $product["category"] === $category
                                        ? "selected"
                                        : "" ?>
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
                            value="<?= htmlspecialchars(
                                $product["price"]
                            ) ?>"
                            placeholder="0.00"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- STOCK STATUS -->

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
                                <?= $product["stock_status"] === "In Stock"
                                    ? "selected"
                                    : "" ?>
                            >

                                In Stock

                            </option>


                            <option
                                value="Out of Stock"
                                <?= $product["stock_status"] === "Out of Stock"
                                    ? "selected"
                                    : "" ?>
                            >

                                Out of Stock

                            </option>

                        </select>


                        <div class="stock-help">

                            Choose whether this product
                            is currently available.

                        </div>

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


                        <div
                            class="stock-help"
                        >

                            Leave empty to keep the
                            current photo.

                        </div>


                        <!-- CURRENT PHOTO -->

                        <?php if (
                            !empty(
                                $product["photo"]
                            )
                        ): ?>

                            <div
                                class="current-image-container"
                            >

                                <small
                                    class="d-block text-muted mb-2"
                                >

                                    Current Photo

                                </small>


                                <img
                                    src="../<?= htmlspecialchars(
                                        $product["photo"]
                                    ) ?>"
                                    class="current-image"
                                    alt="<?= htmlspecialchars(
                                        $product["product_name"]
                                    ) ?>"
                                >

                            </div>

                        <?php endif; ?>


                        <!-- NEW PHOTO PREVIEW -->

                        <div
                            class="image-preview-container"
                            id="imagePreviewContainer"
                        >

                            <small
                                class="d-block text-muted mb-2"
                            >

                                New Photo Preview

                            </small>


                            <img
                                src=""
                                id="imagePreview"
                                class="image-preview"
                                alt="New Image Preview"
                            >

                        </div>

                    </div>


                </div>


                <!-- BUTTONS -->

                <div
                    class="d-flex gap-2 mt-4 pt-4 border-top"
                >


                    <a
                        href="products.php"
                        class="btn btn-secondary"
                    >

                        <i
                            class="bi bi-arrow-left me-1"
                        ></i>

                        Cancel

                    </a>


                    <button
                        type="submit"
                        name="update_product"
                        class="btn btn-primary"
                    >

                        <i
                            class="bi bi-check-lg me-1"
                        ></i>

                        Update Product

                    </button>


                </div>


            </form>

        </div>

    </main>

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
| IMAGE PREVIEW
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
        | Check size
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