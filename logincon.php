<?php
session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $repassword = $_POST['repassword'];
    $roles = mysqli_real_escape_string($conn, $_POST['roles']);

    // Check if passwords match
    if ($password !== $repassword) {
        $_SESSION['register_error'] = 'Passwords do not match!';
        $_SESSION['active_form'] = 'register';
        header("location: register.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = 'Invalid email format!';
        $_SESSION['active_form'] = 'register';
        header("location: register.php");
        exit();
    }

    // Check if email already exists using prepared statement
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    
    if ($checkEmail->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered! Please use a different email.';
        $_SESSION['active_form'] = 'register';
        $checkEmail->close();
        header("location: register.php");
        exit();
    }
    $checkEmail->close();
    
    // If email doesn't exist, proceed with registration
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, roles) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $roles);
    
    if ($stmt->execute()) {
        $_SESSION['register_success'] = 'Registration successful! Please login.';
        $_SESSION['active_form'] = 'login';
    } else {
        $_SESSION['register_error'] = 'Registration failed! Please try again.';
        $_SESSION['active_form'] = 'register';
    }
    $stmt->close();
    
    header("location: login.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['roles'];
            
            if ($user['roles'] === 'carer') {
                header("Location: Carer/dashboard.php");
            } elseif ($user['roles'] === 'manager') {
                header("Location: Manager/dashboard.php");
            } else {
                header("Location: Member/dashboard.php");
            }
            exit();
        }
    }
    
    $stmt->close();
    
    $_SESSION['login_error'] = 'Incorrect email or password';
    $_SESSION['active_form'] = 'login';
    header("Location: login.php");
    exit();
}
?>