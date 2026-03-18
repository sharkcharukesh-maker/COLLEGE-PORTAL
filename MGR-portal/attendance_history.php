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

// Filters
$month = $_GET['month'] ?? date("Y-m");
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date("Y-m");

$start_date = $month . "-01";
$end_date   = date("Y-m-t", strtotime($start_date));

$subject_id = (int)($_GET['subject_id'] ?? 0);

// Subjects dropdown
$subjects = [];
$r = $conn->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code ASC");
while($row = $r->fetch_assoc()) $subjects[] = $row;


// ---------------- Overall % ----------------
$sqlOverall = "
SELECT 
COUNT(*) AS total,
SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present
FROM attendance
WHERE student_id=?
AND date BETWEEN ? AND ?
";

$params = [$student_id,$start_date,$end_date];
$types="iss";

if($subject_id>0){
$sqlOverall.=" AND subject_id=?";
$types.="i";
$params[]=$subject_id;
}

$stmt=$conn->prepare($sqlOverall);
$stmt->bind_param($types,...$params);
$stmt->execute();
$row=$stmt->get_result()->fetch_assoc();
$stmt->close();

$total=(int)($row['total']??0);
$present=(int)($row['present']??0);
$overall_pct=$total>0?round(($present/$total)*100,2):0;


// ---------------- Subject % ----------------
$subjectStats=[];

$stmt=$conn->prepare("
SELECT a.subject_id,s.subject_code,s.subject_name,
COUNT(*) total,
SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) present
FROM attendance a
JOIN subjects s ON s.id=a.subject_id
WHERE a.student_id=? AND a.date BETWEEN ? AND ?
GROUP BY a.subject_id
ORDER BY s.subject_code
");

$stmt->bind_param("iss",$student_id,$start_date,$end_date);
$stmt->execute();
$res=$stmt->get_result();

while($r=$res->fetch_assoc()){
$r['pct']=$r['total']>0?round(($r['present']/$r['total'])*100,2):0;
$subjectStats[]=$r;
}
$stmt->close();


// ---------------- Attendance List ----------------
$sql="
SELECT a.date,a.status,
p.period_no,p.period_name,
s.subject_code,s.subject_name,
t.name teacher_name
FROM attendance a
JOIN periods p ON p.id=a.period_id
JOIN subjects s ON s.id=a.subject_id
LEFT JOIN teachers t ON t.id=a.teacher_id
WHERE a.student_id=? AND a.date BETWEEN ? AND ?
";

$params=[$student_id,$start_date,$end_date];
$types="iss";

if($subject_id>0){
$sql.=" AND a.subject_id=?";
$types.="i";
$params[]=$subject_id;
}

$sql.=" ORDER BY a.date DESC,p.period_no ASC";

$stmt=$conn->prepare($sql);
$stmt->bind_param($types,...$params);
$stmt->execute();
$res=$stmt->get_result();

$rows=[];
while($r=$res->fetch_assoc()) $rows[]=$r;

$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Attendance</title>
<style>
body{font-family:Arial;padding:20px;background:#111;color:#fff}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{padding:10px;border-bottom:1px solid #444}
.btn{padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:6px}
</style>
</head>

<body>

<h2>Attendance History</h2>

<a class="btn" href="dashboard.php">← Back to Dashboard</a>

<h3>Overall Attendance: <?= $overall_pct ?>%</h3>

<table>
<tr>
<th>Date</th>
<th>Period</th>
<th>Subject</th>
<th>Teacher</th>
<th>Status</th>
</tr>

<?php if(empty($rows)): ?>
<tr><td colspan="5">No attendance records</td></tr>
<?php else: ?>
<?php foreach($rows as $r): ?>
<tr>
<td><?=h($r['date'])?></td>
<td><?=h($r['period_no'])?> - <?=h($r['period_name'])?></td>
<td><?=h($r['subject_code'])?> - <?=h($r['subject_name'])?></td>
<td><?=h($r['teacher_name']??'-')?></td>
<td><?=h($r['status'])?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</table>

</body>
</html>