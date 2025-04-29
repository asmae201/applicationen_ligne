<?php
session_start();
require_once('db.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['questionId']) || empty($_GET['questionId']) || !isset($_GET['examenId']) || empty($_GET['examenId'])) {
    die("ID de la question ou de l'examen non valide.");
}

$question_id = $_GET['questionId'];
$exam_id = $_GET['examenId'];

// Récupérer les informations de la question
try {
    $stmt = $pdo->prepare("SELECT * FROM question WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        die("Question non trouvée.");
    }

    // Récupérer les choix pour QCM
    $choices = [];
    if ($question['type'] === 'QCM') {
        $stmt = $pdo->prepare("SELECT * FROM choix WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    try {
        $pdo->beginTransaction();

        $question_text = trim($_POST['question_text']);
        $question_type = trim($_POST['question_type']);
        $question_note = isset($_POST['question_note']) ? (float)$_POST['question_note'] : 0;

        if (empty($question_text)) {
            throw new Exception("Le texte de la question ne peut pas être vide.");
        }

        if ($question_type === 'QCM') {
            if (isset($_POST['correct_answers']) && !empty($_POST['correct_answers'])) {
                // Récupérer les textes des réponses correctes
                $correct_answers_text = [];
                foreach ($_POST['correct_answers'] as $index) {
                    if (isset($_POST['choices'][$index])) {
                        $correct_answers_text[] = trim($_POST['choices'][$index]);
                    }
                }
                
                if (!empty($correct_answers_text)) {
                    $correct_answer = implode(',', $correct_answers_text);
                } else {
                    throw new Exception("Veuillez sélectionner au moins une réponse correcte valide pour le QCM.");
                }
            } else {
                throw new Exception("Veuillez sélectionner au moins une réponse correcte pour le QCM.");
            }
        } else if ($question_type === 'Ouverte') {
            $correct_answer = null;
        } else {
            throw new Exception("Type de question non valide.");
        }

        // Gestion de l'image
        $image_path = $question['image_path'];
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/questions/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Supprimer l'ancienne image si elle existe
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            
            $file_extension = pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'question_' . $question_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                throw new Exception("Type de fichier non autorisé. Seuls JPG, JPEG, PNG et GIF sont acceptés.");
            }
            
            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            } else {
                throw new Exception("Erreur lors du téléchargement de l'image.");
            }
        }

        $mode_correction = ($question_type === 'Ouverte') ? 'manuel' : 'auto';

        // Mettre à jour la question
        $stmt = $pdo->prepare("UPDATE question SET type = ?, texte = ?, point_attribue = ?, reponseCorrecte = ?, image_path = ?, mode_correction = ? WHERE id = ?");
        $stmt->execute([
            $question_type,
            $question_text,
            $question_note,
            $correct_answer,
            $image_path,
            $mode_correction,
            $question_id
        ]);

        // Supprimer les anciens choix
        $stmt = $pdo->prepare("DELETE FROM choix WHERE question_id = ?");
        $stmt->execute([$question_id]);

        // Ajouter les nouveaux choix si QCM
        if ($question_type === 'QCM' && isset($_POST['choices'])) {
            $stmt = $pdo->prepare("INSERT INTO choix (id, question_id, texte) VALUES (?, ?, ?)");

            foreach ($_POST['choices'] as $key => $choice) {
                if (!empty(trim($choice))) {
                    $choice_id = uniqid('c_');
                    $stmt->execute([$choice_id, $question_id, trim($choice)]);
                }
            }
        }

        $pdo->commit();

        $_SESSION['message'] = "Question mise à jour avec succès.";
        $_SESSION['alert_type'] = "success";
        header("Location: view_questions.php?examenId=$exam_id");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Erreur : " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une Question</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* [Tous les styles CSS de la page add_question.php peuvent être conservés ici] */
        :root {
            /* Couleurs principales */
            --primary: #4db8b8;
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

        /* Header & Navigation */
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

        /* Boutons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 20px;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--accent));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
        }

        .btn-secondary {
            background-color: var(--secondary);
            border: none;
        }

        .btn-secondary:hover {
            background-color: var(--sidebar-dark);
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: var(--highlight);
            border: none;
            color: white;
        }

        .btn-warning:hover {
            background-color: #e09800;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: #e74c3c;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        /* Tableau */
        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow-light);
            margin-top: 20px;
        }

        [data-bs-theme="dark"] .table {
            box-shadow: var(--box-shadow-dark);
            color: var(--text-light);
        }

        .table-dark th {
            background: linear-gradient(to right, var(--secondary), var(--sidebar-dark)) !important;
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        [data-bs-theme="dark"] .table tbody tr {
            background-color: var(--card-dark);
            border-color: var(--sidebar-dark);
        }

        [data-bs-theme="dark"] .table tbody tr:nth-of-type(odd) {
            background-color: var(--sidebar-dark);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            transition: var(--transition);
            overflow: hidden;
            margin-bottom: 20px;
        }

        [data-bs-theme="dark"] .card {
            background-color: var(--card-dark);
            box-shadow: var(--box-shadow-dark);
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            padding: 15px 20px;
            border: none;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: rgba(25, 135, 84, 0.1);
            border-left: 4px solid #198754;
            color: #0f5132;
        }

        [data-bs-theme="dark"] .alert-success {
            background-color: rgba(25, 135, 84, 0.2);
            color: #d1e7dd;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            color: #842029;
        }

        [data-bs-theme="dark"] .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
        }

        /* Badges pour les types de questions */
        .badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge-qcm {
            background-color: var(--accent);
            color: white;
        }

        .badge-ouverte {
            background-color: var(--highlight);
            color: white;
        }

        /* Listes dans le tableau */
        .table ul {
            padding-left: 18px;
            margin-bottom: 0;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .main-container {
            animation: fadeIn 0.5s ease-out forwards;
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

        .theme-toggle-animate {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2) rotate(180deg); }
            100% { transform: scale(1); }
        }
        
        /* Header avec gradient */
        .page-header {
            background: linear-gradient(to right, var(--primary), var(--accent));
            color: white;
            padding: 30px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="5" /></svg>') repeat;
            opacity: 0.2;
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 600;
            font-size: 2rem;
        }
        
        .page-header p {
            margin: 10px 0 0;
            opacity: 0.8;
        }
        
        /* Question count badge */
        .question-count {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
        }

        /* Styles spécifiques pour le formulaire d'édition */
        .choice-container {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #eaeaea;
            transition: var(--transition);
        }

        [data-bs-theme="dark"] .choice-container {
            background-color: var(--sidebar-dark);
            border-color: var(--card-dark);
        }

        .choice-container:hover {
            transform: translateX(5px);
            border-left: 3px solid var(--primary);
        }

        .remove-choice {
            color: #dc3545;
            cursor: pointer;
            transition: var(--transition);
        }

        .remove-choice:hover {
            transform: scale(1.2);
        }

        .image-preview {
            max-width: 100%;
            max-height: 250px;
            display: block;
            margin-top: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            transition: var(--transition);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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

    <div class="container main-container">
        <!-- En-tête de page -->
        <div class="page-header text-center text-md-start">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-edit me-2"></i>
                        Modifier la question
                    </h1>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="view_questions.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i>
                        Retour aux questions
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>" role="alert">
                <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['alert_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" id="questionForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="question_type" class="form-label">Type de question</label>
                        <select class="form-select" id="question_type" name="question_type" required>
                            <option value="QCM" <?php echo $question['type'] === 'QCM' ? 'selected' : ''; ?>>QCM</option>
                            <option value="Ouverte" <?php echo $question['type'] === 'Ouverte' ? 'selected' : ''; ?>>Question ouverte</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="question_text" class="form-label">Texte de la question</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo htmlspecialchars($question['texte']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_image" class="form-label">Image (optionnel)</label>
                        <input type="file" class="form-control" id="question_image" name="question_image" accept="image/*">
                        <small class="text-muted">Formats acceptés: JPG, JPEG, PNG, GIF</small>
                        <?php if ($question['image_path']): ?>
                            <div class="mt-2">
                                <p class="mb-1">Image actuelle:</p>
                                <img src="<?php echo $question['image_path']; ?>" class="img-thumbnail" style="max-height: 150px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                    <label class="form-check-label" for="remove_image">Supprimer cette image</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <img id="imagePreview" class="image-preview" src="#" alt="Aperçu de la nouvelle image">
                    </div>

                    <div class="mb-3">
                        <label for="question_note" class="form-label">Points attribués</label>
                        <input type="number" class="form-control" id="question_note" name="question_note" min="0" step="0.5" value="<?php echo htmlspecialchars($question['point_attribue']); ?>" required>
                    </div>

                    <div id="qcm_section" <?php echo $question['type'] === 'Ouverte' ? 'style="display: none;"' : ''; ?>>
                        <div class="mb-3">
                            <label class="form-label">Choix de réponses</label>
                            <div id="choices_container">
                                <!-- Choix seront ajoutés ici dynamiquement -->
                                <?php if ($question['type'] === 'QCM' && !empty($choices)): ?>
                                    <?php 
                                    $correct_answers = explode(',', $question['reponseCorrecte']);
                                    foreach ($choices as $index => $choice): 
                                        $is_correct = in_array($choice['texte'], $correct_answers);
                                    ?>
                                        <div class="choice-container">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <input type="text" class="form-control choice-text" name="choices[]" 
                                                           value="<?php echo htmlspecialchars($choice['texte']); ?>" required>
                                                </div>
                                                <div class="col-auto">
                                                    <input class="form-check-input correct-answer" type="checkbox" 
                                                           name="correct_answers[]" value="<?php echo $index; ?>"
                                                           <?php echo $is_correct ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">Correct</label>
                                                </div>
                                                <div class="col-auto">
                                                    <span class="remove-choice" onclick="removeChoice(this)">❌</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-secondary mt-2" id="add_choice">+ Ajouter un choix</button>
                        </div>
                    </div>

                    <div class="mb-3" id="question_ouverte_info" <?php echo $question['type'] === 'QCM' ? 'style="display: none;"' : ''; ?>>
                        <div class="alert alert-info">
                            <strong>Information:</strong> Les questions ouvertes nécessitent une correction manuelle.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" name="update_question">Mettre à jour la question</button>
                        <a href="view_questions.php?examenId=<?php echo $exam_id; ?>" class="btn btn-secondary mt-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bouton de changement de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const questionType = document.getElementById('question_type');
        const qcmSection = document.getElementById('qcm_section');
        const choicesContainer = document.getElementById('choices_container');
        const imageInput = document.getElementById('question_image');
        const imagePreview = document.getElementById('imagePreview');
        const questionOuverteInfo = document.getElementById('question_ouverte_info');

        function createChoice() {
            const choiceDiv = document.createElement('div');
            choiceDiv.className = 'choice-container';

            const choiceNumber = choicesContainer.children.length + 1;
            const isQCM = document.getElementById('question_type').value === 'QCM';
            
            choiceDiv.innerHTML = `
                <div class="row align-items-center">
                    <div class="col">
                        <input type="text" class="form-control choice-text" name="choices[]" placeholder="Choix ${choiceNumber}" ${isQCM ? 'required' : ''}>
                    </div>
                    <div class="col-auto">
                        <input class="form-check-input correct-answer" type="checkbox" name="correct_answers[]" value="${choiceNumber - 1}">
                        <label class="form-check-label">Correct</label>
                    </div>
                    <div class="col-auto">
                        <span class="remove-choice" onclick="removeChoice(this)">❌</span>
                    </div>
                </div>
            `;

            choicesContainer.appendChild(choiceDiv);
        }

        function removeChoice(element) {
            element.closest('.choice-container').remove();
        }

        document.getElementById('add_choice').addEventListener('click', createChoice);

        questionType.addEventListener('change', function() {
            if (this.value === 'QCM') {
                qcmSection.style.display = 'block';
                questionOuverteInfo.style.display = 'none';
                
                document.querySelectorAll('input[name="choices[]"]').forEach(input => {
                    input.setAttribute('required', true);
                });
                
                if (choicesContainer.children.length < 2) {
                    createChoice();
                    createChoice();
                }
            } else {
                qcmSection.style.display = 'none';
                questionOuverteInfo.style.display = 'block';
                
                document.querySelectorAll('input[name="choices[]"]').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        });

        document.getElementById('questionForm').addEventListener('submit', function(e) {
            if (questionType.value === 'QCM') {
                if (document.querySelectorAll('input[name="choices[]"]').length < 2) {
                    e.preventDefault();
                    alert('Ajoutez au moins 2 choix.');
                    return;
                }
                
                const correctAnswersSelected = document.querySelectorAll('input[name="correct_answers[]"]:checked');
                if (correctAnswersSelected.length === 0) {
                    e.preventDefault();
                    alert('Veuillez sélectionner au moins une réponse correcte.');
                    return;
                }
                
                let hasEmptySelected = false;
                let hasEmptyChoice = false;
                
                document.querySelectorAll('input[name="correct_answers[]"]:checked').forEach(checkbox => {
                    const choiceIndex = Array.from(document.querySelectorAll('input[name="correct_answers[]"]')).indexOf(checkbox);
                    const choiceText = document.querySelectorAll('.choice-text')[choiceIndex].value.trim();
                    if (choiceText === '') {
                        hasEmptySelected = true;
                    }
                });
                
                document.querySelectorAll('.choice-text').forEach(input => {
                    if (input.value.trim() === '') {
                        hasEmptyChoice = true;
                    }
                });
                
                if (hasEmptySelected) {
                    e.preventDefault();
                    alert('Les réponses correctes sélectionnées doivent avoir du texte.');
                    return;
                }
                
                if (hasEmptyChoice) {
                    e.preventDefault();
                    alert('Tous les choix doivent avoir du texte ou être supprimés.');
                    return;
                }
            }
        });
                
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                
                reader.readAsDataURL(this.files[0]);
            } else {
                imagePreview.style.display = 'none';
            }
        });

        // Gestion du mode sombre
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
                
                // Animation de transition du bouton
                themeToggle.classList.add('theme-toggle-animate');
                setTimeout(() => {
                    themeToggle.classList.remove('theme-toggle-animate');
                }, 500);
                
                applyTheme(newTheme);
            });
            
            // Écouter les changements de préférence système
            prefersDarkScheme.addEventListener('change', (e) => {
                const newTheme = e.matches ? 'dark' : 'light';
                // Ne changer que si l'utilisateur n'a pas explicitement choisi un thème
                if (!localStorage.getItem('theme')) {
                    applyTheme(newTheme);
                }
            });
        });
    </script>
</body>
</html>
