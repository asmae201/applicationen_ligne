<?php
session_start();
include('db.php');

// Vérification du rôle
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$exam_id = $_GET['exam_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$group_id = $_GET['group_id'] ?? 0;

// Vérification des paramètres
if (empty($exam_id)) {
    die("ID d'examen manquant");
}

// Vérifier l'examen
$check_exam = $pdo->prepare("SELECT id, titre FROM Examen WHERE id = ?");
$check_exam->execute([$exam_id]);
$exam = $check_exam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Examen non trouvé");
}

// Récupérer l'étudiant
$student_query = $pdo->prepare("SELECT id, nom, email FROM Utilisateur WHERE id = ? AND role = 'Etudiant'");
$student_query->execute([$student_id]);
$student = $student_query->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Étudiant non trouvé");
}

// Calculer le score maximum possible pour l'examen
$max_score_query = $pdo->prepare("SELECT SUM(point_attribue) as total_points FROM Question WHERE examen_id = ?");
$max_score_query->execute([$exam_id]);
$max_score = $max_score_query->fetch(PDO::FETCH_ASSOC)['total_points'] ?? 0;

// Fonction de correction QCM améliorée
function calculerNoteQCM($reponseEtudiant, $reponsesCorrectes, $pointsTotaux) {
    if (empty($reponseEtudiant)) return 0;
    
    // Normaliser les séparateurs (remplacer | et ||| par des virgules)
    $reponseEtudiant = str_replace(['|||', '|'], ',', $reponseEtudiant);
    
    $reponsesEtudiant = array_map('trim', explode(',', $reponseEtudiant));
    $reponsesCorrectes = array_map('trim', explode(',', $reponsesCorrectes));
    
    // Enlever les espaces et mettre en minuscule pour comparaison
    $reponsesEtudiant = array_map(function($item) {
        return strtolower(str_replace(' ', '', $item));
    }, $reponsesEtudiant);
    
    $reponsesCorrectes = array_map(function($item) {
        return strtolower(str_replace(' ', '', $item));
    }, $reponsesCorrectes);
    
    // Compter les bonnes réponses
    $bonnesReponses = 0;
    foreach ($reponsesEtudiant as $reponse) {
        if (in_array($reponse, $reponsesCorrectes)) {
            $bonnesReponses++;
        }
    }
    
    // Calculer la note proportionnelle
    if (count($reponsesCorrectes) > 0) {
        $note = ($pointsTotaux / count($reponsesCorrectes)) * $bonnesReponses;
    } else {
        $note = 0;
    }
    
    return $note;
}

// Variables pour stocker les données actualisées après la soumission
$updated_score = null;
$updated_comment = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $notes = $_POST['notes'] ?? [];
        $commentaire = $_POST['commentaire'] ?? '';
        $score_total = 0;

        foreach ($notes as $question_id => $note) {
            $score_total += floatval($note);
            
            $update_reponse = $pdo->prepare("UPDATE ReponseEtudiant SET note = ? 
                          WHERE etudiant_id = ? AND question_id = ? AND examen_id = ?");
            $update_reponse->execute([$note, $student_id, $question_id, $exam_id]);
        }

        // Mettre à jour la note globale
        $update_note = $pdo->prepare("INSERT INTO note (etudiant_id, examen_id, score, commentaire, statut, date_note)
                      VALUES (?, ?, ?, ?, 'Terminé', NOW())
                      ON DUPLICATE KEY UPDATE score = VALUES(score), commentaire = VALUES(commentaire), date_note = NOW()");
        $update_note->execute([$student_id, $exam_id, $score_total, $commentaire]);

        // Mettre à jour le statut
        $update_statut = $pdo->prepare("UPDATE ExamenEtudiant SET statut = 'Corrigé', score_final = ?
                      WHERE etudiant_id = ? AND examen_id = ?");
        $update_statut->execute([$score_total, $student_id, $exam_id]);

        $pdo->commit();
        
        // Stocker les données actualisées pour l'affichage immédiat
        $updated_score = $score_total;
        $updated_comment = $commentaire;
        
        $success_message = "Correction enregistrée avec succès!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les questions
$questions_query = $pdo->prepare("SELECT * FROM Question WHERE examen_id = ? ORDER BY id");
$questions_query->execute([$exam_id]);
$questions = $questions_query->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les réponses de l'étudiant
$reponses = [];
$reponses_query = $pdo->prepare("SELECT * FROM ReponseEtudiant 
                               WHERE etudiant_id = ? AND examen_id = ?");
$reponses_query->execute([$student_id, $exam_id]);
$reponses_data = $reponses_query->fetchAll(PDO::FETCH_ASSOC);

foreach ($reponses_data as $rep) {
    $reponses[$rep['question_id']] = $rep;
}

// Récupérer les choix pour les QCM
$choix = [];
$qcm_questions = array_filter($questions, function($q) {
    return $q['type'] === 'QCM' || $q['type'] === 'choix_multiple';
});

foreach ($qcm_questions as $q) {
    $choix_query = $pdo->prepare("SELECT * FROM Choix WHERE question_id = ?");
    $choix_query->execute([$q['id']]);
    $choix[$q['id']] = $choix_query->fetchAll(PDO::FETCH_ASSOC);
}

// Note précédente et commentaire
$previous_note = $pdo->prepare("SELECT score, commentaire FROM note 
                               WHERE etudiant_id = ? AND examen_id = ?");
$previous_note->execute([$student_id, $exam_id]);
$previous_note = $previous_note->fetch(PDO::FETCH_ASSOC);

// Utiliser la note mise à jour si elle existe, sinon utiliser la précédente
$displayed_score = $updated_score ?? ($previous_note['score'] ?? 0);
$displayed_comment = $updated_comment ?? ($previous_note['commentaire'] ?? '');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Examen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Couleurs principales */
            --primary: #4db8b8;
            --primary-light: #edf2ff;
            --warning: #f72585;
            --success: #4cc9f0;
            --success-light: #e0f7fa;
            --warning-light: #ffe0f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;



            --primary-dark: #3a9a9a;
            --secondary: #34495e;
            --accent: #1abc9c;
            --highlight: #f0a500;
            
            /* Couleurs de texte */
            --text-light: #f8f9fa;
            --text-dark: #212529;
            
            /* Couleurs de fond */
            --bg-light: #f8fafc;
            --bg-dark: #1a1a2e;
            --sidebar-dark: #16213e;
            --card-dark: #0f3460;
            
            /* Autres */
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --border-radius: 12px;
            --box-shadow-light: 0 5px 15px rgba(0, 0, 0, 0.1);
            --box-shadow-dark: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Styles de base */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: var(--transition);
        }

        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
        }
        /* Navbar */
        .navbar {
            background-color: var(--secondary) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 12px 0;
        }

        [data-bs-theme="dark"] .navbar {
            background-color: var(--sidebar-dark) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .navbar-brand span {
            color: var(--primary);
        }

        .nav-link {
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 15px;
            margin: 0 5px;
            border-radius: 6px;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background-color: rgba(77, 184, 184, 0.1);
        }


        .correction-header {
            position: relative;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .correction-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .student-info {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .score-card {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #4cc9f0;
        }
        
        .score-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .score-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .score-percentage {
            background: rgba(255, 255, 255, 0.3);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 1rem;
        }
        
        .comment-preview {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #f0f4c3;
        }
        
        .comment-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .comment-content {
            font-style: italic;
            line-height: 1.6;
        }

        .question-container {
            position: relative;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 0;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .question-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .question-header {
            background-color: var(--primary-light);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }

        .question-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background-color: var(--primary);
        }

        .question-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .question-number {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .question-number i {
            font-size: 1.2rem;
        }

        .question-type {
            display: inline-block;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            font-size: 0.85rem;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            margin-left: 1rem;
        }

        .points-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--success);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .question-content {
            padding: 1.5rem;
        }

        .question-text {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--gray-800);
            line-height: 1.7;
        }

        .correct-answer-box {
            background-color: var(--success-light);
            border-radius: 10px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success);
            position: relative;
        }

        .correct-answer-box h5 {
            color: var(--success);
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .correct-answer-content {
            color: var(--gray-800);
        }

        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .qcm-option {
            position: relative;
            padding: 1rem;
            border-radius: 10px;
            background-color: var(--gray-100);
            border: 2px solid var(--gray-300);
            transition: all 0.2s ease;
        }

        .qcm-option.correct {
            background-color: var(--success-light);
            border-color: var(--success);
        }

        .qcm-option.selected {
            border-width: 2px;
            font-weight: 600;
        }

        .qcm-option.selected.correct {
            background-color: var(--success-light);
            border-color: var(--success);
            color: var(--gray-800);
        }

        .qcm-option.selected.incorrect {
            background-color: var(--warning-light);
            border-color: var(--warning);
            color: var(--gray-800);
        }

        .option-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2rem;
        }

        .student-answer-box {
            background-color: var(--gray-100);
            border-radius: 10px;
            padding: 1.2rem;
            margin: 1.5rem 0;
            border-left: 4px solid var(--gray-500);
        }

        .student-answer-box.correct {
            background-color: var(--success-light);
            border-left-color: var(--success);
        }

        .student-answer-box.incorrect {
            background-color: var(--warning-light);
            border-left-color: var(--warning);
        }

        .student-answer-box h5 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .student-answer-content {
            padding: 0.5rem;
            background-color: white;
            border-radius: 6px;
            margin-top: 0.5rem;
        }

        .auto-grade-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1rem;
        }

        .scoring-section {
            display: flex;
            align-items: center;
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: var(--gray-100);
            border-radius: 10px;
        }

        .scoring-label {
            font-weight: 600;
            margin-right: 1rem;
        }

        .scoring-input {
            width: 80px;
            height: 45px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 1.1rem;
            text-align: center;
            font-weight: 600;
            color: var(--gray-800);
        }

        .max-points {
            margin-left: 0.5rem;
            color: var(--gray-600);
            font-weight: 600;
        }

        .comment-section {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .comment-textarea {
            width: 100%;
            border: 2px solid var(--gray-300);
            border-radius: 10px;
            padding: 1rem;
            min-height: 120px;
            font-family: 'Nunito', sans-serif;
            resize: vertical;
        }

        .submit-button {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .alert {
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background: linear-gradient(to right, var(--accent), #27ae60);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .alert-success-score {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.8rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.8rem 1.2rem;
        }
        
        .alert-success-score-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success-score-value {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .alert-success-comment {
            margin-top: 0.8rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.8rem 1.2rem;
        }
        
        .alert-success-comment-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .alert-success-comment-text {
            font-style: italic;
            line-height: 1.5;
        }

        .alert-error {
            background-color: var(--warning-light);
            color: var(--warning);
        }

        .no-response {
            background-color: var(--warning-light);
            color: var(--warning);
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .option-grid {
                grid-template-columns: 1fr;
            }
            
            .question-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .points-badge {
                align-self: flex-start;
            }
        }
        
        /* Thème sombre */
        .theme-toggle {
            background: var(--secondary);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
            box-shadow: 0 5px 15px rgba(240, 165, 0, 0.3);
        }

        .theme-toggle:hover {
            transform: rotate(15deg) scale(1.1);
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <span>Exam</span>Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>
                            Mon profil
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo isset($_SESSION['nom']) ? htmlspecialchars($_SESSION['nom']) : 'Enseignant'; ?>
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container py-5">
        <div class="correction-header">
            <div class="header-content">
                <h1><i class="fas fa-clipboard-check me-2"></i> Correction d'examen</h1>
                <h3><?= htmlspecialchars($exam['titre']) ?></h3>
                <div class="student-info">
                    <i class="fas fa-user-graduate me-2"></i> Étudiant: <?= htmlspecialchars($student['nom']) ?>
                    <span class="text-muted">(<?php echo htmlspecialchars($student['email']); ?>)</span>
                </div>

                <?php if ($displayed_score > 0 || !empty($displayed_comment)): ?>
                    <div class="score-card">
                        <div class="score-title">
                            <i class="fas fa-star me-2"></i> Résultat de l'examen
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="score-value">
                                <?= number_format($displayed_score, 2) ?> / <?= number_format($max_score, 2) ?>
                            </div>
                            <?php 
                            $percentage = ($max_score > 0) ? ($displayed_score / $max_score) * 100 : 0;
                            $percentage_class = $percentage >= 70 ? 'text-success' : ($percentage >= 50 ? 'text-warning' : 'text-danger');
                            ?>
                           
                        </div>
                        
                        <?php if (!empty($displayed_comment)): ?>
                            <div class="comment-preview mt-3">
                                <div class="comment-title">
                                    <i class="fas fa-comment-alt me-2"></i> Commentaire du professeur:
                                </div>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($displayed_comment)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <a href="corriger_examens.php?group_id=<?php echo $group_id; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-light mt-3">
                    <i class="fas fa-arrow-left me-1"></i> Retour à la liste
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error_message ?>
            </div>
        <?php elseif (isset($success_message) && isset($updated_score)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?= $success_message ?>
                
                <div class="alert-success-score">
                    <div class="alert-success-score-title">
                        <i class="fas fa-award"></i> Note finale:
                    </div>
                    <div class="alert-success-score-value">
                        <?= number_format($updated_score, 2) ?> / <?= number_format($max_score, 2) ?> 
                        <!-- (<?= number_format(($updated_score / $max_score) * 100, 1) ?>%) -->
                    </div>
                </div>
                
                <?php if (!empty($updated_comment)): ?>
                <div class="alert-success-comment">
                    <div class="alert-success-comment-title">
                        <i class="fas fa-comment-alt"></i> Commentaire sauvegardé:
                    </div>
                    <div class="alert-success-comment-text">
                        <?= nl2br(htmlspecialchars($updated_comment)) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php $question_number = 1; ?>
            <?php foreach ($questions as $q): ?>
                <?php 
                $isQcm = ($q['type'] === 'QCM' || $q['type'] === 'choix_multiple');
                $rep = $reponses[$q['id']] ?? null;
                $note_auto = 0;
                
                if ($isQcm && $rep) {
                    $note_auto = calculerNoteQCM($rep['reponse'], $q['reponseCorrecte'], $q['point_attribue']);
                }
                ?>
                
                <div class="question-container" id="question-<?= $question_number ?>">
                    <div class="question-header">
                        <h3 class="question-title">
                            <div class="question-number">
                                <i class="fas fa-question-circle"></i> Question <?= $question_number ?>
                                <span class="question-type">
                                    <?= $isQcm ? 'QCM' : 'Question ouverte' ?>
                                </span>
                            </div>
                            <div class="points-badge">
                                <i class="fas fa-star me-1"></i> <?= $q['point_attribue'] ?> points
                            </div>
                        </h3>
                    </div>
                    
                    <div class="question-content">
                        <div class="question-text">
                            <?= htmlspecialchars($q['texte']) ?>
                        </div>
                        
                        <?php if ($isQcm): ?>
                            <div class="correct-answer-box">
                                <h5><i class="fas fa-check-circle"></i> Réponse(s) correcte(s)</h5>
                                <div class="correct-answer-content">
                                    <?= htmlspecialchars($q['reponseCorrecte']) ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($choix[$q['id']])): ?>
                                <h5 class="mb-3">Options disponibles:</h5>
                                <div class="option-grid">
                                    <?php foreach ($choix[$q['id']] as $c): ?>
                                        <?php
                                        $isCorrect = strpos($q['reponseCorrecte'], $c['texte']) !== false;
                                        $isSelected = $rep && strpos($rep['reponse'], $c['texte']) !== false;
                                        $classes = 'qcm-option ';
                                        $classes .= $isCorrect ? 'correct ' : '';
                                        $classes .= $isSelected ? 'selected ' : '';
                                        $classes .= ($isSelected && !$isCorrect) ? 'incorrect' : '';
                                        ?>
                                        <div class="<?= trim($classes) ?>">
                                            <?= htmlspecialchars($c['texte']) ?>
                                            <?php if ($isCorrect && $isSelected): ?>
                                                <span class="option-icon text-success"><i class="fas fa-check-circle"></i></span>
                                            <?php elseif ($isCorrect): ?>
                                                <span class="option-icon text-success"><i class="fas fa-check"></i></span>
                                            <?php elseif ($isSelected): ?>
                                                <span class="option-icon text-danger"><i class="fas fa-times-circle"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($rep): ?>
                            <div class="student-answer-box <?= ($isQcm && $note_auto == $q['point_attribue']) ? 'correct' : ($isQcm && $note_auto < $q['point_attribue'] ? 'incorrect' : '') ?>">
                                <h5>
                                    <i class="fas fa-user-edit"></i> Réponse de l'étudiant
                                    <?php if ($isQcm && $note_auto == $q['point_attribue']): ?>
                                        <span class="ms-2 text-success"><i class="fas fa-check-circle"></i></span>
                                    <?php elseif ($isQcm && $note_auto < $q['point_attribue']): ?>
                                        <span class="ms-2 text-danger"><i class="fas fa-times-circle"></i></span>
                                    <?php endif; ?>
                                </h5>
                                <div class="student-answer-content">
                                    <?= nl2br(htmlspecialchars($rep['reponse'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($isQcm): ?>
                                <div class="auto-grade-badge">
                                    <i class="fas fa-calculator"></i> Note automatique: 
                                    <strong><?= number_format($note_auto, 2) ?></strong> / <?= $q['point_attribue'] ?>
                                </div>
                                <input type="hidden" name="notes[<?= $q['id'] ?>]" value="<?= $note_auto ?>">
                            <?php else: ?>
                                <div class="scoring-section">
                                    <span class="scoring-label">
                                        <i class="fas fa-pen me-2"></i> Note attribuée:
                                    </span>
                                    <input type="number" class="scoring-input" 
                                           name="notes[<?= $q['id'] ?>]" 
                                           min="0" max="<?= $q['point_attribue'] ?>" step="0.25"
                                           value="<?= $rep['note'] ?? 0 ?>" required>
                                    <span class="max-points">/ <?= $q['point_attribue'] ?></span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-response">
                                <i class="fas fa-exclamation-triangle"></i> Aucune réponse fournie par l'étudiant
                            </div>
                            <input type="hidden" name="notes[<?= $q['id'] ?>]" value="0">
                        <?php endif; ?>
                    </div>
                </div>
                <?php $question_number++; ?>
            <?php endforeach; ?>
            
            <div class="comment-section">
                <h4 class="mb-3"><i class="fas fa-comment-alt me-2"></i> Commentaire global</h4>
                <textarea name="commentaire" class="comment-textarea"><?= htmlspecialchars($displayed_comment) ?></textarea>
            </div>
            
            <div class="text-center mb-5">
                <button type="submit" class="submit-button">
                    <i class="fas fa-save"></i> Enregistrer la correction
                </button>
                
                <div class="mt-4 d-flex justify-content-between">
                    <a href="corriger_examens.php?group_id=<?php echo $group_id; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Bouton de changement de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Gestion du thème sombre/clair
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
            
            // Vérifier la préférence système
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Récupérer le thème enregistré ou utiliser la préférence système
            const savedTheme = localStorage.getItem('theme');
            const initialTheme = savedTheme || (prefersDarkScheme.matches ? 'dark' : 'light');
            
            // Appliquer le thème initial
            applyTheme(initialTheme);
            
            // Fonction pour appliquer un thème spécifique
            function applyTheme(theme) {
                htmlElement.setAttribute('data-bs-theme', theme);
                updateThemeIcon(theme);
                localStorage.setItem('theme', theme);
            }
            
            // Mettre à jour l'icône du bouton selon le thème actuel
            function updateThemeIcon(theme) {
                const icon = themeToggle.querySelector('i');
                if (theme === 'dark') {
                    icon.className = 'fas fa-sun';
                } else {
                    icon.className = 'fas fa-moon';
                }
            }
            
            // Écouteur d'événements pour le bouton de basculement
            themeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                applyTheme(newTheme);
            });
            
            <?php if (isset($success_message) && isset($updated_score)): ?>
            // Faire défiler vers l'alerte de succès si présente
            document.querySelector('.alert-success').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
