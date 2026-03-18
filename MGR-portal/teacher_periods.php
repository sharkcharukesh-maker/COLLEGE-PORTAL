<?php
include "db.php";
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "teacher") {
    header("Location: school.php");
    exit();
}

$teacher_id = (int)($_SESSION['id'] ?? 0);
$day = strtoupper(date("D")); // MON, TUE, WED...

$stmt = $conn->prepare("
    SELECT tt.id AS slot_id, p.period_no, p.period_name, s.subject_name
    FROM timetable tt
    JOIN periods p ON p.id = tt.period_id
    LEFT JOIN subjects s ON s.id = tt.subject_id
    WHERE tt.teacher_id=? AND tt.day_of_week=?
    ORDER BY p.period_no
");
$stmt->bind_param("is", $teacher_id, $day);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Teacher Periods</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root{
  --bg1:#0ea5e9; --bg2:#1d4ed8; --bg3:#06b6d4;
  --glass: rgba(255,255,255,0.14);
  --line: rgba(255,255,255,0.18);
  --text:#ffffff; --muted: rgba(255,255,255,0.78);
  --btnbg: rgba(255,255,255,0.18);
  --btnhover: rgba(255,255,255,0.28);
}
body.dark{
  --bg1:#0b1220; --bg2:#111827; --bg3:#1f2937;
  --glass: rgba(0,0,0,0.40);
  --line: rgba(255,255,255,0.10);
  --text:#f9fafb; --muted: rgba(249,250,251,0.75);
  --btnbg: rgba(255,255,255,0.10);
  --btnhover: rgba(255,255,255,0.16);
}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Poppins',sans-serif;}
body{
  min-height:100vh; color:var(--text);
  background: radial-gradient(1000px 600px at 10% 10%, rgba(255,255,255,0.14), transparent 60%),
              radial-gradient(900px 600px at 90% 20%, rgba(255,255,255,0.10), transparent 60%),
              linear-gradient(120deg, var(--bg1), var(--bg2), var(--bg3));
  display:flex; justify-content:center; padding:28px 16px;
}
.container{width:100%;max-width:1000px;}
.topbar{
  display:flex;justify-content:space-between;gap:16px;
  padding:18px;border-radius:16px;
  background:var(--glass); border:1px solid var(--line);
  backdrop-filter: blur(16px);
  box-shadow: 0 18px 40px rgba(0,0,0,0.22);
  margin-bottom:16px;
}
.brand h1{font-size:24px;font-weight:600;}
.brand p{margin-top:4px;font-size:13px;color:var(--muted);}
.actions{display:flex;gap:10px;}
.btn,a.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:10px 14px;border-radius:10px;
  border:1px solid var(--line);
  background:var(--btnbg); color:var(--text);
  cursor:pointer;text-decoration:none;font-size:13px;
  transition:0.2s; user-select:none; white-space:nowrap;
}
.btn:hover,a.btn:hover{background:var(--btnhover);transform:translateY(-1px);}
.card{
  background:var(--glass); border:1px solid var(--line);
  border-radius:16px; padding:18px;
  backdrop-filter: blur(16px);
  box-shadow: 0 18px 40px rgba(0,0,0,0.18);
}
.card h2{font-size:18px;font-weight:600;margin-bottom:6px;}
.help{font-size:13px;color:var(--muted);margin-bottom:14px;line-height:1.4;}
.table{width:100%;border-collapse:separate;border-spacing:0 10px;}
.row{
  background: rgba(255,255,255,0.10);
  border:1px solid var(--line);
  border-radius:12px;
}
.table th{font-size:12px;color:var(--muted);text-align:left;padding:8px 10px;}
.table td{padding:14px 10px;}
.badge{
  display:inline-block;padding:6px 10px;border-radius:999px;
  background:rgba(255,255,255,0.12);border:1px solid var(--line);
  font-size:12px;color:var(--text);
}
.empty{padding:14px;border:1px dashed var(--line);border-radius:12px;color:var(--muted);}
</style>
</head>

<body>
<div class="container">
  <div class="topbar">
    <div class="brand">
      <h1>MGR COLLEGE</h1>
      <p>Teacher Dashboard • Today’s Periods (<?php echo $day; ?>)</p>
    </div>
    <div class="actions">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="teacher_dashboard.php">⬅ Back</a>
    </div>
  </div>

  <div class="card">
    <h2>Today’s Periods</h2>
    <div class="help">Click “Mark Attendance” for the correct period. It will open with slot_id automatically.</div>

    <?php if($result->num_rows==0){ ?>
      <div class="empty">No timetable set for today (<?php echo $day; ?>). Add rows in timetable for this day.</div>
    <?php } else { ?>
      <table class="table">
        <thead>
          <tr>
            <th>Period</th>
            <th>Subject</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()){ ?>
          <tr class="row">
            <td><span class="badge"><?php echo (int)$row['period_no']; ?></span> <?php echo htmlspecialchars($row['period_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['subject_name'] ?? 'Not Set'); ?></td>
            <td>
              <a class="btn" href="mark_attendance.php?slot_id=<?php echo (int)$row['slot_id']; ?>">✅ Mark Attendance</a>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    <?php } ?>

  </div>
</div>

<script>
function toggleDarkMode(){
  document.body.classList.toggle("dark");
  localStorage.setItem("mgr_theme", document.body.classList.contains("dark") ? "dark" : "light");
}
(function(){
  if(localStorage.getItem("mgr_theme")==="dark") document.body.classList.add("dark");
})();
</script>
</body>
</html>
