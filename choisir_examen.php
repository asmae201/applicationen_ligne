<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: login.php');
    exit();
}

// Récupération des groupes et des examens
try {
    $query_groups = "SELECT id, group_name FROM groups ORDER BY group_name";
    $query_exams = "SELECT id, titre FROM examen ORDER BY titre";

    $stmt_groups = $pdo->prepare($query_groups);
    $stmt_exams = $pdo->prepare($query_exams);

    $stmt_groups->execute();
    $stmt_exams->execute();

    $groups = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);
    $exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors de la récupération des données.";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['group_id']) && isset($_POST['exam_id'])) {
        // Récupérer et nettoyer les valeurs du formulaire
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
        $exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Vérification des valeurs
        if ($group_id && $exam_id) {
            // Redirection vers la page de correction
            header("Location: corriger_examens.php?group_id=$group_id&exam_id=" . urlencode($exam_id));
            exit;
        } else {
            $error_message = "Veuillez sélectionner un groupe et un examen valides.";
        }
    } else {
        $error_message = "Veuillez remplir tous les champs du formulaire.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélection du Groupe et Examen | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
            padding: 10px 20px;
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

        /* Formulaires et sélecteurs */
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        [data-bs-theme="dark"] .form-label {
            color: var(--text-light);
        }

        .form-select, .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: var(--transition);
            background-color: #fff;
        }

        [data-bs-theme="dark"] .form-select, [data-bs-theme="dark"] .form-control {
            background-color: var(--card-dark);
            border-color: var(--sidebar-dark);
            color: var(--text-light);
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
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

        .card-header {
            background: linear-gradient(to right, var(--primary), var(--accent));
            color: white;
            padding: 20px;
            border: none;
        }

        [data-bs-theme="dark"] .card-header {
            background: linear-gradient(to right, var(--primary-dark), var(--accent));
        }
        
        /* Animation de fade-in */
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
        <!-- Page Header -->
        <div class="page-header text-center text-md-start">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-users me-2"></i>
                        Sélection du Groupe et de l'Examen
                    </h1>
                    <p>Choisissez un groupe et un examen pour voir les résultats des étudiants</p>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="group_id" class="form-label">
                            <i class="fas fa-users-class me-1"></i>
                            Sélectionner un groupe
                        </label>
                        <select name="group_id" id="group_id" class="form-select" required>
                            <option value="">Choisir un groupe...</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo htmlspecialchars($group['id']); ?>">
                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner un groupe.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="exam_id" class="form-label">
                            <i class="fas fa-file-alt me-1"></i>
                            Sélectionner un examen
                        </label>
                        <select name="exam_id" id="exam_id" class="form-select" required>
                            <option value="">Choisir un examen...</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo htmlspecialchars($exam['id']); ?>">
                                    <?php echo htmlspecialchars($exam['titre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner un examen.
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle me-2"></i>
                            Afficher les Étudiants
                        </button>
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

        // Validation du formulaire
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>
