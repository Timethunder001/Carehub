<?php
session_start();



$errors = [
    'login' => $_SESSION['login_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();
function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/Login.css">
    <title>Login</title>
</head>
<body>
    <div class="wrapper">
        <div class="form-box login" <?= isActiveForm('login', $activeForm); ?>>
            <form action="logincon.php" method="post">
            <h2>Login</h2>
            <?= showError($errors ['login']); ?>

                <div class="input-box">
                    <span class="icon"><ion-icon name="mail"></ion-icon></span>
                    <input type="email" name="email" required>
                    <label>Email</label>
                </div>

                <div class="input-box">
                    <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                    <input type="password" name="password" required>
                    <label>Password</label>
                </div>

                <div class="remember-forgot">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="btn">Login</button>

                <div class="login-register">
                    <p>Don't have an account? <a href="register.php" class="register-link">Register</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
