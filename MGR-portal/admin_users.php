<?php
include "db.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = "";

// ---------- Helpers ----------
function clean_email($e){
    $e = trim($e);
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : "";
}

// ---------- STUDENTS: Update ----------
if (isset($_POST['update_student'])) {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? "");
    $email = clean_email($_POST['email'] ?? "");

    if ($id <= 0 || $name === "" || $email === "") {
        $msg = "⚠️ Invalid student data (name/email).";
    } else {
        $stmt = $conn->prepare("UPDATE students SET Name=?, Email=? WHERE Id=?");
        $stmt->bind_param("ssi", $name, $email, $id);
        if ($stmt->execute()) $msg = "✅ Student updated!";
        else $msg = "❌ Student update failed (email may already exist).";
        $stmt->close();
    }
}

// ---------- STUDENTS: Deactivate ----------
if (isset($_GET['deactivate_student'])) {
    $id = (int)($_GET['deactivate_student'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE students SET is_active=0 WHERE Id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $msg = "✅ Student deactivated!";
        else $msg = "❌ Deactivate failed.";
        $stmt->close();
    }
}

// ---------- STUDENTS: Activate ----------
if (isset($_GET['activate_student'])) {
    $id = (int)($_GET['activate_student'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE students SET is_active=1 WHERE Id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $msg = "✅ Student activated!";
        else $msg = "❌ Activate failed.";
        $stmt->close();
    }
}

// ---------- TEACHERS: Update ----------
if (isset($_POST['update_teacher'])) {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? "");
    $email = clean_email($_POST['email'] ?? "");
    $role  = trim($_POST['role'] ?? "teacher");
    if ($role === "") $role = "teacher";

    if ($id <= 0 || $name === "" || $email === "") {
        $msg = "⚠️ Invalid teacher data (name/email).";
    } else {
        $stmt = $conn->prepare("UPDATE teachers SET Name=?, Email=?, Role=? WHERE Id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
        if ($stmt->execute()) $msg = "✅ Teacher updated!";
        else $msg = "❌ Teacher update failed (email may already exist).";
        $stmt->close();
    }
}

// ---------- TEACHERS: Delete ----------
if (isset($_GET['del_teacher'])) {
    $id = (int)($_GET['del_teacher'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM teachers WHERE Id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $msg = "✅ Teacher deleted!";
        else $msg = "❌ Delete failed (teacher may be referenced by timetable/attendance).";
        $stmt->close();
    }
}

// ---------- Search ----------
$qStudent = trim($_GET['qStudent'] ?? "");
$qTeacher = trim($_GET['qTeacher'] ?? "");

// Fetch students
$students = [];
if ($qStudent !== "") {
    $like = "%".$qStudent."%";
    $stmt = $conn->prepare("SELECT Id, Name, Email, is_active FROM students WHERE Name LIKE ? OR Email LIKE ? ORDER BY Name ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
} else {
    $res = $conn->query("SELECT Id, Name, Email, is_active FROM students ORDER BY Name ASC LIMIT 300");
    while($row = $res->fetch_assoc()) $students[] = $row;
}

// Fetch teachers
$teachers = [];
if ($qTeacher !== "") {
    $like = "%".$qTeacher."%";
    $stmt = $conn->prepare("SELECT Id, Name, Email, Role FROM teachers WHERE Name LIKE ? OR Email LIKE ? ORDER BY Name ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $teachers[] = $row;
    $stmt->close();
} else {
    $res = $conn->query("SELECT Id, Name, Email, Role FROM teachers ORDER BY Name ASC LIMIT 300");
    while($row = $res->fetch_assoc()) $teachers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Manage Users</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{min-height:100vh;color:var(--t);
background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
background-size:400% 400%;animation:bg 12s ease infinite;padding:22px;}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1200px;margin:0 auto;}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);
background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.12);margin-bottom:12px;}
.small{font-size:13px;opacity:.9;}
.searchRow{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px;}
input,select{padding:10px 12px;border-radius:12px;border:none;outline:none;}
table{width:100%;border-collapse:collapse;margin-top:10px;border-radius:14px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;font-size:14px;vertical-align:top;}
th{color:rgba(255,255,255,.75);font-size:13px;background:rgba(0,0,0,.12);}
.actions{display:flex;gap:8px;flex-wrap:wrap;}
.inlineForm{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.inlineForm input{max-width:160px;}
.inlineForm select{max-width:140px;}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);background:rgba(0,0,0,.12);font-size:12px;}
@media(max-width:1000px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h2>Admin • Manage Users</h2>
      <div class="small">Students use Deactivate/Activate (safe). Teachers can be deleted if not linked.</div>
    </div>
    <div class="actions">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="admin_dashboard.php">⬅ Dashboard</a>
      <a class="btn" href="admin_logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <?php if($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>

    <div class="grid">

      <!-- STUDENTS -->
      <div>
        <h3>Students</h3>
        <form method="GET" class="searchRow">
          <div>
            <div class="small">Search</div>
            <input name="qStudent" value="<?= h($qStudent) ?>" placeholder="Name or Email">
          </div>
          <div>
            <button class="btn" type="submit">🔎 Search</button>
            <a class="btn" href="admin_users.php">♻ Reset</a>
          </div>
        </form>

        <table>
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th>Name</th>
              <th>Email</th>
              <th style="width:120px;">Status</th>
              <th style="width:360px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($students)===0): ?>
              <tr><td colspan="5" style="opacity:.8;">No students found.</td></tr>
            <?php else: ?>
              <?php foreach($students as $s): 
                $active = (int)($s['is_active'] ?? 1);
              ?>
                <tr>
                  <td><?= (int)$s['Id'] ?></td>
                  <td><?= h($s['Name']) ?></td>
                  <td><?= h($s['Email']) ?></td>
                  <td>
                    <?php if($active===1): ?>
                      <span class="badge">Active</span>
                    <?php else: ?>
                      <span class="badge" style="opacity:.75;">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" class="inlineForm">
                      <input type="hidden" name="id" value="<?= (int)$s['Id'] ?>">
                      <input name="name" value="<?= h($s['Name']) ?>" placeholder="Name">
                      <input name="email" value="<?= h($s['Email']) ?>" placeholder="Email">
                      <button class="btn" type="submit" name="update_student">💾 Save</button>

                      <?php if($active===1): ?>
                        <a class="btn" href="admin_users.php?deactivate_student=<?= (int)$s['Id'] ?>"
                           onclick="return confirm('Deactivate this student? (Recommended)')">🚫 Deactivate</a>
                      <?php else: ?>
                        <a class="btn" href="admin_users.php?activate_student=<?= (int)$s['Id'] ?>"
                           onclick="return confirm('Activate this student?')">✅ Activate</a>
                      <?php endif; ?>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- TEACHERS -->
      <div>
        <h3>Teachers</h3>
        <form method="GET" class="searchRow">
          <div>
            <div class="small">Search</div>
            <input name="qTeacher" value="<?= h($qTeacher) ?>" placeholder="Name or Email">
          </div>
          <div>
            <button class="btn" type="submit">🔎 Search</button>
            <a class="btn" href="admin_users.php">♻ Reset</a>
          </div>
        </form>

        <table>
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th>Name</th>
              <th>Email</th>
              <th style="width:90px;">Role</th>
              <th style="width:360px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($teachers)===0): ?>
              <tr><td colspan="5" style="opacity:.8;">No teachers found.</td></tr>
            <?php else: ?>
              <?php foreach($teachers as $t): ?>
                <tr>
                  <td><?= (int)$t['Id'] ?></td>
                  <td><?= h($t['Name']) ?></td>
                  <td><?= h($t['Email']) ?></td>
                  <td><?= h($t['Role']) ?></td>
                  <td>
                    <form method="POST" class="inlineForm">
                      <input type="hidden" name="id" value="<?= (int)$t['Id'] ?>">
                      <input name="name" value="<?= h($t['Name']) ?>" placeholder="Name">
                      <input name="email" value="<?= h($t['Email']) ?>" placeholder="Email">
                      <select name="role">
                        <option value="teacher" <?= ($t['Role']==="teacher")?"selected":""; ?>>teacher</option>
                        <option value="staff" <?= ($t['Role']==="staff")?"selected":""; ?>>staff</option>
                        <option value="hod" <?= ($t['Role']==="hod")?"selected":""; ?>>hod</option>
                      </select>
                      <button class="btn" type="submit" name="update_teacher">💾 Save</button>
                      <a class="btn" href="admin_users.php?del_teacher=<?= (int)$t['Id'] ?>"
                         onclick="return confirm('Delete this teacher? Timetable/attendance may be affected.')">🗑 Delete</a>
                    </form>
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