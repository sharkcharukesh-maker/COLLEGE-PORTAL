<?php
include "db.php";
session_start();

$student_id = (int)($_SESSION['student_id'] ?? ($_SESSION['id'] ?? 0));
if ($student_id <= 0) { header("Location: school.php"); exit(); }

$file_id = (int)($_GET['id'] ?? 0);
if ($file_id <= 0) { die("Invalid file."); }

$stmt = $conn->prepare("SELECT file_name, file_path, file_type, file_size
                        FROM student_files
                        WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $file_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) { die("File not found."); }

$row = $res->fetch_assoc();
$path = __DIR__ . "/" . $row['file_path'];

if (!file_exists($path)) { die("File missing on server."); }

$downloadName = basename($row['file_name']);
header("Content-Description: File Transfer");
header("Content-Type: " . ($row['file_type'] ?: "application/octet-stream"));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header("Content-Length: " . filesize($path));
readfile($path);
exit;