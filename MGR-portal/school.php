<?php
include "db.php";
session_start();

$message = "";

if (!isset($_SESSION['role']) && isset($_COOKIE['remember_role']) && isset($_COOKIE['remember_id'])) {

    $_SESSION['role'] = $_COOKIE['remember_role'];
    $_SESSION['id']   = $_COOKIE['remember_id'];

    if ($_SESSION['role'] === "teacher") {
        header("Location: teacher_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}


/* ===============================
   2️⃣ LOGIN PROCESS
================================ */

if (isset($_POST['login'])) {

    $identifier = trim($_POST['register_number']);  // Student RegNo OR Teacher Email
    $password   = $_POST['password'];

    // If teacher selected OR identifier contains "@"
    $login_type = isset($_POST['login_type']) ? $_POST['login_type'] : "student";
    $isTeacher  = ($login_type === "teacher") || (strpos($identifier, "@") !== false);

    /* ---------- TEACHER LOGIN ---------- */
    if ($isTeacher) {

        $stmt = $conn->prepare("SELECT id, password FROM teachers WHERE email=?");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $teacher = $result->fetch_assoc();

            if (password_verify($password, $teacher['password'])) {

                $_SESSION['role'] = "teacher";
                $_SESSION['id']   = $teacher['id'];

                if (isset($_POST['remember'])) {
                    setcookie("remember_role", "teacher", time() + (86400 * 30), "/");
                    setcookie("remember_id", $teacher['id'], time() + (86400 * 30), "/");
                }

                header("Location: teacher_dashboard.php");
                exit();

            } else {
                $message = "Wrong Password!";
            }

        } else {
            $message = "Teacher not found!";
        }

    }
    /* ---------- STUDENT LOGIN ---------- */
    else {

        $stmt = $conn->prepare("SELECT id, password FROM students WHERE register_number=? AND is_active=1");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $student = $result->fetch_assoc();

            if (password_verify($password, $student['password'])) {

                $_SESSION['role'] = "student";
                $_SESSION['id']   = $student['id'];

                if (isset($_POST['remember'])) {
                    setcookie("remember_role", "student", time() + (86400 * 30), "/");
                    setcookie("remember_id", $student['id'], time() + (86400 * 30), "/");
                }

                header("Location: dashboard.php");
                exit();

            } else {
                $message = "Wrong Password!";
            }

        } else {
            $message = "Register Number not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MGR College Portal</title>

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

.remember {
    display: flex;
    align-items: center;
    font-size: 13px;
    margin-bottom: 15px;
    gap: 10px;
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
        <p>Student & Faculty Management Portal</p>
    </div>

    <div class="login-card">

        <?php if($message != "") echo "<div class='message'>".htmlspecialchars($message)."</div>"; ?>

        <form method="POST">

            <!-- Student / Teacher Toggle -->
            <div class="remember" style="justify-content: space-between;">
                <label style="display:flex; align-items:center; gap:8px;">
                    <input type="radio" name="login_type" value="student" checked onclick="setLoginType('student')">
                    Student
                </label>
                <label style="display:flex; align-items:center; gap:8px;">
                    <input type="radio" name="login_type" value="teacher" onclick="setLoginType('teacher')">
                    Teacher
                </label>
            </div>

            <div class="input-group">
                <label id="loginLabel">Register Number</label>
                <input type="text" name="register_number" id="loginInput" placeholder="Enter Register Number" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="remember">
                <input type="checkbox" id="rememberMe" name="remember">
                <label for="rememberMe">Remember Me</label>
            </div>

            <button class="login-btn" type="submit" name="login">Login</button>
        </form>

        <div class="extra-links">
            <a href="register.php">Student Create Account</a> |
            <a href="teacher_register.php">Teacher Create Account</a> |
            <a href="forgot.php">Forgot Password?</a>
        </div>

        <div class="toggle-mode" onclick="toggleDarkMode()">
            🌙 Dark
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

// Toggle label + placeholder
function setLoginType(type){
    const label = document.getElementById("loginLabel");
    const input = document.getElementById("loginInput");

    if(type === "teacher"){
        label.innerText = "Teacher Email";
        input.placeholder = "Enter Teacher Email";
        input.type = "email";
    } else {
        label.innerText = "Register Number";
        input.placeholder = "Enter Register Number";
        input.type = "text";
    }
}
setLoginType("student");
</script>

</body>
</html>
