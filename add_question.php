<?php
session_start();
require_once('db.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['examenId']) || empty($_GET['examenId'])) {
    die("ID de l'examen non valide.");
}

$exam_id = $_GET['examenId'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    try {
        $pdo->beginTransaction();

        $question_text = trim($_POST['question_text']);
        $question_type = trim($_POST['question_type']);
        $question_note = isset($_POST['question_note']) ? (float)$_POST['question_note'] : 0;

        if (empty($question_text)) {
            throw new Exception("Le texte de la question ne peut pas être vide.");
        }

        $question_id = uniqid('q_');
        
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
                    $correct_answer = implode(',', $correct_answers_text); // Stocker comme "cc,bb"
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

        $image_path = null;
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/questions/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
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

        try {
            $checkColumnQuery = "SHOW COLUMNS FROM Question LIKE 'mode_correction'";
            $stmt = $pdo->query($checkColumnQuery);
            if ($stmt->rowCount() == 0) {
                $alterTableQuery = "ALTER TABLE Question ADD COLUMN mode_correction VARCHAR(50) DEFAULT 'auto'";
                $pdo->exec($alterTableQuery);
            }
            
            $checkColumnQuery = "SHOW COLUMNS FROM Question LIKE 'image_path'";
            $stmt = $pdo->query($checkColumnQuery);
            if ($stmt->rowCount() == 0) {
                $alterTableQuery = "ALTER TABLE Question ADD COLUMN image_path VARCHAR(255) DEFAULT NULL";
                $pdo->exec($alterTableQuery);
            }
        } catch (Exception $e) {
            // Ignorer si les colonnes existent déjà
        }

        $stmt = $pdo->prepare("INSERT INTO Question (id, type, texte, examen_id, point_attribue, reponseCorrecte, image_path, mode_correction) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$question_id, $question_type, $question_text, $exam_id, $question_note, $correct_answer, $image_path, $mode_correction]);

        if ($question_type === 'QCM' && isset($_POST['choices'])) {
            $stmt = $pdo->prepare("INSERT INTO Choix (id, question_id, texte) VALUES (?, ?, ?)");

            foreach ($_POST['choices'] as $key => $choice) {
                if (!empty(trim($choice))) {
                    $choice_id = uniqid('c_');
                    $stmt->execute([$choice_id, $question_id, trim($choice)]);
                }
            }
        }

        $pdo->commit();

        $_SESSION['message'] = "Question ajoutée avec succès.";
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
    <title>Ajouter une Question</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .choice-container {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .remove-choice {
            color: red;
            cursor: pointer;
        }
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            display: none;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
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

.btn-logout {
    background-color: var(--primary);
    color: white;
}

.btn-logout:hover {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
}

/* Cards */
.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow-light);
    transition: var(--transition);
    overflow: hidden;
}

.card:hover {
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    transform: translateY(-5px);
}

[data-bs-theme="dark"] .card {
    background-color: var(--card-dark);
    box-shadow: var(--box-shadow-dark);
}

.card-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: var(--text-light);
    font-weight: 600;
    padding: 15px 20px;
    border: none;
}

.card-body {
    padding: 25px;
}

/* Forms */
.form-control, .form-select {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #dee2e6;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
}

.input-group-text {
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: 8px 0 0 8px;
}

/* Question Types */
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

/* Alerts */
.alert {
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow-light);
    padding: 15px 20px;
    border: none;
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

.alert-info {
    background-color: rgba(13, 202, 240, 0.1);
    border-left: 4px solid #0dcaf0;
    color: #055160;
}

[data-bs-theme="dark"] .alert-info {
    background-color: rgba(13, 202, 240, 0.2);
    color: #cff4fc;
}

/* Image Preview */
.image-preview {
    max-width: 100%;
    max-height: 250px;
    display: block;
    margin-top: 15px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow-light);
    transition: var(--transition);
}

/* Toggles */
.theme-toggle {
    background: var(--secondary);
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: var(--transition);
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 1000;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

[data-bs-theme="dark"] .theme-toggle {
    background: var(--highlight);
}

.theme-toggle:hover {
    transform: rotate(30deg) scale(1.1);
}

/* Profile Components */
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 20px;
    box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
    transition: var(--transition);
}

.profile-avatar:hover {
    transform: scale(1.05) rotate(5deg);
}

.nav-pills .nav-link {
    color: var(--secondary);
    font-weight: 500;
    border-radius: 8px;
    transition: var(--transition);
    margin: 0 5px;
}

.nav-pills .nav-link.active {
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    box-shadow: 0 4px 15px rgba(78, 184, 184, 0.3);
}

[data-bs-theme="dark"] .nav-pills .nav-link {
    color: var(--text-light);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.profile-card, .card {
    animation: fadeIn 0.5s ease-out forwards;
}
/* Styles du bouton de changement de thème */
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
    outline: none;
}

[data-bs-theme="dark"] .theme-toggle {
    background: var(--highlight);
    box-shadow: 0 5px 15px rgba(240, 165, 0, 0.3);
}

.theme-toggle:hover {
    transform: rotate(15deg) scale(1.1);
}

.theme-toggle:active {
    transform: scale(0.95);
}

/* Animation pour le basculement du thème */
.theme-toggle-animate {
    animation: pulse 0.5s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2) rotate(180deg); }
    100% { transform: scale(1); }
}

/* Transition douce lors du changement de thème */
body, .card, .navbar, .alert, .form-control, .btn, 
.choice-container, .profile-avatar, .nav-link {
    transition: all 0.3s ease;
}

/* Ajustements spécifiques pour le mode sombre */
[data-bs-theme="dark"] {
    /* Fond et texte */
    --bs-body-bg: var(--bg-dark);
    --bs-body-color: var(--text-light);
    
    /* Inputs et formulaires */
    --bs-form-control-bg: var(--sidebar-dark);
    --bs-border-color: #2c3e50;
}

[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select {
    background-color: var(--sidebar-dark);
    border-color: var(--card-dark);
    color: var(--text-light);
}

[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus {
    background-color: var(--sidebar-dark);
    border-color: var(--primary);
}

[data-bs-theme="dark"] .input-group-text {
    background-color: var(--primary-dark);
}

/* Transitions pour les paragraphes et textes */
p, h1, h2, h3, h4, h5, h6, .form-label, .text-muted {
    transition: color 0.3s ease;
}

/* Indicateur visuel du mode sombre */
[data-bs-theme="dark"] .navbar::after {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(to right, var(--primary), var(--highlight));
    opacity: 0.7;
}

    </style>
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-4">Ajouter une Question</h1>

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
                            <option value="QCM">QCM</option>
                            <option value="Ouverte">Question ouverte</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="question_text" class="form-label">Texte de la question</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_image" class="form-label">Image (optionnel)</label>
                        <input type="file" class="form-control" id="question_image" name="question_image" accept="image/*">
                        <small class="text-muted">Formats acceptés: JPG, JPEG, PNG, GIF</small>
                        <img id="imagePreview" class="image-preview" src="#" alt="Aperçu de l'image">
                    </div>

                    <div class="mb-3">
                        <label for="question_note" class="form-label">Points attribués</label>
                        <input type="number" class="form-control" id="question_note" name="question_note" min="0" step="0.5" value="1" required>
                    </div>

                    <div id="qcm_section">
                        <div class="mb-3">
                            <label class="form-label">Choix de réponses</label>
                            <div id="choices_container">
                                <!-- Choix seront ajoutés ici -->
                            </div>
                            <button type="button" class="btn btn-secondary mt-2" id="add_choice">+ Ajouter un choix</button>
                        </div>
                    </div>

                    <div class="mb-3" id="question_ouverte_info" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Information:</strong> Les questions ouvertes nécessitent une correction manuelle.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" name="add_question">Ajouter la question</button>
                        <a href="view_questions.php?examenId=<?php echo $exam_id; ?>" class="btn btn-secondary mt-2">Retour</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Créer des choix initiaux
        createChoice();
        createChoice();

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

        questionType.dispatchEvent(new Event('change'));
    </script>
</body>
</html>
