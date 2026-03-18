<?php
include "db.php";
session_start();

/* Basic protection */
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$mode = $_GET['mode'] ?? 'day';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');

$subject_id = (int)($_GET['subject_id'] ?? 0);
$period_id  = (int)($_GET['period_id'] ?? 0);
$teacher_id = (int)($_GET['teacher_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

/* WHERE conditions */
$where = [];
$params = [];
$types = "";

if ($mode === "month") {
    $where[] = "DATE_FORMAT(a.date,'%Y-%m') = ?";
    $types .= "s";
    $params[] = $month;
} else {
    $where[] = "a.date = ?";
    $types .= "s";
    $params[] = $date;
}

if ($subject_id > 0) { $where[] = "a.subject_id = ?"; $types.="i"; $params[]=$subject_id; }
if ($period_id  > 0) { $where[] = "a.period_id = ?";  $types.="i"; $params[]=$period_id; }
if ($teacher_id > 0) { $where[] = "a.teacher_id = ?"; $types.="i"; $params[]=$teacher_id; }
if ($student_id > 0) { $where[] = "a.student_id = ?"; $types.="i"; $params[]=$student_id; }

$sql = "
SELECT
    a.date,
    p.period_name,
    sub.subject_name,
    t.Name AS teacher_name,
    s.Name AS student_name,
    a.status
FROM attendance a
LEFT JOIN students s ON s.id = a.student_id
LEFT JOIN teachers t ON t.id = a.teacher_id
LEFT JOIN subjects sub ON sub.id = a.subject_id
LEFT JOIN periods p ON p.id = a.period_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY a.date ASC, p.id ASC, s.Name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* CSV headers */
$filename = ($mode === "month")
    ? "attendance_month_" . $month . ".csv"
    : "attendance_day_" . $date . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$output = fopen("php://output", "w");

/* CSV column titles */
fputcsv($output, [
    "Date",
    "Period",
    "Subject",
    "Teacher",
    "Student",
    "Status"
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['date'],
        $row['period_name'],
        $row['subject_name'],
        $row['teacher_name'],
        $row['student_name'],
        $row['status']
    ]);
}

fclose($output);
exit();