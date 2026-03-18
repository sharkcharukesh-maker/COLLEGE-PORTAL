<?php
include "db.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$msg = "";
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// -------------------------
// FETCH DROPDOWN DATA
// (normalize column names to Id/Name/Email to avoid case issues)
// -------------------------
$periods = [];
$resP = $conn->query("SELECT id AS Id, period_no AS Period_no, period_name AS Period_name, start_time AS Start_time, end_time AS End_time FROM periods ORDER BY period_no ASC");
while($row = $resP->fetch_assoc()) $periods[] = $row;

$teachers = [];
$resT = $conn->query("SELECT id AS Id, name AS Name, email AS Email FROM teachers ORDER BY name ASC");
while($row = $resT->fetch_assoc()) $teachers[] = $row;

$subjects = [];
$resS = $conn->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code ASC");
while($row = $resS->fetch_assoc()) $subjects[] = $row;

$days = ["MON","TUE","WED","THU","FRI","SAT"];

// -------------------------
// EDIT MODE (Slot)
// -------------------------
$editSlot = null;
if (isset($_GET['edit'])) {
    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $st = $conn->prepare("SELECT id AS Id, day_of_week AS Day_of_week, period_id AS Period_id, subject_id AS Subject_id, teacher_id AS Teacher_id, note AS Note FROM timetable WHERE id=? LIMIT 1");
        $st->bind_param("i", $editId);
        $st->execute();
        $editSlot = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// -------------------------
// EDIT MODE (Period)
// -------------------------
$editPeriod = null;
if (isset($_GET['edit_period'])) {
    $pid = (int)($_GET['edit_period'] ?? 0);
    if ($pid > 0) {
        $st = $conn->prepare("SELECT id AS Id, period_no AS Period_no, period_name AS Period_name, start_time AS Start_time, end_time AS End_time FROM periods WHERE id=? LIMIT 1");
        $st->bind_param("i", $pid);
        $st->execute();
        $editPeriod = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// -------------------------
// SAVE / UPDATE SLOT  (✅ single clean block)
// -------------------------
if (isset($_POST['save_slot'])) {

    $slot_id   = (int)($_POST['slot_id'] ?? 0);
    $day        = trim($_POST['day_of_week'] ?? "");
    $period_id  = (int)($_POST['period_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $note       = trim($_POST['note'] ?? "");

    if ($day === "" || $period_id <= 0 || $subject_id <= 0 || $teacher_id <= 0) {
        $msg = "⚠️ Please select Day, Period, Subject, and Teacher.";
    } else {

        if ($slot_id > 0) {
            // ✅ Edit button: update by timetable.id
            $upd = $conn->prepare("UPDATE timetable SET day_of_week=?, period_id=?, subject_id=?, teacher_id=?, note=? WHERE id=?");
            // ✅ Correct bind_param (6 vars = 6 types, no spaces)
            $upd->bind_param("siiisi", $day, $period_id, $subject_id, $teacher_id, $note, $slot_id);

            if ($upd->execute()) {
                $upd->close();
                header("Location: admin_timetable.php?msg=updated");
                exit();
            } else {
                $msg = "❌ Update failed (maybe duplicate Day+Period).";
            }
            $upd->close();

        } else {
            // ✅ Normal save: insert or update by UNIQUE(day_of_week, period_id)
            $chk = $conn->prepare("SELECT id AS Id FROM timetable WHERE day_of_week=? AND period_id=? LIMIT 1");
            $chk->bind_param("si", $day, $period_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                $slot_id2 = (int)$existing['Id'];
                $upd = $conn->prepare("UPDATE timetable SET subject_id=?, teacher_id=?, note=? WHERE id=?");
                $upd->bind_param("iisi", $subject_id, $teacher_id, $note, $slot_id2);
                if ($upd->execute()) $msg = "✅ Slot updated successfully!";
                else $msg = "❌ Update failed.";
                $upd->close();
            } else {
                $ins = $conn->prepare("INSERT INTO timetable (day_of_week, period_id, subject_id, teacher_id, note) VALUES (?,?,?,?,?)");
                $ins->bind_param("siiis", $day, $period_id, $subject_id, $teacher_id, $note);
                if ($ins->execute()) $msg = "✅ Slot added successfully!";
                else $msg = "❌ Insert failed (maybe duplicate constraint).";
                $ins->close();
            }
        }
    }
}

// read msg from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $msg = "✅ Slot updated successfully!";
}

// -------------------------
// UPDATE PERIOD TIME / NAME
// -------------------------
if (isset($_POST['save_period'])) {
    $pid   = (int)($_POST['period_edit_id'] ?? 0);
    $pno   = (int)($_POST['period_no'] ?? 0);
    $pname = trim($_POST['period_name'] ?? "");
    $start = trim($_POST['start_time'] ?? "");
    $end   = trim($_POST['end_time'] ?? "");

    if ($pid <= 0 || $pno <= 0 || $pname === "" || $start === "" || $end === "") {
        $msg = "⚠️ Please fill Period No, Name, Start, End.";
    } else {
        $up = $conn->prepare("UPDATE periods SET period_no=?, period_name=?, start_time=?, end_time=? WHERE id=?");
        $up->bind_param("isssi", $pno, $pname, $start, $end, $pid);
        if ($up->execute()) {
            $up->close();
            header("Location: admin_timetable.php?msg=period_updated");
            exit();
        } else {
            $msg = "❌ Period update failed.";
        }
        $up->close();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'period_updated') {
    $msg = "✅ Period updated successfully!";
}

// -------------------------
// DELETE SLOT
// -------------------------
if (isset($_GET['delete'])) {
    $id = (int)($_GET['delete'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM timetable WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $msg = "✅ Slot deleted!";
        else $msg = "❌ Delete failed.";
        $stmt->close();
    }
}

// -------------------------
// FETCH TIMETABLE LIST
// -------------------------
$slots = [];
$sql = "
SELECT 
  tt.id AS Id,
  tt.day_of_week AS Day_of_week,
  tt.period_id AS Period_id,
  tt.subject_id AS Subject_id,
  tt.teacher_id AS Teacher_id,
  tt.note AS Note,

  p.period_no AS Period_no,
  p.period_name AS Period_name,
  p.start_time AS Start_time,
  p.end_time AS End_time,

  s.subject_code,
  s.subject_name,

  t.name AS teacher_name
FROM timetable tt
JOIN periods p ON p.id = tt.period_id
JOIN subjects s ON s.id = tt.subject_id
JOIN teachers t ON t.id = tt.teacher_id
ORDER BY FIELD(tt.day_of_week,'MON','TUE','WED','THU','FRI','SAT'), p.period_no ASC";
$res = $conn->query($sql);
if ($res) {
    while($row = $res->fetch_assoc()) $slots[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Timetable</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{min-height:100vh;color:var(--t);background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
background-size:400% 400%;animation:bg 12s ease infinite;padding:22px;}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1100px;margin:0 auto;}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);
background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.18);margin-bottom:12px;}
.grid{display:grid;grid-template-columns:1fr 1.3fr;gap:14px;}
label{font-size:13px;opacity:.9;display:block;margin-top:10px;}
select,input,textarea{width:100%;padding:10px 12px;border-radius:12px;border:none;outline:none;margin-top:6px;}
textarea{min-height:70px;resize:vertical;}
table{width:100%;border-collapse:collapse;margin-top:10px;border-radius:14px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;font-size:14px;vertical-align:top;}
th{color:rgba(255,255,255,.75);font-size:13px;background:rgba(0,0,0,.12);}
.small{font-size:13px;opacity:.9;}
.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);background:rgba(0,0,0,.12);font-size:12px;}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h2>Admin • Timetable</h2>
      <div class="small">Assign Day + Period + Subject + Teacher.</div>
    </div>
    <div class="actions">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="admin_dashboard.php">⬅ Dashboard</a>
      <a class="btn" href="admin_logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <?php if($msg) echo "<div class='msg'>".h($msg)."</div>"; ?>

    <div class="grid">
      <!-- LEFT: Add / Update slot -->
      <div>
        <h3><?= $editSlot ? "Edit Slot" : "Assign Slot" ?></h3>

        <form method="POST">
          <input type="hidden" name="slot_id" value="<?= $editSlot ? (int)$editSlot['Id'] : 0 ?>">

          <label>Day of Week</label>
          <select name="day_of_week" required>
            <option value="">-- Select Day --</option>
            <?php foreach($days as $d): ?>
              <option value="<?= h($d) ?>" <?= ($editSlot && $editSlot['Day_of_week']===$d) ? "selected" : "" ?>>
                <?= h($d) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label>Period</label>
          <select name="period_id" required>
            <option value="">-- Select Period --</option>
            <?php foreach($periods as $p): ?>
              <?php $pid = (int)$p['Id']; ?>
              <option value="<?= $pid ?>" <?= ($editSlot && (int)$editSlot['Period_id']===$pid) ? "selected" : "" ?>>
                <?= h($p['Period_no']) ?> • <?= h($p['Period_name']) ?> (<?= h($p['Start_time']) ?> - <?= h($p['End_time']) ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <label>Subject</label>
          <select name="subject_id" required>
            <option value="">-- Select Subject --</option>
            <?php foreach($subjects as $s): ?>
              <?php $sid = (int)$s['id']; ?>
              <option value="<?= $sid ?>" <?= ($editSlot && (int)$editSlot['Subject_id']===$sid) ? "selected" : "" ?>>
                <?= h($s['subject_code']) ?> • <?= h($s['subject_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label>Teacher</label>
          <select name="teacher_id" required>
            <option value="">-- Select Teacher --</option>
            <?php foreach($teachers as $t): ?>
              <?php $tid = (int)$t['Id']; ?>
              <option value="<?= $tid ?>" <?= ($editSlot && (int)$editSlot['Teacher_id']===$tid) ? "selected" : "" ?>>
                <?= h($t['Name']) ?> (<?= h($t['Email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <label>Note (optional)</label>
          <textarea name="note" placeholder="Eg: Lab / Extra class"><?= $editSlot ? h($editSlot['Note']) : "" ?></textarea>

          <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn" type="submit" name="save_slot">💾 <?= $editSlot ? "Update Slot" : "Save Slot" ?></button>
            <?php if($editSlot): ?>
              <a class="btn" href="admin_timetable.php">✖ Cancel</a>
            <?php endif; ?>
          </div>

          <div style="margin-top:10px;">
            <span class="badge">ℹ️ Same Day + Period updates automatically. Use Edit button for quick changes.</span>
          </div>
        </form>

        <!-- Manage Periods -->
        <div style="margin-top:18px;">
          <h3>Manage Period Times</h3>

          <?php if($editPeriod): ?>
            <form method="POST" style="margin-top:10px;">
              <input type="hidden" name="period_edit_id" value="<?= (int)$editPeriod['Id'] ?>">

              <label>Period No</label>
              <input type="number" name="period_no" value="<?= h($editPeriod['Period_no']) ?>" required>

              <label>Period Name</label>
              <input type="text" name="period_name" value="<?= h($editPeriod['Period_name']) ?>" required>

              <label>Start Time (HH:MM:SS)</label>
              <input type="text" name="start_time" value="<?= h($editPeriod['Start_time']) ?>" required>

              <label>End Time (HH:MM:SS)</label>
              <input type="text" name="end_time" value="<?= h($editPeriod['End_time']) ?>" required>

              <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn" type="submit" name="save_period">✅ Save Period</button>
                <a class="btn" href="admin_timetable.php">✖ Cancel</a>
              </div>
              <div style="margin-top:10px;">
                <span class="badge">Tip: use format like 09:00:00</span>
              </div>
            </form>
          <?php else: ?>
            <div class="small" style="margin-top:8px;opacity:.9;">Click Edit next to a period to change time.</div>
          <?php endif; ?>

          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Period</th>
                <th>Time</th>
                <th style="width:120px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($periods as $p): ?>
                <tr>
                  <td><?= h($p['Period_no']) ?></td>
                  <td><?= h($p['Period_name']) ?></td>
                  <td><?= h($p['Start_time']) ?> - <?= h($p['End_time']) ?></td>
                  <td>
                    <a class="btn" href="admin_timetable.php?edit_period=<?= (int)$p['Id'] ?>">✏ Edit</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

        </div>
      </div>

      <!-- RIGHT: Timetable list -->
      <div>
        <h3>Current Timetable</h3>

        <table>
          <thead>
            <tr>
              <th>Day</th>
              <th>Period</th>
              <th>Subject</th>
              <th>Teacher</th>
              <th>Note</th>
              <th style="width:220px;">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if(count($slots) === 0): ?>
            <tr><td colspan="6" style="opacity:.8;">No timetable slots added yet.</td></tr>
          <?php else: ?>
            <?php foreach($slots as $r): ?>
              <tr>
                <td><?= h($r['Day_of_week']) ?></td>
                <td>
                  <?= h($r['Period_no']) ?> • <?= h($r['Period_name']) ?><br>
                  <span style="opacity:.8;font-size:12px;"><?= h($r['Start_time']) ?> - <?= h($r['End_time']) ?></span>
                </td>
                <td><?= h($r['subject_code']) ?> • <?= h($r['subject_name']) ?></td>
                <td><?= h($r['teacher_name']) ?></td>
                <td><?= h($r['Note']) ?></td>
                <td>
                  <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="admin_timetable.php?edit=<?= (int)$r['Id'] ?>">✏ Edit</a>
                    <a class="btn" href="admin_timetable.php?delete=<?= (int)$r['Id'] ?>"
                       onclick="return confirm('Delete this timetable slot?');">🗑 Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>

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