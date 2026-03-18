<?php
include "db.php";
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "student") {
    header("Location: school.php");
    exit();
}

$student_id = (int)($_SESSION['id'] ?? 0);
if ($student_id <= 0) {
    header("Location: school.php");
    exit();
}

$msg = "";
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (isset($_POST['upload_pfp'])) {
    if (!isset($_FILES['pfp']) || $_FILES['pfp']['error'] !== UPLOAD_ERR_OK) {
        $msg = "⚠️ Please choose an image file.";
    } else {
        $tmp  = $_FILES['pfp']['tmp_name'];
        $name = $_FILES['pfp']['name'];
        $size = (int)$_FILES['pfp']['size'];

        // Max 2MB
        if ($size > 2 * 1024 * 1024) {
            $msg = "⚠️ File too large. Max 2MB.";
        } else {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","webp"];
            if (!in_array($ext, $allowed, true)) {
                $msg = "⚠️ Only JPG, PNG, WEBP allowed.";
            } else {
                // Ensure folder exists
                $dir = __DIR__ . "/uploads/profile/";
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                // Unique file name
                $newName = "student_" . $student_id . "_" . time() . "." . $ext;
                $relPath = "uploads/profile/" . $newName;
                $absPath = __DIR__ . "/" . $relPath;

                if (move_uploaded_file($tmp, $absPath)) {

                    // (Optional) remove old photo
                    $old = null;
                    $st0 = $conn->prepare("SELECT profile_photo FROM students WHERE id=?");
                    $st0->bind_param("i", $student_id);
                    $st0->execute();
                    $oldRow = $st0->get_result()->fetch_assoc();
                    $st0->close();
                    $old = $oldRow['profile_photo'] ?? null;

                    $st = $conn->prepare("UPDATE students SET profile_photo=? WHERE id=?");
                    $st->bind_param("si", $relPath, $student_id);

                    if ($st->execute()) {
                        $msg = "✅ Profile photo updated!";
                        $st->close();

                        // Delete old image safely (only inside uploads/profile)
                        if ($old && strpos($old, "uploads/profile/") === 0) {
                            $oldAbs = __DIR__ . "/" . $old;
                            if (is_file($oldAbs)) @unlink($oldAbs);
                        }

                    } else {
                        $msg = "❌ DB update failed.";
                        $st->close();
                    }
                } else {
                    $msg = "❌ Upload failed.";
                }
            }
        }
    }
}

// Get current photo
$photo = null;
$st = $conn->prepare("SELECT name, register_number, profile_photo FROM students WHERE id=?");
$st->bind_param("i", $student_id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();
$photo = $row['profile_photo'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Upload Profile Photo</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
/* Same glass + dark mode style (matches your portal) */
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);--muted:rgba(255,255,255,.75);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);--muted:rgba(255,255,255,.72);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{min-height:100vh;color:var(--t);background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);background-size:400% 400%;animation:bg 12s ease infinite;padding:22px;}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:900px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);border-radius:16px;box-shadow:0 18px 40px rgba(0,0,0,.18);}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px;}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;transition:.2s;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{margin-top:14px;padding:16px;}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.18);margin-bottom:12px;}
.avatar{width:92px;height:92px;border-radius:50%;border:1px solid var(--line);object-fit:cover;background:rgba(0,0,0,.10);}
label{font-size:13px;opacity:.9;display:block;margin-top:12px;}
input{width:100%;padding:10px 12px;border-radius:12px;border:none;outline:none;margin-top:6px;}
.small{font-size:12px;opacity:.85;}
</style>
</head>
<body>
<div class="wrap">

  <div class="top glass">
    <div>
      <div style="font-weight:800;">Upload Profile Photo</div>
      <div class="small">This photo will show on your dashboard.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="dashboard.php">⬅ Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card glass">
    <?php if($msg) echo "<div class='msg'>".h($msg)."</div>"; ?>

    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
      <img class="avatar" src="<?= $photo ? h($photo) : "assets/default-avatar.png" ?>" alt="Profile">
      <div>
        <div style="font-weight:900;font-size:18px;"><?= h($row['name'] ?? 'Student') ?></div>
        <div class="small">Register No: <?= h($row['register_number'] ?? '-') ?></div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="margin-top:14px;">
      <label>Select Photo (JPG/PNG/WEBP, max 2MB)</label>
      <input type="file" name="pfp" accept="image/*" required>

      <div style="margin-top:12px;">
        <button class="btn" type="submit" name="upload_pfp">✅ Upload Photo</button>
      </div>
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