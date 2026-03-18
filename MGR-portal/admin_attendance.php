<?php
// admin_attendance.php  (REPLACE FULL FILE)

include "db.php";
session_start();

/* ✅ Admin session check (kept flexible for your project keys) */
$isAdmin =
    isset($_SESSION['admin_id']) ||
    isset($_SESSION['admin']) ||
    isset($_SESSION['admin_email']) ||
    isset($_SESSION['admin_name']) ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$isAdmin) {
    header("Location: admin_login.php");
    exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------- Load dropdown data ---------- */
$subjects = [];
$periods  = [];
$teachers = [];
$students = [];

if ($r = $conn->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")) {
    while($row = $r->fetch_assoc()) $subjects[] = $row;
    $r->close();
}

/* Your table column is probably period_name (NOT label) */
if ($r = $conn->query("SELECT id, period_name FROM periods ORDER BY id ASC")) {
    while($row = $r->fetch_assoc()) $periods[] = $row;
    $r->close();
}

if ($r = $conn->query("SELECT id, Name FROM teachers ORDER BY Name ASC")) {
    while($row = $r->fetch_assoc()) $teachers[] = $row;
    $r->close();
}

if ($r = $conn->query("SELECT id, Name FROM students ORDER BY Name ASC")) {
    while($row = $r->fetch_assoc()) $students[] = $row;
    $r->close();
}

/* ---------- Filters ---------- */
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$monthVal = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthVal)) $monthVal = date('Y-m');

$subject_id = (int)($_GET['subject_id'] ?? 0);
$period_id  = (int)($_GET['period_id'] ?? 0);
$teacher_id = (int)($_GET['teacher_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- Query attendance rows for the selected DAY ---------- */
$where = ["a.date = ?"];
$types = "s";
$params = [$date];

if ($subject_id > 0) { $where[] = "a.subject_id = ?"; $types .= "i"; $params[] = $subject_id; }
if ($period_id  > 0) { $where[] = "a.period_id  = ?"; $types .= "i"; $params[] = $period_id;  }
if ($teacher_id > 0) { $where[] = "a.teacher_id = ?"; $types .= "i"; $params[] = $teacher_id; }
if ($student_id > 0) { $where[] = "a.student_id = ?"; $types .= "i"; $params[] = $student_id; }

$sql = "
SELECT
    a.id,
    a.date,
    a.status,
    s.id AS student_id,
    s.Name AS student_name,
    t.id AS teacher_id,
    t.Name AS teacher_name,
    sub.id AS subject_id,
    sub.subject_name AS subject_name,
    p.id AS period_id,
    p.period_name AS period_label
FROM attendance a
LEFT JOIN students s ON s.id = a.student_id
LEFT JOIN teachers t ON t.id = a.teacher_id
LEFT JOIN subjects sub ON sub.id = a.subject_id
LEFT JOIN periods p ON p.id = a.period_id
WHERE " . implode(" AND ", $where) . "
ORDER BY p.id ASC, s.Name ASC, a.id DESC
";

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin - Attendance Report</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root{
  --bg1:#a53bd6;
  --bg2:#6a2bd9;
  --card:rgba(255,255,255,.16);
  --line:rgba(255,255,255,.22);
  --txt:#fff;
  --muted:rgba(255,255,255,.75);
  --shadow:0 18px 40px rgba(0,0,0,.18);
}
body{
  margin:0;
  font-family:Poppins,system-ui,Arial;
  color:var(--txt);
  background:linear-gradient(135deg,var(--bg1),var(--bg2));
  min-height:100vh;
}
body.dark{
  --bg1:#0f172a;
  --bg2:#111827;
  --card:rgba(255,255,255,.06);
  --line:rgba(255,255,255,.12);
  --txt:#e5e7eb;
  --muted:rgba(229,231,235,.7);
}
.wrap{max-width:1100px;margin:0 auto;padding:28px 14px;}
.top{
  background:var(--card);
  border:1px solid var(--line);
  backdrop-filter:blur(16px);
  border-radius:18px;
  padding:18px 18px;
  box-shadow:var(--shadow);
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
}
h1{margin:0;font-size:22px;font-weight:600;}
.small{font-size:13px;opacity:.85;margin-top:6px;line-height:1.4;}
.btn{
  padding:10px 14px;
  border-radius:12px;
  background:rgba(255,255,255,.14);
  border:1px solid var(--line);
  color:var(--txt);
  text-decoration:none;
  cursor:pointer;
  display:inline-block;
  font-size:14px;
  user-select:none;
}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{
  margin-top:14px;
  background:var(--card);
  border:1px solid var(--line);
  backdrop-filter:blur(16px);
  border-radius:18px;
  padding:16px;
  box-shadow:var(--shadow);
}
.grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:12px;
}
@media(max-width:900px){ .grid{grid-template-columns:1fr;} }

label{font-size:13px;opacity:.9;display:block;margin-bottom:6px;}
input,select{
  width:100%;
  padding:11px 12px;
  border-radius:12px;
  border:1px solid var(--line);
  background:rgba(0,0,0,.08);
  color:var(--txt);
  outline:none;
}
body.dark input, body.dark select{background:rgba(255,255,255,.06);}

.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:flex-start;margin-top:12px;}
.badge{
  display:inline-block;
  padding:8px 10px;
  border-radius:12px;
  background:rgba(0,0,0,.10);
  border:1px solid var(--line);
  font-size:13px;
}
table{
  width:100%;
  border-collapse:collapse;
  margin-top:12px;
  overflow:hidden;
  border-radius:14px;
}
th,td{
  padding:12px 10px;
  border-bottom:1px solid rgba(255,255,255,.12);
  text-align:left;
  font-size:14px;
}
th{opacity:.9;background:rgba(0,0,0,.08);}
.status{
  padding:6px 10px;
  border-radius:999px;
  display:inline-block;
  border:1px solid var(--line);
  font-size:13px;
}
.status.present{background:rgba(34,197,94,.18);}
.status.absent{background:rgba(239,68,68,.18);}
.status.other{background:rgba(234,179,8,.18);}
</style>
</head>

<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1>Admin • Attendance Report</h1>
      <div class="small">Filter and view attendance records.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="admin_dashboard.php">← Dashboard</a>
      <a class="btn" href="admin_logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <form method="GET" action="admin_attendance.php">
      <div class="grid">
        <div>
          <label>Date (Day view)</label>
          <input type="date" name="date" value="<?= h($date) ?>">
        </div>

        <div>
          <label>Month (Month export)</label>
          <input type="month" name="month" value="<?= h($monthVal) ?>">
        </div>

        <div>
          <label>Subject</label>
          <select name="subject_id">
            <option value="0">All</option>
            <?php foreach($subjects as $sub): ?>
              <option value="<?= (int)$sub['id'] ?>" <?= $subject_id==(int)$sub['id']?'selected':'' ?>>
                <?= h($sub['subject_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Period</label>
          <select name="period_id">
            <option value="0">All</option>
            <?php foreach($periods as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $period_id==(int)$p['id']?'selected':'' ?>>
                <?= h($p['period_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Teacher</label>
          <select name="teacher_id">
            <option value="0">All</option>
            <?php foreach($teachers as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $teacher_id==(int)$t['id']?'selected':'' ?>>
                <?= h($t['Name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Student</label>
          <select name="student_id">
            <option value="0">All</option>
            <?php foreach($students as $st): ?>
              <option value="<?= (int)$st['id'] ?>" <?= $student_id==(int)$st['id']?'selected':'' ?>>
                <?= h($st['Name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">🔎 Apply</button>
        <a class="btn" href="admin_attendance.php">♻️ Reset</a>

        <?php if (count($rows) > 0): ?>
          <a class="btn"
             href="export_attendance_csv.php?mode=day&date=<?= urlencode($date) ?>&month=<?= urlencode($monthVal) ?>&subject_id=<?= (int)$subject_id ?>&period_id=<?= (int)$period_id ?>&teacher_id=<?= (int)$teacher_id ?>&student_id=<?= (int)$student_id ?>">
            ⬇️ Day CSV
          </a>

          <a class="btn"
             href="export_attendance_csv.php?mode=month&date=<?= urlencode($date) ?>&month=<?= urlencode($monthVal) ?>&subject_id=<?= (int)$subject_id ?>&period_id=<?= (int)$period_id ?>&teacher_id=<?= (int)$teacher_id ?>&student_id=<?= (int)$student_id ?>">
            ⬇️ Month CSV
          </a>
        <?php else: ?>
          <span class="badge">No attendance data to export</span>
        <?php endif; ?>
      </div>
    </form>

    <div style="margin-top:12px;">
      <span class="badge">Rows: <?= count($rows) ?></span>
    </div>

    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Period</th>
          <th>Subject</th>
          <th>Teacher</th>
          <th>Student</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($rows) === 0): ?>
        <tr><td colspan="6" style="opacity:.85;">No records found for selected filters.</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <?php
            $st = strtolower((string)$r['status']);
            $cls = ($st === 'present') ? 'present' : (($st === 'absent') ? 'absent' : 'other');
          ?>
          <tr>
            <td><?= h($r['date']) ?></td>
            <td><?= h($r['period_label'] ?? '-') ?></td>
            <td><?= h($r['subject_name'] ?? '-') ?></td>
            <td><?= h($r['teacher_name'] ?? '-') ?></td>
            <td><?= h($r['student_name'] ?? '-') ?></td>
            <td><span class="status <?= $cls ?>"><?= h($r['status'] ?? '-') ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function toggleDarkMode(){
  document.body.classList.toggle("dark");
  localStorage.setItem("mgr_theme", document.body.classList.contains("dark") ? "dark" : "light");
}
if(localStorage.getItem("mgr_theme")==="dark"){
  document.body.classList.add("dark");
}
</script>
</body>
</html>