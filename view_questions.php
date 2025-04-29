<?php
session_start();
require_once('db.php');

// Vérification de la session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

// Vérification de l'ID examen
if (!isset($_GET['examenId']) || empty($_GET['examenId'])) {
    die("ID dyal examen machi valide.");
}

$exam_id = $_GET['examenId'];

try {
    // Récupérer les questions
    $stmt = $pdo->prepare("SELECT * FROM question WHERE examen_id = ?");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le titre de l'examen
    $stmt = $pdo->prepare("SELECT titre FROM examen WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    $exam_title = $exam ? $exam['titre'] : "Examen";
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions d'Examen | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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
        
        /* Bouton d'export Word */
        .btn-word {
            background-color: #2b579a;
            color: white;
            border: none;
        }
        
        .btn-word:hover {
            background-color: #1e3f73;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(43, 87, 154, 0.3);
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
                        <i class="fas fa-question-circle me-2"></i>
                        Questions de <?php echo htmlspecialchars($exam_title); ?>
                        <span class="question-count">
                            <?php echo count($questions); ?> question<?php echo count($questions) > 1 ? 's' : ''; ?>
                        </span>
                    </h1>
                </div>
                <!-- <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="export_word.php?examenId=<?php echo $exam_id; ?>" class="btn btn-word me-2">
                        <i class="fas fa-file-word me-1"></i>
                        Télécharger Word
                    </a>
                    <a href="add_question.php?examenId=<?php echo $exam_id; ?>" class="btn btn-light">
                        <i class="fas fa-plus-circle me-1"></i>
                        Ajouter Question
                    </a>
                </div> -->
            </div>
        </div>

        <?php if (empty($questions)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                    <h3>Aucune question trouvée</h3>
                    <a href="add_question.php?examenId=<?php echo $exam_id; ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-plus-circle me-1"></i>
                        Ajouter une Question
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Question</th>
                                    <th>Type</th>
                                    <th>Choix</th>
                                    <th>Points</th>
                                    <th>Réponse Correcte</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $question): ?>
                                    <tr>
                                        <td><code><?php echo substr($question['id'], 0, 8); ?>...</code></td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($question['texte'], 0, 50)); ?>
                                            <?php if (!empty($question['image_path'])): ?>
                                                <span class="badge bg-info"><i class="fas fa-image"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $question['type'] === 'QCM' ? 'bg-primary' : 'bg-warning'; ?>">
                                                <?php echo $question['type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($question['type'] === 'QCM'): ?>
                                                <?php
                                                $stmt = $pdo->prepare("SELECT texte FROM choix WHERE question_id = ?");
                                                $stmt->execute([$question['id']]);
                                                $choix = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                                echo '<ul class="mb-0">';
                                                foreach ($choix as $c) {
                                                    echo '<li>' . htmlspecialchars($c) . '</li>';
                                                }
                                                echo '</ul>';
                                                ?>
                                            <?php else: ?>
                                                <em>Réponse libre</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $question['point_attribue']; ?></td>
                                        <td>
                                            <?php if ($question['type'] === 'QCM'): ?>
                                                <?php
                                                if (!empty($question['reponseCorrecte'])) {
                                                    // Si reponseCorrecte est un index numérique
                                                    if (is_numeric($question['reponseCorrecte'])) {
                                                        $correctIndex = (int)$question['reponseCorrecte'];
                                                        $stmt = $pdo->prepare("SELECT texte FROM choix WHERE question_id = ?");
                                                        $stmt->execute([$question['id']]);
                                                        $allChoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                                        
                                                        if (isset($allChoices[$correctIndex])) {
                                                            echo '<span class="badge bg-success">' . 
                                                                 htmlspecialchars($allChoices[$correctIndex]) . '</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">Index invalide</span>';
                                                        }
                                                    } 
                                                    // Si reponseCorrecte est déjà le texte
                                                    else {
                                                        echo '<span class="badge bg-success">' . 
                                                             htmlspecialchars($question['reponseCorrecte']) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">Non définie</span>';
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="badge bg-info">Correction manuelle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_question.php?questionId=<?php echo $question['id']; ?>&examenId=<?php echo $exam_id; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_question.php?questionId=<?php echo $question['id']; ?>&examenId=<?php echo $exam_id; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Voulez-vous vraiment supprimer cette question?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center mt-4 gap-2">
                <a href="export_word.php?examenId=<?php echo $exam_id; ?>" class="btn btn-word">
                    <i class="fas fa-file-word me-1"></i>
                    Télécharger Word
                </a>
                <a href="add_question.php?examenId=<?php echo $exam_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-1"></i>
                    Ajouter une Question
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour au Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bouton de changement de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
