<?php
include "db.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$adminName = $_SESSION['admin_name'] ?? "Main Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{min-height:100vh;color:var(--t);
background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
background-size:400% 400%;animation:bg 12s ease infinite;padding:22px;}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1100px;margin:0 auto;}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);
background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:14px;}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;
backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);}
.small{font-size:13px;opacity:.9;margin-top:6px;line-height:1.4;}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h2>MGR COLLEGE • Admin Dashboard</h2>
      <div class="small">Welcome, <?= htmlspecialchars($adminName) ?> 👑</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Manage Timetable</h3>
      <div class="small">Add/Edit day-wise timetable slots (MON–SAT) with periods, subjects & teacher mapping.</div>
      <div style="margin-top:12px;">
        <a class="btn" href="admin_timetable.php">Open</a>
      </div>
    </div>

    <div class="card">
      <h3>Manage Subjects</h3>
      <div class="small">Add/Edit subject code & subject name easily.</div>
      <div style="margin-top:12px;">
        <a class="btn" href="admin_subjects.php">Open</a>
      </div>
    </div>

    <div class="card">
      <h3>Attendance Report</h3>
      <div class="small">View attendance records by date/subject/period/teacher/student.</div>
      <div style="margin-top:12px;">
        <a class="btn" href="admin_attendance.php">Open</a>
      </div>
    </div>
    
    <div class="card">
    <h3>Manage Users</h3>
    <div class="small">View / Edit / Delete students & teachers.</div>
    <div style="margin-top:12px;">
        <a class="btn" href="admin_users.php">Open</a>
    </div>
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