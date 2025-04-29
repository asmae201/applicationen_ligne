<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Etudiant') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupérer les données envoyées
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier les données requises
if (!isset($data['exam_id']) || !isset($data['student_id']) || !isset($data['incident_type'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Données incomplètes']);
    exit();
}

// Vérifier que l'étudiant connecté est bien celui qui passe l'examen
if ($_SESSION['user_id'] !== $data['student_id']) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Utilisateur non autorisé']);
    exit();
}

try {
    // Créer une table pour les incidents si elle n'existe pas déjà
    $pdo->exec("CREATE TABLE IF NOT EXISTS ExamenIncident (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id VARCHAR(50) NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        incident_type VARCHAR(50) NOT NULL,
        duration INT DEFAULT 0,
        timestamp DATETIME NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        FOREIGN KEY (exam_id) REFERENCES Examen(id),
        FOREIGN KEY (student_id) REFERENCES Utilisateur(id)
    )");

    // Enregistrer l'incident
    $stmt = $pdo->prepare("
        INSERT INTO ExamenIncident 
        (exam_id, student_id, incident_type, duration, timestamp, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['exam_id'],
        $data['student_id'],
        $data['incident_type'],
        $data['duration'] ?? 0,
        date('Y-m-d H:i:s'), // Utiliser l'heure du serveur plutôt que celle envoyée
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Mettre à jour ExamenEtudiant pour indiquer un comportement suspect
    $pdo->prepare("
        UPDATE ExamenEtudiant 
        SET comportement_suspect = comportement_suspect + 1 
        WHERE etudiant_id = ? AND examen_id = ?
    ")->execute([$data['student_id'], $data['exam_id']]);

    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    error_log("Error in log_incident.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur serveur']);
}
?>
