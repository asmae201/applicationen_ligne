<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    die('Accès non autorisé');
}

$exam_id = $_GET['exam_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';

// Récupérer les informations de l'étudiant
$student_query = $pdo->prepare("SELECT nom, email FROM Utilisateur WHERE id = ?");
$student_query->execute([$student_id]);
$student = $student_query->fetch(PDO::FETCH_ASSOC);

// Récupérer les incidents
$incident_query = $pdo->prepare("SELECT incident_type, duration, timestamp, ip_address 
                                FROM ExamenIncident 
                                WHERE exam_id = ? AND student_id = ?
                                ORDER BY timestamp DESC");
$incident_query->execute([$exam_id, $student_id]);
$incidents = $incident_query->fetchAll(PDO::FETCH_ASSOC);

echo '<div class="mb-4">';
echo '<h5>Étudiant: '.htmlspecialchars($student['nom']).'</h5>';
echo '<p>Email: '.htmlspecialchars($student['email']).'</p>';
echo '</div>';

if (empty($incidents)) {
    echo '<div class="alert alert-info">Aucun incident enregistré pour cet étudiant.</div>';
    exit();
}

echo '<div class="table-responsive">';
echo '<table class="table table-sm table-hover">';
echo '<thead class="table-primary">';
echo '<tr>';
echo '<th>Type</th>';
echo '<th>Durée</th>';
echo '<th>Date/Heure</th>';
echo '<th>Adresse IP</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($incidents as $incident) {
    echo '<tr>';
    echo '<td>';
    switch($incident['incident_type']) {
        case 'window_blur':
            echo '<i class="fas fa-window-restore me-2"></i> Perte de focus';
            break;
        case 'tab_switch':
            echo '<i class="fas fa-exchange-alt me-2"></i> Changement d\'onglet';
            break;
        default:
            echo htmlspecialchars($incident['incident_type']);
    }
    echo '</td>';
    echo '<td>'.($incident['duration'] > 0 ? $incident['duration'].' ms' : 'N/A').'</td>';
    echo '<td>'.htmlspecialchars($incident['timestamp']).'</td>';
    echo '<td>'.htmlspecialchars($incident['ip_address']).'</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
?>
