<?php
include "db.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$msg = "";

/* ADD */
if (isset($_POST['add_subject'])) {
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');

    if ($code === "" || $name === "") {
        $msg = "⚠️ Please enter subject code and name.";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name) VALUES (?, ?)");
        $stmt->bind_param("ss", $code, $name);
        if ($stmt->execute()) $msg = "✅ Subject added!";
        else $msg = "❌ Error: Subject code may already exist.";
        $stmt->close();
    }
}

/* UPDATE */
if (isset($_POST['update_subject'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');

    if ($id <= 0 || $code === "" || $name === "") {
        $msg = "⚠️ Invalid update data.";
    } else {
        $stmt = $conn->prepare("UPDATE subjects SET subject_code=?, subject_name=? WHERE id=?");
        $stmt->bind_param("ssi", $code, $name, $id);
        if ($stmt->execute()) $msg = "✅ Subject updated!";
        else $msg = "❌ Update failed.";
        $stmt->close();
    }
}

/* DELETE */
if (isset($_GET['delete'])) {
    $id = (int)($_GET['delete'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM subjects WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $msg = "✅ Subject deleted!";
        else $msg = "❌ Delete failed (maybe timetable/attendance uses it).";
        $stmt->close();
    }
}

/* Fetch subjects */
$subjects = [];
$res = $conn->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code ASC");
while ($row = $res->fetch_assoc()) $subjects[] = $row;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Subjects</title>
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
background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.18);margin-bottom:12px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
input{width:100%;padding:10px 12px;border-radius:12px;border:none;outline:none;margin-top:6px;}
label{font-size:13px;opacity:.9;}
table{width:100%;border-collapse:collapse;margin-top:10px;border-radius:14px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;font-size:14px;}
th{color:rgba(255,255,255,.75);font-size:13px;background:rgba(0,0,0,.12);}
.small{font-size:13px;opacity:.9;}
.actions{display:flex;gap:8px;flex-wrap:wrap;}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h2>Admin • Manage Subjects</h2>
      <div class="small">Add / Edit / Delete subject codes and names.</div>
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
      <div>
        <h3>Add Subject</h3>
        <form method="POST">
          <label>Subject Code</label>
          <input name="subject_code" placeholder="HBCS22004" required>

          <label style="margin-top:10px;display:block;">Subject Name</label>
          <input name="subject_name" placeholder="Programming in Java" required>

          <div style="margin-top:12px;">
            <button class="btn" type="submit" name="add_subject">➕ Add</button>
          </div>
        </form>
      </div>

      <div>
        <h3>Subjects List</h3>
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th style="width:240px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($subjects as $s): ?>
            <tr>
              <td><?= h($s['subject_code']) ?></td>
              <td><?= h($s['subject_name']) ?></td>
              <td>
                <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <input name="subject_code" value="<?= h($s['subject_code']) ?>" style="max-width:140px;">
                  <input name="subject_name" value="<?= h($s['subject_name']) ?>" style="max-width:220px;">
                  <button class="btn" type="submit" name="update_subject">💾 Save</button>
                  <a class="btn" href="admin_subjects.php?delete=<?= (int)$s['id'] ?>" onclick="return confirm('Delete this subject?')">🗑 Delete</a>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
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
