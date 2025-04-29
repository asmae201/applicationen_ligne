<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("non autorisé!");
}

$exam_id = $_POST['exam_id'] ?? null;
$student_id = $_SESSION['user_id']; 
$incident_type = $_POST['incident_type'] ?? '';
$duration = $_POST['duration'] ?? 0;

try {
    $stmt = $pdo->prepare("
        INSERT INTO examenincident 
        (exam_id, student_id, incident_type, duration, timestamp) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$exam_id, $student_id, $incident_type, $duration]);
    
    echo "Incident aucun base!";
} catch(PDOException $e) {
    error_log("Error f record_incident.php: " . $e->getMessage());
    die("Problème en database!");
}
?>
