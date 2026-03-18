<?php
include "db.php";
session_start();

// Check DB connection
if (!isset($conn)) {
    die("Database connection failed.");
}

$message = "";
$message_type = "error";

if (isset($_POST['register_teacher'])) {

    $name = trim($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    // Validation
    if ($name === "" || $email === "" || $password === "") {
        $message = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {

        // Check existing email
        $check = $conn->prepare("SELECT id FROM teachers WHERE email = ?");
        if (!$check) {
            die("SQL Error: " . $conn->error);
        }

        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();

        if ($res && $res->num_rows > 0) {
            $message = "This email is already registered!";
        } else {

            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert teacher
            $ins = $conn->prepare("INSERT INTO teachers (name, email, password, role) VALUES (?, ?, ?, 'teacher')");
            if (!$ins) {
                die("SQL Error: " . $conn->error);
            }

            $ins->bind_param("sss", $name, $email, $hashed);

            if ($ins->execute()) {
                $message = "Teacher account created! Now login.";
                $message_type = "success";
            } else {
                $message = "Error occurred. Try again.";
            }

            $ins->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MGR College Portal - Teacher Register</title>

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
* { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; }

body {
    height:100vh;
    background: linear-gradient(-45deg, var(--primary), var(--secondary), #9333ea, #ec4899);
    background-size: 400% 400%;
    animation: gradientBG 12s ease infinite;
    display:flex; justify-content:center; align-items:center;
    color: var(--text-color);
}

@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

.portal-container { width:100%; max-width:420px; }

.college-header { text-align:center; margin-bottom:20px; }
.college-header h1 { font-weight:600; }

.login-card {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.input-group { margin-bottom: 15px; }

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
}

.login-btn:hover { background: #e0e7ff; }

.extra-links {
    margin-top: 15px;
    text-align: center;
    font-size: 13px;
}

.extra-links a {
    color: var(--text-color);
    text-decoration: underline;
}

.toggle-mode {
    text-align:center;
    margin-top:15px;
    cursor:pointer;
    font-size:13px;
}

.message {
    text-align: center;
    margin-bottom: 10px;
    font-size: 14px;
}

.message.error { color: #ffb4b4; }
.message.success { color: #86efac; }
</style>
</head>

<body>

<div class="portal-container">

    <div class="college-header">
        <h1>MGR COLLEGE</h1>
        <p>Teacher Account Creation</p>
    </div>

    <div class="login-card">

        <?php if($message != ""): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

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

            <button class="login-btn" type="submit" name="register_teacher">
                Create Teacher Account
            </button>

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