<?php
include "db.php";
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "student") {
    header("Location: school.php");
    exit();
}

$student_id = (int)($_SESSION['id'] ?? 0);
if ($student_id <= 0) {
    session_destroy();
    header("Location: school.php");
    exit();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ------------------- Fetch Student -------------------
$stmt = $conn->prepare("SELECT register_number, name, profile_photo FROM students WHERE id=? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    session_destroy();
    header("Location: school.php");
    exit();
}

$student_name = $student['name'] ?? "Student";
$reg_no = $student['register_number'] ?? "-";

$profilePhoto = !empty($student['profile_photo'])
    ? $student['profile_photo']
    : 'assets/default-avatar.png';

// ------------------- Day Code -------------------
$dayMap = [
  "Mon" => "MON",
  "Tue" => "TUE",
  "Wed" => "WED",
  "Thu" => "THU",
  "Fri" => "FRI",
  "Sat" => "SAT",
  "Sun" => "SUN"
];
$todayShort = date("D");
$todayCode = $dayMap[$todayShort] ?? "MON";
$todayDate = date("Y-m-d");

// ------------------- Attendance Summary -------------------
$present = 0; $absent = 0; $total = 0; $percent = 0;

$stA = $conn->prepare("
    SELECT
      SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present_count,
      SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent_count,
      COUNT(*) AS total_count
    FROM attendance
    WHERE student_id=?
");
$stA->bind_param("i", $student_id);
$stA->execute();
$rowA = $stA->get_result()->fetch_assoc();
$stA->close();

if ($rowA) {
    $present = (int)($rowA['present_count'] ?? 0);
    $absent  = (int)($rowA['absent_count'] ?? 0);
    $total   = (int)($rowA['total_count'] ?? 0);
    $percent = $total > 0 ? round(($present / $total) * 100) : 0;
}

// ------------------- Today Timetable -------------------
$classes = [];
$stT = $conn->prepare("
    SELECT
      p.period_no, p.period_name, p.start_time, p.end_time,
      s.subject_code, s.subject_name,
      t.name AS teacher_name,
      tt.note
    FROM timetable tt
    JOIN periods p  ON p.id = tt.period_id
    JOIN subjects s ON s.id = tt.subject_id
    JOIN teachers t ON t.id = tt.teacher_id
    WHERE tt.day_of_week=?
    ORDER BY p.period_no ASC
");
$stT->bind_param("s", $todayCode);
$stT->execute();
$resT = $stT->get_result();
while($r = $resT->fetch_assoc()){
    $classes[] = $r;
}
$stT->close();

// ------------------- Teacher Announcements -------------------
$announcements = [];
$q = $conn->query("
  SELECT a.title, a.message, a.priority, a.created_at, t.name AS teacher_name
  FROM announcements a
  JOIN teachers t ON t.id = a.teacher_id
  ORDER BY a.created_at DESC
  LIMIT 5
");
if ($q) {
    while($r = $q->fetch_assoc()){
        $announcements[] = $r;
    }
}

// ------------------- Marksheet / Uploaded Files -------------------
$files = [];
$stF = $conn->prepare("
    SELECT file_name, file_path, file_type, file_size, uploaded_at
    FROM student_files
    WHERE student_id=?
    ORDER BY uploaded_at DESC
    LIMIT 5
");
$stF->bind_param("i", $student_id);
$stF->execute();
$resF = $stF->get_result();
while($r = $resF->fetch_assoc()){
    $files[] = $r;
}
$stF->close();

$warn = ($total >= 5 && $percent < 75);

// resolve image path safely
$showPhoto = false;
$photoSrc = 'assets/default-avatar.png';

if (!empty($profilePhoto)) {
    $photoAbsolute = __DIR__ . '/' . ltrim($profilePhoto, '/');
    if (file_exists($photoAbsolute) && is_file($photoAbsolute)) {
        $photoSrc = $profilePhoto;
        $showPhoto = true;
    } elseif ($profilePhoto === 'assets/default-avatar.png' && file_exists(__DIR__ . '/assets/default-avatar.png')) {
        $photoSrc = $profilePhoto;
        $showPhoto = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>MGR Student Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
:root{
  --p:#4f46e5;--s:#06b6d4;--t:#fff;
  --card:rgba(255,255,255,.14);
  --line:rgba(255,255,255,.18);
  --muted:rgba(255,255,255,.75);
}
body.dark{
  --p:#111827;--s:#1f2937;--t:#f9fafb;
  --card:rgba(0,0,0,.45);
  --line:rgba(255,255,255,.10);
  --muted:rgba(255,255,255,.72);
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{
  min-height:100vh;color:var(--t);
  background:linear-gradient(-45deg,var(--p),var(--s),#9333ea,#ec4899);
  background-size:400% 400%;
  animation:bg 12s ease infinite;
  padding:22px;
}
@keyframes bg{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.wrap{max-width:1200px;margin:0 auto;}
.glass{
  background:var(--card);
  border:1px solid var(--line);
  backdrop-filter:blur(16px);
  border-radius:16px;
  box-shadow:0 18px 40px rgba(0,0,0,.18);
}
.topbar{
  display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
  padding:14px 16px;
}
.btn{
  padding:10px 14px;border-radius:12px;border:1px solid var(--line);
  background:rgba(255,255,255,.14);color:var(--t);
  text-decoration:none;cursor:pointer;display:inline-block;
  transition:.2s;
}
.btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.small{font-size:12px;opacity:.85;}
.badge{
  display:inline-block;padding:5px 10px;border-radius:999px;
  border:1px solid var(--line);background:rgba(0,0,0,.12);font-size:12px;
}
.badge.warn{border-color:rgba(255,255,255,.25);}

.layout{
  margin-top:14px;
  display:grid;
  grid-template-columns: 240px 1fr;
  gap:14px;
  align-items:start;
}

.sidebar{padding:14px;position:sticky;top:16px;}
.side-title{font-weight:700;margin-bottom:10px;}
.qa-item{
  display:flex;align-items:center;gap:10px;
  padding:12px 12px;border-radius:14px;
  text-decoration:none;color:var(--t);
  border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.10);
  margin-bottom:10px;transition:.2s;font-weight:600;
}
.qa-item:hover{transform:translateY(-1px);background:rgba(255,255,255,.18);}

.main{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:14px;
}
.card{padding:16px;}
.card h2{font-size:16px;margin-bottom:10px;}
.kv{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;}
.kv .k{opacity:.85;font-size:12px;}
.kv .v{font-weight:700;}
.profile{
  display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;
}
.p-left{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.avatar-box{
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:8px;
}
.avatar{
  width:70px;height:70px;border-radius:50%;
  border:1px solid var(--line);
  background:rgba(0,0,0,.10);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;
  overflow:hidden;
  font-size:24px;
}
.avatar img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.upload-label{
  padding:7px 12px;
  border-radius:10px;
  border:1px solid var(--line);
  background:rgba(255,255,255,.14);
  color:var(--t);
  cursor:pointer;
  font-size:11px;
  font-weight:600;
  transition:.2s;
}
.upload-label:hover{
  background:rgba(255,255,255,.22);
  transform:translateY(-1px);
}
.welcome{font-size:18px;font-weight:800;}
.subline{font-size:13px;opacity:.85;margin-top:2px;}

.clock-wrap{display:flex;align-items:center;gap:14px;}
.digital{font-weight:800;letter-spacing:1px;font-size:15px;opacity:.95;}

.analog-clock{
  width:58px;height:58px;border-radius:50%;
  border:1px solid var(--line);
  background:rgba(0,0,0,.10);
  position:relative;
}
.hand{position:absolute;left:50%;top:50%;transform-origin:0% 50%;transform:translateY(-50%) rotate(90deg);border-radius:999px;}
.hand.hour{width:17px;height:3px;background:rgba(255,255,255,.85);}
.hand.minute{width:22px;height:2px;background:rgba(255,255,255,.75);}
.hand.second{width:25px;height:1px;background:rgba(255,255,255,.65);}
.dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.9);
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);}

.progress{
  width:100%;height:10px;border-radius:999px;
  border:1px solid rgba(255,255,255,.14);
  background:rgba(0,0,0,.12);
  overflow:hidden;margin-top:10px;
}
.progress > div{height:100%;width:0%;background:rgba(255,255,255,.65);}

.item{
  margin-top:10px;padding:12px;border-radius:14px;
  border:1px solid rgba(255,255,255,.12);
  background:rgba(0,0,0,.10);
}
.line{
  display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;
}
.muted{color:var(--muted);}

.list-row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:6px;font-size:13px;}
hr.sep{border:none;border-top:1px solid rgba(255,255,255,.10);margin:10px 0;}

@media(max-width:1000px){
  .main{grid-template-columns:1fr;}
}
@media(max-width:900px){
  .layout{grid-template-columns:1fr;}
  .sidebar{position:relative;top:auto;display:flex;gap:10px;overflow-x:auto;}
  .sidebar .side-title{display:none;}
  .qa-item{flex:0 0 auto;margin-bottom:0;}
}
</style>
</head>

<body>
<div class="wrap">

  <div class="topbar glass">
    <div>
      <div style="font-weight:800;">MGR College • Student Portal</div>
      <div class="small">Logged in as <span class="muted"><?= h($student_name) ?></span></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <div class="btn" onclick="toggleDarkMode()">🌙 Dark</div>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="layout">

    <div class="sidebar glass">
      <div class="side-title">Quick Actions</div>
      <a class="qa-item" href="dashboard.php">🏠 <span>Dashboard</span></a>
      <a class="qa-item" href="attendance_history.php">📌 <span>Attendance</span></a>
      <a class="qa-item" href="student_profile.php">👤 <span>Profile</span></a>
      <a class="qa-item" href="upload_marksheet.php">📄 <span>Marksheet</span></a>
    </div>

    <div class="main">

      <div class="card glass" style="grid-column:1/-1;">
        <div class="profile">
          <div class="p-left">

            <div class="avatar-box">
              <div class="avatar">
                <?php if ($showPhoto): ?>
                  <img src="<?= h($photoSrc) ?>" alt="Profile Photo">
                <?php else: ?>
                  <?= strtoupper(substr($student_name,0,1)) ?>
                <?php endif; ?>
              </div>

              <form action="upload_profile_photo.php" method="POST" enctype="multipart/form-data">
                <label for="profile_photo" class="upload-label">Change Photo</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" onchange="this.form.submit()" hidden>
              </form>
            </div>

            <div>
              <div class="welcome">Welcome, <?= h($student_name) ?>!</div>
              <div class="subline" id="dateLine"><?= h(date("l, d M Y")) ?></div>
              <div class="kv">
                <div><div class="k">Register No</div><div class="v"><?= h($reg_no) ?></div></div>
                <div><div class="k">Today</div><div class="v"><?= h($todayCode) ?></div></div>
              </div>
            </div>
          </div>

          <div class="clock-wrap">
            <div class="analog-clock">
              <div class="hand hour" id="hHand"></div>
              <div class="hand minute" id="mHand"></div>
              <div class="hand second" id="sHand"></div>
              <div class="dot"></div>
            </div>
            <div>
              <div class="digital" id="digitalClock">--:--:--</div>
              <div class="small muted">Live Time</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card glass">
        <h2>📊 Attendance Summary</h2>

        <div class="line">
          <div class="small">Present: <b><?= $present ?></b></div>
          <div class="small">Absent: <b><?= $absent ?></b></div>
          <div class="small">Total: <b><?= $total ?></b></div>
        </div>

        <div style="margin-top:10px;font-size:28px;font-weight:900;"><?= $percent ?>%</div>
        <div class="small muted">Overall Attendance</div>

        <div class="progress"><div id="attBar"></div></div>

        <?php if($warn): ?>
          <div class="item" style="margin-top:12px;">
            <div style="font-weight:800;">⚠️ Warning</div>
            <div class="small">Attendance below 75%. Try to attend more classes.</div>
          </div>
        <?php endif; ?>

        <div style="margin-top:12px;">
          <a class="btn" href="attendance_history.php">View Attendance</a>
        </div>
      </div>

      <div class="card glass">
        <h2>🗓 Today’s Timetable (<?= h($todayCode) ?>)</h2>

        <?php if(empty($classes)): ?>
          <div class="small muted">No classes scheduled for today.</div>
        <?php else: ?>
          <?php foreach($classes as $c): ?>
            <div class="item">
              <div style="font-weight:800;">
                <?= h($c['subject_code']) ?> • <?= h($c['subject_name']) ?>
              </div>
              <div class="small muted" style="margin-top:4px;">
                Period <?= h($c['period_no']) ?> (<?= h($c['start_time']) ?> - <?= h($c['end_time']) ?>)
              </div>
              <div class="small" style="margin-top:4px;">
                Teacher: <b><?= h($c['teacher_name']) ?></b>
              </div>
              <?php if(!empty($c['note'])): ?>
                <div class="small muted" style="margin-top:6px;">Note: <?= h($c['note']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top:12px;">
          <span class="badge">Tip: Ask teacher to update timetable if wrong.</span>
        </div>
      </div>

      <div class="card glass" style="grid-column:1/-1;">
        <h2>📢 Teacher Announcements</h2>

        <?php if(empty($announcements)): ?>
          <div class="small muted">No announcements yet.</div>
        <?php else: ?>
          <?php foreach($announcements as $a): ?>
            <div class="item">
              <div class="line">
                <div style="font-weight:900;">
                  <?= h($a['title']) ?>
                  <?php if(($a['priority'] ?? '') === 'important'): ?>
                    <span class="badge warn" style="margin-left:8px;">IMPORTANT</span>
                  <?php endif; ?>
                </div>
                <div class="small muted"><?= h(date("d M, h:i A", strtotime($a['created_at']))) ?></div>
              </div>
              <div class="small muted" style="margin-top:4px;">By <?= h($a['teacher_name']) ?></div>
              <div style="margin-top:8px;white-space:pre-wrap;"><?= h($a['message']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="card glass" style="grid-column:1/-1;">
        <div class="line" style="align-items:center;">
          <h2 style="margin:0;">📄 Marksheet Storage</h2>
          <a class="btn" href="upload_marksheet.php">Upload</a>
        </div>

        <?php if(empty($files)): ?>
          <div class="small muted" style="margin-top:8px;">No files uploaded yet.</div>
        <?php else: ?>
          <?php foreach($files as $f): ?>
            <div class="item">
              <div style="font-weight:800;"><?= h($f['file_name']) ?></div>
              <div class="small muted">
                <?= h($f['file_type']) ?> • <?= h(round(((int)$f['file_size'])/1024, 1)) ?> KB
                • <?= h(date("d M Y", strtotime($f['uploaded_at']))) ?>
              </div>
              <div style="margin-top:10px;">
                <a class="btn" href="download_marksheet.php?file=<?= urlencode($f['file_path']) ?>">Download</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top:12px;">
          <span class="badge">Only you can download your files</span>
        </div>
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

(function(){
  var pct = <?= (int)$percent ?>;
  var el = document.getElementById("attBar");
  if(el){ el.style.width = Math.max(0, Math.min(100, pct)) + "%"; }
})();

function pad(n){ return String(n).padStart(2,'0'); }
function updateClocks(){
  const now = new Date();

  const dateStr = now.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'short', day:'numeric' });
  const dateEl = document.getElementById('dateLine');
  if(dateEl) dateEl.textContent = dateStr;

  const hh = pad(now.getHours());
  const mm = pad(now.getMinutes());
  const ss = pad(now.getSeconds());
  const dEl = document.getElementById('digitalClock');
  if(dEl) dEl.textContent = `${hh}:${mm}:${ss}`;

  const hour = now.getHours() % 12;
  const minute = now.getMinutes();
  const second = now.getSeconds();

  const hDeg = (hour * 30) + (minute * 0.5);
  const mDeg = (minute * 6) + (second * 0.1);
  const sDeg = second * 6;

  const h = document.getElementById('hHand');
  const m = document.getElementById('mHand');
  const s = document.getElementById('sHand');
  if(h) h.style.transform = `translateY(-50%) rotate(${hDeg}deg)`;
  if(m) m.style.transform = `translateY(-50%) rotate(${mDeg}deg)`;
  if(s) s.style.transform = `translateY(-50%) rotate(${sDeg}deg)`;
}
updateClocks();
setInterval(updateClocks, 1000);
</script>

</body>
</html>