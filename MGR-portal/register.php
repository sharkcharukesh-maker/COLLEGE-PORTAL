<?php
include "db.php";
session_start();

$message = "";

// REGISTER PROCESS
if (isset($_POST['register'])) {

    $reg = trim($_POST['register_number']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($reg === "" || $name === "" || $email === "" || $password === "") {
        $message = "Please fill all fields.";
    } else {
        // Check if register number already exists (safe prepared statement)
        $check = $conn->prepare("SELECT id FROM students WHERE register_number = ?");
        $check->bind_param("s", $reg);
        $check->execute();
        $res = $check->get_result();

        if ($res && $res->num_rows > 0) {
            $message = "Register Number already exists!";
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert student (role column must exist; default student is fine too)
            $ins = $conn->prepare(
                "INSERT INTO students (register_number, name, email, password, role) VALUES (?, ?, ?, ?, 'student')"
            );
            $ins->bind_param("ssss", $reg, $name, $email, $hashed_password);

            if ($ins->execute()) {
                $message = "Registration successful! Now login.";
            } else {
                $message = "Error occurred while registering. Try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MGR College Portal - Register</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #4f46e5;
    --secondary: #06b6d4;
    --text-color: white;
    --card-bg: rgba(255,255,255,0.15);
}

body.dark {
    --primary: #111827;
    --secondary: #1f2937;
    --text-color: #f9fafb;
    --card-bg: rgba(0,0,0,0.5);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    height: 100vh;
    background: linear-gradient(-45deg, var(--primary), var(--secondary), #9333ea, #ec4899);
    background-size: 400% 400%;
    animation: gradientBG 12s ease infinite;
    display: flex;
    justify-content: center;
    align-items: center;
    color: var(--text-color);
    transition: 0.4s;
}

@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

.portal-container {
    width: 100%;
    max-width: 420px;
}

.college-header {
    text-align: center;
    margin-bottom: 20px;
}

.college-header h1 {
    font-weight: 600;
}

.college-header p {
    font-size: 14px;
    opacity: 0.9;
}

.login-card {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: 0.3s;
}

.input-group {
    margin-bottom: 15px;
}

.input-group input {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: none;
    margin-top: 5px;
    outline: none;
}

.login-btn {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: none;
    background: white;
    color: var(--primary);
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.login-btn:hover {
    background: #e0e7ff;
}

.extra-links {
    margin-top: 15px;
    text-align: center;
    font-size: 13px;
}

.extra-links a {
    color: var(--text-color);
    text-decoration: underline;
    margin: 0 5px;
}

.toggle-mode {
    text-align: center;
    margin-top: 15px;
    cursor: pointer;
    font-size: 13px;
}

.message {
    text-align: center;
    margin-bottom: 10px;
    font-size: 14px;
    color: #ffb4b4;
}
</style>
</head>

<body>

<div class="portal-container">

    <div class="college-header">
        <h1>MGR COLLEGE</h1>
        <p>Create Student Account</p>
    </div>

    <div class="login-card">

        <?php if($message != "") echo "<div class='message'>".htmlspecialchars($message)."</div>"; ?>

        <form method="POST">
            <div class="input-group">
                <label>Register Number</label>
                <input type="text" name="register_number" required>
            </div>

            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="name" required>
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Create Password</label>
                <input type="password" name="password" required>
            </div>

            <button class="login-btn" type="submit" name="register">Create Account</button>
        </form>

        <div class="extra-links">
            <a href="school.php">Back to Login</a>
        </div>

        <div class="toggle-mode" onclick="toggleDarkMode()">
            🌙 Toggle Dark Mode
        </div>
    </div>

</div>

<script>
function toggleDarkMode() {
    document.body.classList.toggle("dark");
    localStorage.setItem("theme",
        document.body.classList.contains("dark") ? "dark" : "light"
    );
}

if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark");
}
</script>

</body>
</html>
