<?php
include "db.php";
session_start();

// Teacher-only protection (based on your session)
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: school.php");
    exit();
}

$teacher_id = (int)$_SESSION['id'];
$msg = "";

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Handle post
if (isset($_POST['post_announcement'])) {
    $title = trim($_POST['title'] ?? "");
    $message = trim($_POST['message'] ?? "");
    $priority = ($_POST['priority'] ?? "normal") === "important" ? "important" : "normal";

    if ($title === "" || $message === "") {
        $msg = "⚠️ Please enter title and message.";
    } else {
        $st = $conn->prepare("INSERT INTO announcements (teacher_id, title, message, priority) VALUES (?,?,?,?)");
        $st->bind_param("isss", $teacher_id, $title, $message, $priority);
        if ($st->execute()) $msg = "✅ Announcement posted!";
        else $msg = "❌ Failed to post.";
        $st->close();
    }
}

// Delete own announcement
if (isset($_GET['delete'])) {
    $del_id = (int)($_GET['delete'] ?? 0);
    if ($del_id > 0) {
        $st = $conn->prepare("DELETE FROM announcements WHERE id=? AND teacher_id=?");
        $st->bind_param("ii", $del_id, $teacher_id);
        if ($st->execute()) $msg = "✅ Deleted.";
        else $msg = "❌ Delete failed.";
        $st->close();
    }
}

// Fetch teacher posts
$rows = [];
$st = $conn->prepare("SELECT id, title, message, priority, created_at FROM announcements WHERE teacher_id=? ORDER BY created_at DESC LIMIT 20");
$st->bind_param("i", $teacher_id);
$st->execute();
$res = $st->get_result();
while($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher - Announcements</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
/* Keep your same theme style (glass + animated gradient + dark toggle) */
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{
  min-height:100vh;color:var(--t);
  background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
  background-size:400% 400%;animation:bg 12s ease infinite;
  padding:22px;
}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1000px;margin:0 auto;}
.top{
  display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
  border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);
}
.card{
  margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
  padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);
}
.btn{
  padding:10px 14px;border-radius:12px;border:1px solid var(--line);
  background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;
}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
label{font-size:13px;opacity:.9;display:block;margin-top:10px;}
input,textarea,select{
  width:100%;padding:10px 12px;border-radius:12px;border:none;outline:none;margin-top:6px;
}
textarea{min-height:100px;resize:vertical;}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.18);margin-bottom:12px;}
.item{
  border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:12px;margin-top:10px;
  background:rgba(0,0,0,.10);
}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);background:rgba(0,0,0,.12);font-size:12px;}
.small{font-size:12px;opacity:.85;margin-top:6px;}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h2>Teacher • Announcements</h2>
      <div class="small">Post important updates like Internship Orientation, Exams, etc.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="teacher_dashboard.php">⬅ Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <?php if($msg) echo "<div class='msg'>".h($msg)."</div>"; ?>

    <h3>Post New</h3>
    <form method="POST">
      <label>Title</label>
      <input type="text" name="title" maxlength="150" placeholder="Eg: Internship Orientation Tomorrow" required>

      <label>Message</label>
      <textarea name="message" placeholder="Write details (time, venue, instructions)..." required></textarea>

      <label>Priority</label>
      <select name="priority">
        <option value="normal">Normal</option>
        <option value="important">Important</option>
      </select>

      <div style="margin-top:12px;">
        <button class="btn" type="submit" name="post_announcement">📢 Post Announcement</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Your Recent Posts</h3>

    <?php if(count($rows) === 0): ?>
      <div class="small">No announcements yet.</div>
    <?php else: ?>
      <?php foreach($rows as $a): ?>
        <div class="item">
          <div class="row">
            <div>
              <div style="font-weight:700;"><?= h($a['title']) ?></div>
              <div class="small"><?= h(date("d M Y, h:i A", strtotime($a['created_at']))) ?></div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <span class="badge"><?= h($a['priority']) ?></span>
              <a class="btn" href="teacher_announcements.php?delete=<?= (int)$a['id'] ?>"
                 onclick="return confirm('Delete this announcement?');">🗑 Delete</a>
            </div>
          </div>
          <div style="margin-top:8px;opacity:.95;white-space:pre-wrap;"><?= h($a['message']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
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