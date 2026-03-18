<?php
include "db.php";
session_start();

/* Only student can access */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "student") {
    header("Location: school.php");
    exit();
}

$student_id = (int)($_SESSION['id'] ?? 0);
if ($student_id <= 0) {
    die("Invalid session. Please login again.");
}

/* 1) Get student details */
$stmt = $conn->prepare("SELECT id, register_number, name, email FROM students WHERE id=? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found.");
}

/* 2) Overall attendance */
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present_count,
        COUNT(*) AS total_count
    FROM attendance
    WHERE student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$overall = $stmt->get_result()->fetch_assoc();
$stmt->close();

$present_total = (int)($overall['present_count'] ?? 0);
$total_classes = (int)($overall['total_count'] ?? 0);
$overall_pct = ($total_classes > 0) ? round(($present_total / $total_classes) * 100, 1) : 0;

/* 3) Subject-wise attendance */
$stmt = $conn->prepare("
    SELECT 
        s.id AS subject_id,
        s.subject_name,
        s.subject_code,
        SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
        COUNT(a.id) AS total_count
    FROM subjects s
    LEFT JOIN attendance a 
        ON a.subject_id = s.id AND a.student_id = ?
    GROUP BY s.id, s.subject_name, s.subject_code
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subject_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Profile</title>

<style>
    :root{
        --bg1:#0b78c8;
        --bg2:#0aa0d6;
        --card: rgba(255,255,255,0.10);
        --card2: rgba(255,255,255,0.14);
        --text:#eaf6ff;
        --muted: rgba(234,246,255,0.75);
        --border: rgba(255,255,255,0.18);
        --shadow: 0 18px 40px rgba(0,0,0,0.22);
        --radius: 18px;
    }
    *{box-sizing:border-box}
    body{
        margin:0;
        font-family: "Poppins", Arial, sans-serif;
        color:var(--text);
        min-height:100vh;
        background: linear-gradient(135deg, var(--bg1), var(--bg2));
        padding:28px;
    }
    .container{
        max-width: 1050px;
        margin: 0 auto;
    }
    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        background: var(--card);
        border:1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 18px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
    }
    .brand h1{
        margin:0;
        font-size: 26px;
        letter-spacing: 0.5px;
    }
    .brand p{
        margin:6px 0 0;
        color: var(--muted);
        font-size: 14px;
    }
    .actions{
        display:flex;
        gap:10px;
        align-items:center;
    }
    .btn{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 14px;
        border-radius: 14px;
        border:1px solid var(--border);
        background: rgba(255,255,255,0.12);
        color: var(--text);
        text-decoration:none;
        cursor:pointer;
        font-weight: 600;
        transition: 0.2s ease;
        user-select:none;
    }
    .btn:hover{
        transform: translateY(-1px);
        background: rgba(255,255,255,0.18);
    }

    .grid{
        margin-top:18px;
        display:grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .card{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
    }
    .card h2{
        margin:0 0 10px;
        font-size: 18px;
    }
    .info{
        display:grid;
        grid-template-columns: 170px 1fr;
        row-gap: 10px;
        column-gap: 10px;
        color: var(--text);
        font-size: 14px;
    }
    .label{ color: var(--muted); }

    .big{
        font-size: 40px;
        font-weight: 800;
        margin: 6px 0;
    }
    .small{ color: var(--muted); font-size: 14px; }

    .table-card{
        margin-top: 16px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
    }
    table{
        width:100%;
        border-collapse: collapse;
        overflow:hidden;
        border-radius: 14px;
    }
    thead th{
        text-align:left;
        padding:12px;
        font-size: 13px;
        color: var(--muted);
        background: rgba(255,255,255,0.10);
        border-bottom: 1px solid var(--border);
    }
    tbody td{
        padding:12px;
        border-bottom: 1px solid rgba(255,255,255,0.10);
        font-size: 14px;
    }
    tbody tr:hover{
        background: rgba(255,255,255,0.06);
    }
    .pill{
        display:inline-block;
        padding:6px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.10);
        font-size: 12px;
        color: var(--text);
    }

    .progress{
        height: 10px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 999px;
        overflow:hidden;
    }
    .bar{
        height:100%;
        width: 0%;
        background: rgba(255,255,255,0.55);
    }

    @media(max-width: 900px){
        .grid{ grid-template-columns: 1fr; }
        .info{ grid-template-columns: 140px 1fr; }
    }
</style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div class="brand">
            <h1>MGR COLLEGE</h1>
            <p>Student Dashboard • Profile & Attendance</p>
        </div>
        <div class="actions">
            <a class="btn" href="student_dashboard.php">⬅ Back</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Student Profile</h2>
            <div class="info">
                <div class="label">Register No</div>
                <div><span class="pill"><?php echo htmlspecialchars($student['register_number']); ?></span></div>

                <div class="label">Name</div>
                <div><?php echo htmlspecialchars($student['name']); ?></div>

                <div class="label">Email</div>
                <div><?php echo htmlspecialchars($student['email']); ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Overall Attendance</h2>
            <div class="big"><?php echo $overall_pct; ?>%</div>
            <div class="small">
                Present: <b><?php echo $present_total; ?></b> /
                Total: <b><?php echo $total_classes; ?></b>
            </div>
            <div style="margin-top:12px" class="progress">
                <div class="bar" style="width: <?php echo min(100, max(0, $overall_pct)); ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
            <h2 style="margin:0;">Subject-wise Attendance</h2>
            <a class="btn" href="#" onclick="alert('Next: We will add PDF download after confirming this page looks correct.');return false;">⬇ Download PDF</a>
        </div>
        <p class="small" style="margin-top:8px;">Shows attendance summary for each subject.</p>

        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Present</th>
                    <th>Total</th>
                    <th>%</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subject_rows as $r):
                    $p = (int)($r['present_count'] ?? 0);
                    $t = (int)($r['total_count'] ?? 0);
                    $pct = ($t > 0) ? round(($p/$t)*100, 1) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['subject_code'] ?? ""); ?></td>
                    <td><?php echo $p; ?></td>
                    <td><?php echo $t; ?></td>
                    <td><span class="pill"><?php echo $pct; ?>%</span></td>
                    <td>
                        <div class="progress">
                            <div class="bar" style="width: <?php echo min(100, max(0, $pct)); ?>%;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
