<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Etudiant') {
    header('Location: login.php');
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_SESSION['user_id'];

try {
    // Get exam information
    $stmt = $pdo->prepare("
        SELECT * FROM Examen WHERE id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    // Get preliminary score if available
    $stmt = $pdo->prepare("
        SELECT * FROM note 
        WHERE etudiant_id = ? AND examen_id = ? 
        ORDER BY date_note DESC LIMIT 1
    ");
    $stmt->execute([$student_id, $exam_id]);
    $note = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Examen Soumis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success-card {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .return-btn {
            margin-top: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="success-card bg-white">
            <div class="text-center">
                <div class="success-icon">✓</div>
                <h2 class="mb-4">Examen soumis avec succès!</h2>
                <p class="lead mb-4">
                    Votre réponse pour "<?php echo htmlspecialchars($exam['titre']); ?>" a été enregistrée.
                </p>
                
                <?php if ($note && $exam['statut'] !== 'brouillon'): ?>
                    <?php if ($note['statut'] === 'Préliminaire'): ?>
                        <div class="alert alert-info">
                            <h4>Score préliminaire: <?php echo round($note['score'], 1); ?>%</h4>
                            <p>Certaines questions nécessitent une correction manuelle par l'enseignant.</p>
                            <p>Votre score final sera disponible prochainement.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <h4>Score final: <?php echo round($note['score'], 1); ?>%</h4>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <p>Votre examen sera noté prochainement.</p>
                    </div>
                <?php endif; ?>
                
                <a href="dashboard_student.php" class="btn btn-primary btn-lg return-btn">
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
} catch(PDOException $e) {
    error_log("Error in exam_submitted.php: " . $e->getMessage());
    die("Une erreur est survenue.");
}
?>
