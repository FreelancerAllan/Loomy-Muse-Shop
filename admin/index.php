<?php
session_start();
require_once "config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {

        $stmt = $pdo->prepare(
            "SELECT id, email, password FROM admins WHERE email = ? LIMIT 1"
        );

        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin["password"])) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_email"] = $admin["email"];

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            min-height: 100vh;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .login-header {
            background: #3F6656;
            color: white;
            padding: 35px 25px;
            text-align: center;
        }

        .logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 10px;
            margin-bottom: 15px;
        }

        .login-body {
            padding: 30px;
        }

        .form-control {
            height: 50px;
            border-radius: 10px;
        }

        .btn-login {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
             background-color: #3F6656 !important;
    border-color: #3F6656 !important;
        }
    

/* Optional: Change the hover color slightly darker for better UX */
.btn-login:hover {
    background-color: #335345 !important;
    border-color: #335345 !important;
}
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="card login-card">

        <div class="login-header">

            <!-- Replace logo.png with your own logo -->
            <img
                src="../images/logo.jpg"
                alt="Logo"
                class="logo"
            >

            <h3 class="mb-1">Admin Login</h3>

          

        </div>

        <div class="login-body">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="mb-3">
                    <label for="email" class="form-label">
                        Email Address
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">
                        Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="btn btn-primary btn-login w-100"
                >
                    Sign In
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>