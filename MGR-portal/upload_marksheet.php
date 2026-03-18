<?php
include "db.php";
session_start();

/* ✅ Allow student login session keys */
$student_id = (int)($_SESSION['student_id'] ?? ($_SESSION['id'] ?? 0));

if ($student_id <= 0) {
    header("Location: school.php");
    exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ✅ Upload storage */
$uploadDir = __DIR__ . "/uploads/marksheets/";
$publicDir = "uploads/marksheets/";

/* Create folder if missing */
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

/* Allowed */
$allowedExt  = ["pdf","jpg","jpeg","png"];
$allowedMime = ["application/pdf","image/jpeg","image/png"];
$maxSize = 5 * 1024 * 1024; // 5MB

$msg = "";

/* ✅ Handle upload */
if (isset($_POST['upload']) && isset($_FILES['marksheet'])) {
    $f = $_FILES['marksheet'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = "❌ Upload failed. Try again.";
    } else if ($f['size'] > $maxSize) {
        $msg = "⚠️ File too large. Max 5MB.";
    } else {
        $origName = basename($f['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // detect mime (best effort)
        $mime = $f['type'] ?? '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = finfo_file($fi, $f['tmp_name']) ?: $mime;
                finfo_close($fi);
            }
        }

        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
            $msg = "⚠️ Only PDF/JPG/PNG allowed.";
        } else {
            // unique safe file name
            $safe = "student_" . $student_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $targetPath = $uploadDir . $safe;

            if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
                $msg = "❌ Failed to save file (check folder permissions).";
            } else {
                // ✅ Save to DB table: student_files
                // If you haven't created table yet, we’ll do SQL below.
                $stmt = $conn->prepare("INSERT INTO student_files (student_id, file_name, file_path, file_type, file_size) VALUES (?,?,?,?,?)");
                $pathDB = $publicDir . $safe;
                $size = (int)$f['size'];
                $stmt->bind_param("isssi", $student_id, $origName, $pathDB, $mime, $size);

                if ($stmt->execute()) {
                    $msg = "✅ Marksheet uploaded successfully!";
                } else {
                    // If DB insert fails, remove file to avoid orphan
                    @unlink($targetPath);
                    $msg = "❌ DB save failed. Create student_files table first.";
                }
                $stmt->close();
            }
        }
    }
}

/* ✅ Fetch uploaded list */
$files = [];
$res = $conn->prepare("SELECT id, file_name, file_path, file_type, file_size, uploaded_at FROM student_files WHERE student_id=? ORDER BY uploaded_at DESC");
if ($res) {
    $res->bind_param("i", $student_id);
    $res->execute();
    $q = $res->get_result();
    while ($row = $q->fetch_assoc()) $files[] = $row;
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Marksheet Storage</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--p:#4f46e5;--s:#06b6d4;--t:#fff;--card:rgba(255,255,255,.14);--line:rgba(255,255,255,.18);}
body.dark{--p:#111827;--s:#1f2937;--t:#f9fafb;--card:rgba(0,0,0,.45);--line:rgba(255,255,255,.10);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{min-height:100vh;color:var(--t);background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
background-size:400% 400%;animation:bg 12s ease infinite;padding:22px;}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1000px;margin:0 auto;}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
background:var(--card);border:1px solid var(--line);backdrop-filter:blur(16px);
border-radius:16px;padding:16px;box-shadow:0 18px 40px rgba(0,0,0,.22);}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);
background:rgba(255,255,255,.14);color:var(--t);text-decoration:none;cursor:pointer;display:inline-block;}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.card{margin-top:14px;background:var(--card);border:1px solid var(--line);border-radius:16px;
padding:16px;backdrop-filter:blur(16px);box-shadow:0 18px 40px rgba(0,0,0,.18);}
.msg{padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.18);margin-bottom:12px;}
input[type=file]{width:100%;padding:10px 12px;border-radius:12px;border:none;outline:none;background:rgba(255,255,255,.9);}
table{width:100%;border-collapse:collapse;margin-top:10px;border-radius:14px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;font-size:14px;}
th{color:rgba(255,255,255,.75);font-size:13px;background:rgba(0,0,0,.12);}
.small{font-size:13px;opacity:.9;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(0,0,0,.18);border:1px solid var(--line);font-size:12px;}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h2>📄 Marksheet Storage</h2>
      <div class="small">Upload your marksheet and download anytime (PDF/JPG/PNG, max 5MB).</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="dashboard.php">⬅ Back</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <?php if($msg) echo "<div class='msg'>".h($msg)."</div>"; ?>

    <h3>Upload Marksheet</h3>
    <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
      <input type="file" name="marksheet" required>
      <div style="margin-top:12px;">
        <button class="btn" type="submit" name="upload">⬆ Upload</button>
      </div>
    </form>

    <h3 style="margin-top:18px;">Your Uploaded Files</h3>
    <div class="small" style="margin-top:6px;">Total: <?= count($files) ?></div>

    <table>
      <thead>
        <tr>
          <th>File</th>
          <th>Type</th>
          <th>Size</th>
          <th>Uploaded</th>
          <th style="width:150px;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!count($files)): ?>
        <tr><td colspan="5" class="small">No uploads yet.</td></tr>
      <?php else: foreach($files as $f): ?>
        <tr>
          <td><?= h($f['file_name']) ?></td>
          <td><span class="badge"><?= h($f['file_type']) ?></span></td>
          <td><?= (int)round(((int)$f['file_size'])/1024) ?> KB</td>
          <td><?= h($f['uploaded_at']) ?></td>
          <td><a class="btn" href="download_marksheet.php?id=<?= (int)$f['id'] ?>">⬇ Download</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
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