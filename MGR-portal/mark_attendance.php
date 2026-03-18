<?php
// mark_attendance.php
include "db.php";
session_start();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// -------------------------
// 1) AUTH (teacher)
// -------------------------
$current_teacher_id = 0;
if (isset($_SESSION['teacher_id'])) $current_teacher_id = (int)$_SESSION['teacher_id'];
else if (isset($_SESSION['id'])) $current_teacher_id = (int)$_SESSION['id']; // fallback
else if (isset($_SESSION['user_id'])) $current_teacher_id = (int)$_SESSION['user_id'];

if ($current_teacher_id <= 0) {
    // not logged in as teacher
    header("Location: school.php");
    exit();
}

// -------------------------
// 2) GET slot_id
// -------------------------
$slot_id = (int)($_GET['slot_id'] ?? 0);
if ($slot_id <= 0) {
    die("Invalid slot_id. Go back and open attendance from teacher periods page.");
}

// -------------------------
// 3) Load slot details from timetable
// -------------------------
$slot = null;

$stmt = $conn->prepare("
    SELECT 
        tt.Id AS slot_id,
        tt.Day_of_week,
        tt.Period_id,
        tt.Subject_id,
        tt.Teacher_id,
        tt.Note,
        p.Period_no,
        p.Period_name,
        p.Start_time,
        p.End_time,
        s.subject_code,
        s.subject_name,
        t.Name AS teacher_name
    FROM timetable tt
    JOIN periods p ON p.Id = tt.Period_id
    JOIN subjects s ON s.id = tt.Subject_id
    JOIN teachers t ON t.Id = tt.Teacher_id
    WHERE tt.Id = ?
    LIMIT 1
");
$stmt->bind_param("i", $slot_id);
$stmt->execute();
$res = $stmt->get_result();
$slot = $res->fetch_assoc();
$stmt->close();

if (!$slot) {
    die("Slot not found in timetable. Check admin timetable.");
}

// IMPORTANT: Only the assigned teacher can mark this slot
if ((int)$slot['Teacher_id'] !== $current_teacher_id) {
    die("Access denied. This slot does not belong to your account.");
}

// slot values used for attendance
$subject_id = (int)$slot['Subject_id'];
$period_id  = (int)$slot['Period_id'];
$teacher_id = (int)$slot['Teacher_id'];

// -------------------------
// 4) Date
// -------------------------
$date = $_GET['date'] ?? date("Y-m-d");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date("Y-m-d");

// -------------------------
// 5) Load students list
// -------------------------
$students = [];
// If you have class/department filters, we can add later. For now: all students.
$r = $conn->query("SELECT Id, Name FROM students ORDER BY Name ASC");
if ($r) {
    while($row = $r->fetch_assoc()) $students[] = $row;
}

// -------------------------
// 6) Load existing attendance for this slot/date (so it shows checked)
// -------------------------
$existing = []; // student_id => status
$stmt = $conn->prepare("
    SELECT student_id, status
    FROM attendance
    WHERE subject_id = ? AND period_id = ? AND date = ?
");
$stmt->bind_param("iis", $subject_id, $period_id, $date);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $existing[(int)$row['student_id']] = $row['status'];
}
$stmt->close();

// -------------------------
// 7) SAVE attendance (POST)
// -------------------------
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $date_post = $_POST['date'] ?? $date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_post)) $date_post = date("Y-m-d");

    $attendance = $_POST['attendance'] ?? [];

    if (!is_array($attendance) || count($attendance) === 0) {
        $msg = "⚠️ No attendance selected.";
    } else {

        // ✅ teacher_id INCLUDED now (this is the main fix)
        $ins = $conn->prepare("
            INSERT INTO attendance (student_id, subject_id, teacher_id, period_id, date, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                teacher_id = VALUES(teacher_id)
        ");

        $saved = 0;

        foreach ($attendance as $sid => $status) {
            $student_id = (int)$sid;
            if ($student_id <= 0) continue;

            $status = ($status === "Present") ? "Present" : "Absent";

            $ins->bind_param("iiiiss", $student_id, $subject_id, $teacher_id, $period_id, $date_post, $status);

            if ($ins->execute()) $saved++;
        }

        $ins->close();

        $msg = "✅ Attendance saved for $saved students.";

        // reload existing after save
        $existing = [];
        $stmt = $conn->prepare("
            SELECT student_id, status
            FROM attendance
            WHERE subject_id = ? AND period_id = ? AND date = ?
        ");
        $stmt->bind_param("iis", $subject_id, $period_id, $date_post);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            $existing[(int)$row['student_id']] = $row['status'];
        }
        $stmt->close();

        $date = $date_post;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>MGR Teacher - Mark Attendance</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#0b5aa6;
  --bg2:#0a7bd8;
  --t:#ffffff;
  --card: rgba(255,255,255,.14);
  --line: rgba(255,255,255,.18);
}
body.dark{
  --bg1:#0b1020;
  --bg2:#0f1b3d;
  --t:#f9fafb;
  --card: rgba(0,0,0,.45);
  --line: rgba(255,255,255,.10);
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{
  min-height:100vh;color:var(--t);
  background:linear-gradient(135deg,var(--bg1),var(--bg2));
  padding:22px;
}
.wrap{max-width:1100px;margin:0 auto;}
.top{
  display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
  border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);
}
.btn{
  padding:10px 14px;border-radius:12px;border:1px solid var(--line);
  background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;
}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{
  margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
  padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);
}
.small{font-size:13px;opacity:.9;margin-top:6px;line-height:1.4;}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.12);margin-bottom:12px;}
.row{display:flex;gap:12px;flex-wrap:wrap;align-items:end;}
label{font-size:13px;opacity:.9;display:block;margin-top:10px;}
input[type="date"]{padding:10px 12px;border-radius:12px;border:none;outline:none;}
table{width:100%;border-collapse:collapse;margin-top:12px;border-radius:14px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;font-size:14px;}
th{color:rgba(255,255,255,.75);font-size:13px;background:rgba(0,0,0,.12);}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);background:rgba(0,0,0,.12);font-size:12px;}
.pillBtn{display:inline-flex;gap:10px;align-items:center;flex-wrap:wrap;}
.radioWrap{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.radioWrap label{margin:0;}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h2>MGR COLLEGE</h2>
      <div class="small">
        Mark Attendance • <?= h($slot['Day_of_week']) ?> •
        Period <?= h($slot['Period_no']) ?> (<?= h($slot['Start_time']) ?> - <?= h($slot['End_time']) ?>)
        <br>
        <span class="badge"><?= h($slot['subject_code']) ?> - <?= h($slot['subject_name']) ?></span>
        <span class="badge">Teacher: <?= h($slot['teacher_name']) ?></span>
      </div>
    </div>
    <div class="pillBtn">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="teacher_periods.php">← Back</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <?php if($msg): ?>
      <div class="msg"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="row">
        <div>
          <label>Date</label>
          <input type="date" name="date" value="<?= h($date) ?>">
        </div>
        <div class="small">
          Click Save after marking. (teacher_id will be stored automatically now ✅)
        </div>
        <div style="margin-left:auto;">
          <button class="btn" type="submit">💾 Save Attendance</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Student</th>
            <th style="width:260px;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if(count($students) === 0): ?>
            <tr><td colspan="3" style="opacity:.8;">No students found.</td></tr>
          <?php else: ?>
            <?php foreach($students as $st): 
              $sid = (int)$st['Id'];
              $cur = $existing[$sid] ?? "Absent";
            ?>
              <tr>
                <td><?= $sid ?></td>
                <td><?= h($st['Name']) ?></td>
                <td>
                  <div class="radioWrap">
                    <label>
                      <input type="radio" name="attendance[<?= $sid ?>]" value="Present" <?= ($cur==="Present")?'checked':''; ?>>
                      Present
                    </label>
                    <label>
                      <input type="radio" name="attendance[<?= $sid ?>]" value="Absent" <?= ($cur!=="Present")?'checked':''; ?>>
                      Absent
                    </label>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </form>
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