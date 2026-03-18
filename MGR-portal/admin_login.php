<?php
include "db.php";
session_start();

$message = "";

// If already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password FROM admins WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $admin = $res->fetch_assoc();

        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $message = "Wrong password!";
        }
    } else {
        $message = "Admin email not found!";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>MGR Admin Login</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #4f46e5;
    --secondary: #06b6d4;
    --text-color: white;
    --card-bg: rgba(255,255,255,0.15);
    --line: rgba(255,255,255,0.18);
}

body.dark {
    --primary: #111827;
    --secondary: #1f2937;
    --text-color: #f9fafb;
    --card-bg: rgba(0,0,0,0.5);
    --line: rgba(255,255,255,0.10);
}

* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

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
    padding: 16px;
}

@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

.portal-container { width: 100%; max-width: 420px; }

.college-header { text-align: center; margin-bottom: 18px; }
.college-header h1 { font-weight: 600; letter-spacing: .6px; }
.college-header p { font-size: 13px; opacity: 0.9; margin-top: 6px; }

.card {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid var(--line);
}

.message {
    text-align: center;
    margin-bottom: 12px;
    font-size: 14px;
    color: #ffd0d0;
}

.input-group { margin-bottom: 14px; }
.input-group label { font-size: 13px; opacity: .95; }
.input-group input {
    width: 100%;
    padding: 11px 12px;
    border-radius: 10px;
    border: none;
    margin-top: 6px;
    outline: none;
}

.btn {
    width: 100%;
    padding: 11px;
    border-radius: 10px;
    border: none;
    background: white;
    color: var(--primary);
    font-weight: 700;
    cursor: pointer;
    transition: 0.25s;
}
.btn:hover { background: #e0e7ff; }

.bottom {
    display:flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}
.smallbtn{
    flex: 1;
    text-align:center;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: rgba(255,255,255,0.12);
    color: var(--text-color);
    cursor: pointer;
    user-select: none;
}
.smallbtn:hover { background: rgba(255,255,255,0.20); }
.smallbtn a{ color:inherit; text-decoration:none; display:block; }
</style>
</head>

<body>
<div class="portal-container">

    <div class="college-header">
        <h1>MGR COLLEGE</h1>
        <p>Admin Panel Login</p>
    </div>

    <div class="card">
        <?php if($message!="") echo "<div class='message'>".htmlspecialchars($message)."</div>"; ?>

        <form method="POST">
            <div class="input-group">
                <label>Admin Email</label>
                <input type="email" name="email" required placeholder="admin@mgr.com">
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>

            <button class="btn" type="submit" name="login">Login</button>
        </form>

        <div class="bottom">
            <div class="smallbtn" onclick="toggleDarkMode()">🌙 Dark</div>
            <div class="smallbtn"><a href="school.php">⬅ Portal</a></div>
        </div>
    </div>

</div>

<script>
function toggleDarkMode(){
    document.body.classList.toggle("dark");
    localStorage.setItem("mgr_theme", document.body.classList.contains("dark") ? "dark" : "light");
}
if(localStorage.getItem("mgr_theme")==="dark"){ document.body.classList.add("dark"); }
</script>
</body>
</html>