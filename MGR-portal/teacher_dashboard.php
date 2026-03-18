<?php
include "db.php";
session_start();

// Only teacher can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "teacher") {
    header("Location: school.php");
    exit();
}

$teacher_id = (int)($_SESSION['id'] ?? 0);

// Get teacher info (optional but nice)
$teacher_name = "Teacher";
$teacher_email = "";
$stmt = $conn->prepare("SELECT name, email FROM teachers WHERE id=? LIMIT 1");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $t = $res->fetch_assoc();
    $teacher_name = $t['name'] ?? "Teacher";
    $teacher_email = $t['email'] ?? "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>MGR Teacher Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root{
  --bg1:#0ea5e9;
  --bg2:#1d4ed8;
  --bg3:#06b6d4;
  --glass: rgba(255,255,255,0.14);
  --glass2: rgba(255,255,255,0.10);
  --text:#ffffff;
  --muted: rgba(255,255,255,0.78);
  --line: rgba(255,255,255,0.18);
  --btnbg: rgba(255,255,255,0.18);
  --btnhover: rgba(255,255,255,0.28);
  --danger: rgba(255,70,70,0.22);
}

body.dark{
  --bg1:#0b1220;
  --bg2:#111827;
  --bg3:#1f2937;
  --glass: rgba(0,0,0,0.40);
  --glass2: rgba(0,0,0,0.30);
  --text:#f9fafb;
  --muted: rgba(249,250,251,0.75);
  --line: rgba(255,255,255,0.10);
  --btnbg: rgba(255,255,255,0.10);
  --btnhover: rgba(255,255,255,0.16);
  --danger: rgba(255,70,70,0.18);
}

*{box-sizing:border-box; margin:0; padding:0; font-family:'Poppins',sans-serif;}

body{
  min-height:100vh;
  color:var(--text);
  background: radial-gradient(1000px 600px at 10% 10%, rgba(255,255,255,0.14), transparent 60%),
              radial-gradient(900px 600px at 90% 20%, rgba(255,255,255,0.10), transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2), var(--bg3));
  display:flex;
  justify-content:center;
  padding:28px 16px;
}

.container{
  width:100%;
  max-width:1000px;
}

.topbar{
  display:flex;
  justify-content:space-between;
  gap:16px;
  padding:18px 18px;
  border-radius:16px;
  background:var(--glass);
  border:1px solid var(--line);
  backdrop-filter: blur(16px);
  box-shadow: 0 18px 40px rgba(0,0,0,0.22);
  margin-bottom:16px;
}

.brand h1{
  font-size:26px;
  font-weight:600;
  letter-spacing:0.5px;
}
.brand p{
  margin-top:4px;
  font-size:13px;
  color:var(--muted);
}

.actions{
  display:flex;
  align-items:flex-start;
  gap:10px;
}

.btn, a.btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:10px 14px;
  border-radius:10px;
  border:1px solid var(--line);
  background:var(--btnbg);
  color:var(--text);
  cursor:pointer;
  text-decoration:none;
  font-size:13px;
  transition:0.2s;
  user-select:none;
  white-space:nowrap;
}
.btn:hover, a.btn:hover{ background:var(--btnhover); transform: translateY(-1px); }
.btn.danger{ background: var(--danger); }

.grid{
  display:grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap:16px;
}

@media (max-width: 860px){
  .grid{ grid-template-columns:1fr; }
}

.card{
  background:var(--glass);
  border:1px solid var(--line);
  border-radius:16px;
  padding:18px;
  backdrop-filter: blur(16px);
  box-shadow: 0 18px 40px rgba(0,0,0,0.18);
}

.card h2{
  font-size:18px;
  font-weight:600;
  margin-bottom:6px;
}
.help{
  font-size:13px;
  color:var(--muted);
  margin-bottom:14px;
  line-height:1.4;
}

.kv{
  display:grid;
  grid-template-columns: 120px 1fr;
  gap:10px;
  font-size:13px;
  padding:10px 0;
  border-bottom:1px solid var(--line);
}
.kv:last-child{ border-bottom:none; padding-bottom:0; }

.big-action{
  margin-top:14px;
  display:flex;
  flex-wrap:wrap;
  gap:10px;
}

.note{
  margin-top:12px;
  font-size:12px;
  color:var(--muted);
}
</style>
</head>

<body>
<div class="container">

  <div class="topbar">
    <div class="brand">
      <h1>MGR COLLEGE</h1>
      <p>Teacher Dashboard • Mark Attendance</p>
    </div>

    <div class="actions">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn danger" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">

    <!-- MAIN CARD -->
    <div class="card">
      <h2>Attendance (BSc CS)</h2>
      <div class="help">
        Your attendance page must be opened period-wise from the timetable slot.
        Use the button below to open today’s periods and mark attendance correctly.
      </div>

      <div class="big-action">
        <a class="btn" href="teacher_periods.php">📌 Open Today Periods</a>
        <a class="btn" href="teacher_dashboard.php">🔄 Refresh</a>
      </div>

      <div class="note">
        Tip: If “No timetable set for today”, update your <b>timetable.day_of_week</b> to match today (MON/TUE/WED...).
      </div>
    </div>

    <!-- SIDE CARD -->
    <div class="card">
      <h2>Profile</h2>

      <div class="kv"><div style="color:var(--muted)">Name</div><div><?php echo htmlspecialchars($teacher_name); ?></div></div>
      <div class="kv"><div style="color:var(--muted)">Email</div><div><?php echo htmlspecialchars($teacher_email); ?></div></div>
      <div class="kv"><div style="color:var(--muted)">Role</div><div>Teacher</div></div>
      <div class="kv"><div style="color:var(--muted)">Today</div><div><?php echo date("D, Y-m-d"); ?></div></div>

      <div class="big-action">
        <a class="btn" href="teacher_periods.php">Go to Periods</a>
        <a class="btn" href="teacher_announcements.php">📢 Announcements</a>
      </div>
    </div>

  </div>

</div>

<script>
function toggleDarkMode(){
  document.body.classList.toggle("dark");
  localStorage.setItem("mgr_theme", document.body.classList.contains("dark") ? "dark" : "light");
}
(function(){
  if(localStorage.getItem("mgr_theme")==="dark"){
    document.body.classList.add("dark");
  }
})();
</script>
</body>
</html>
