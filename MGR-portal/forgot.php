<?php
include "db.php";
session_start();

$message = "";

if (isset($_POST['reset'])) {

    $identifier = trim($_POST['identifier']); // student regno OR teacher email
    $email = trim($_POST['email']);
    $newpass = $_POST['new_password'];

    if ($identifier === "" || $email === "" || $newpass === "") {
        $message = "Please fill all fields.";
    } else {

        $hashed = password_hash($newpass, PASSWORD_DEFAULT);

        // Teacher reset if identifier contains "@"
        if (strpos($identifier, "@") !== false) {

            $stmt = $conn->prepare("SELECT id FROM teachers WHERE email=?");
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                // For teacher, identifier IS the email, so match email==email
                if (strcasecmp($identifier, $email) !== 0) {
                    $message = "Email mismatch. Enter the same registered email.";
                } else {
                    $upd = $conn->prepare("UPDATE teachers SET password=? WHERE email=?");
                    $upd->bind_param("ss", $hashed, $identifier);
                    if ($upd->execute()) $message = "Password updated! Now login.";
                    else $message = "Error updating password.";
                }
            } else {
                $message = "Teacher account not found.";
            }

        } else {
            // Student reset: identifier is register number, email must match DB
            $stmt = $conn->prepare("SELECT id, email FROM students WHERE register_number=?");
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();

                if (strcasecmp($row['email'], $email) !== 0) {
                    $message = "Email mismatch. Enter the email used during registration.";
                } else {
                    $upd = $conn->prepare("UPDATE students SET password=? WHERE register_number=?");
                    $upd->bind_param("ss", $hashed, $identifier);
                    if ($upd->execute()) $message = "Password updated! Now login.";
                    else $message = "Error updating password.";
                }
            } else {
                $message = "Student register number not found.";
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
<title>MGR College - Forgot Password</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #4f46e5;
    --secondary: #06b6d4;
    --text-color: white;
    --card-bg: rgba(255,255,255,0.15);
    --muted: rgba(255,255,255,0.75);
    --line: rgba(255,255,255,0.18);
}

body.dark {
    --primary: #111827;
    --secondary: #1f2937;
    --text-color: #f9fafb;
    --card-bg: rgba(0,0,0,0.55);
    --muted: rgba(249,250,251,0.75);
    --line: rgba(249,250,251,0.16);
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
    padding: 22px;
}

@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

.portal-container { width: 100%; max-width: 420px; }

.college-header { text-align: center; margin-bottom: 18px; }
.college-header h1 { font-weight: 600; }
.college-header p { font-size: 13px; color: var(--muted); }

.card {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    padding: 32px;
    border: 1px solid var(--line);
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}

.input-group { margin-bottom: 14px; }
label { font-size: 13px; color: var(--muted); }
input {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: rgba(255,255,255,0.10);
    color: var(--text-color);
    margin-top: 6px;
    outline: none;
}

.btn {
    width: 100%;
    padding: 11px;
    border-radius: 12px;
    border: none;
    background: white;
    color: var(--primary);
    font-weight: 600;
    cursor: pointer;
    transition: 0.25s;
}
.btn:hover { background: #e0e7ff; transform: translateY(-1px); }

.extra {
    margin-top: 14px;
    text-align: center;
    font-size: 13px;
}
.extra a {
    color: var(--text-color);
    text-decoration: underline;
    margin: 0 6px;
}

.toggle {
    text-align: center;
    margin-top: 12px;
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
      <p>Reset Password (Student / Teacher)</p>
  </div>

  <div class="card">
      <?php if($message != "") echo "<div class='message'>".htmlspecialchars($message)."</div>"; ?>

      <form method="POST">
          <div class="input-group">
              <label>Register Number (Student) / Email (Teacher)</label>
              <input type="text" name="identifier" required>
          </div>

          <div class="input-group">
              <label>Registered Email</label>
              <input type="email" name="email" required>
          </div>

          <div class="input-group">
              <label>New Password</label>
              <input type="password" name="new_password" required>
          </div>

          <button class="btn" type="submit" name="reset">Update Password</button>
      </form>

      <div class="extra">
          <a href="school.php">Back to Login</a>
      </div>

      <div class="toggle" onclick="toggleDarkMode()">🌙 Dark</div>
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
