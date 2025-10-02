<?php
session_start();

$errors = [
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'register';

session_unset();
session_destroy();

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
    <link rel="stylesheet" href="CSS/register.css">
    <title>Register</title>
</head>
<body>
    <div class="wrapper">
        <div class="form-box register" <?= isActiveForm('register', $activeForm); ?>>
            <form action="logincon.php" method="post">
                <h2>Sign up</h2>
                <?= showError($errors['register']); ?>
                
                <div class="input-box">
                    <span class="icon"><ion-icon name="person-circle-outline"></ion-icon></span>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <label for="">Full Name</label>
                </div>
                
                <div class="input-box">
                    <span class="icon"><ion-icon name="mail"></ion-icon></span>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="">Email</label>
                </div>
                
                <div class="input-box">
                    <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                    <input type="password" name="password" required>
                    <label for="">Password</label>
                </div>
                
                <div class="input-box">
                    <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                    <input type="password" name="repassword" required>
                    <label for="">Re-Enter Password</label>
                </div>
                
                <div class="select-input">
                    <select name="roles" required>
                        <option value="">---Select role---</option>
                        <option value="Carer" <?= (($_POST['roles'] ?? '') === 'Carer') ? 'selected' : '' ?>>Carer</option>
                        <option value="Manager" <?= (($_POST['roles'] ?? '') === 'Manager') ? 'selected' : '' ?>>Manager</option>
                        <option value="Member" <?= (($_POST['roles'] ?? '') === 'Member') ? 'selected' : '' ?>>Member</option>
                    </select>
                </div>
                
                <button type="submit" name="register" class="btn">Sign up</button>
                
                <div class="login-register">
                    <p>Already have an account? <a href="login.php" class="register-link">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>