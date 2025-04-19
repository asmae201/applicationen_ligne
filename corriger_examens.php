<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Récupération des paramètres
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';

// Si aucun examen n'est spécifié, afficher la liste des examens de l'enseignant
if (empty($exam_id)) {
    header('Location:choisir_examen.php');
    exit();
}

// Vérifier si l'examen existe et appartient à l'enseignant
$check_exam = $pdo->prepare("SELECT id, titre FROM Examen WHERE id = ? AND enseignant_id = ?");
$check_exam->execute([$exam_id, $teacher_id]);
$exam = $check_exam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $error_message = "L'examen spécifié n'existe pas ou vous n'avez pas les droits pour y accéder.";
}

// Récupérer les informations du groupe
$group_query = $pdo->prepare("SELECT group_name FROM groups WHERE id = ?");
$group_query->execute([$group_id]);
$group = $group_query->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    $error_message = "Le groupe spécifié n'existe pas.";
}

// Récupérer les étudiants du groupe avec leurs incidents
try {
    $query = "SELECT u.id, u.nom, u.email, 
              COUNT(ei.id) as incident_count,
              MAX(CASE WHEN ei.incident_type = 'tab_switch' THEN 1 ELSE 0 END) as has_tab_switch
              FROM Utilisateur u
              LEFT JOIN ExamenIncident ei ON u.id = ei.student_id AND ei.exam_id = ?
              WHERE u.groupe = ? AND u.role = 'Etudiant'
              GROUP BY u.id
              ORDER BY u.nom ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$exam_id, "Group " . $group_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Une erreur est survenue lors de la récupération des données: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Étudiants - Correction</title>
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

        /* Cartes */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            transition: var(--transition);
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
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        /* Badges statut */
        .badge {
            padding: 8px 12px;
            font-weight: 500;
            border-radius: 6px;
        }

        .status-badge {
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            display: inline-block;
            text-align: center;
            min-width: 140px;
        }

        .status-not-taken {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-in-progress {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-waiting-correction {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-corrected {
            background-color: #d4edda;
            color: #155724;
        }

        /* Table des étudiants */
        .student-table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow-light);
        }

        [data-bs-theme="dark"] .student-table {
            box-shadow: var(--box-shadow-dark);
        }

        .student-table th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 15px;
        }

        .student-table td {
            padding: 15px;
            vertical-align: middle;
        }

        .student-table tbody tr {
            transition: var(--transition);
        }

        .student-table tbody tr:hover {
            background-color: rgba(77, 184, 184, 0.1);
        }

        /* Bouton d'action */
        .btn-action {
            min-width: 120px;
            padding: 8px 12px;
            font-weight: 500;
            background-color: #17a2b8;

            
        }

        .btn-not-taken {
            background-color: #dc3545;
            color: white;
            cursor: not-allowed;
        }

        .btn-waiting-correction {
            background-color: #17a2b8;
            color: white;
        }

        .btn-corrected {
            background-color: #28a745;
            color: white;
            cursor: default;
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

        /* Avatar */
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Badge incident */
        .incident-badge {
            cursor: pointer;
            transition: var(--transition);
        }

        .incident-badge:hover {
            transform: scale(1.05);
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

    <div class="container fade-in">
        <!-- Header -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Liste des Étudiants pour Correction</h2>
                </div>
                <a href="choisir_examen.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i> Retour aux examens
                </a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Examen : <?php echo htmlspecialchars($exam['titre']); ?></h5>
                        <p class="mb-0">Groupe : <?php echo htmlspecialchars($group['group_name']); ?></p>
                    </div>
                    <div>
                        <span class="badge bg-primary">
                            <i class="fas fa-users me-1"></i> <?php echo count($students); ?> étudiants
                        </span>
                    </div>
                </div>
                
                <!-- Recherche -->
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchStudent" class="form-control" placeholder="Rechercher un étudiant...">
                    </div>
                </div>

                <!-- Tableau des étudiants -->
                <div class="table-responsive">
                    <table class="table table-hover student-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Incidents</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    // Vérifier si l'étudiant a participé à l'examen
                                    $check_participation = $pdo->prepare("SELECT COUNT(*) FROM ReponseEtudiant WHERE etudiant_id = ? AND examen_id = ?");
                                    $check_participation->execute([$student['id'], $exam_id]);
                                    $has_participated = ($check_participation->fetchColumn() > 0);
                                    
                                    // Vérifier si l'examen a été corrigé
                                    $check_correction = $pdo->prepare("SELECT statut FROM ExamenEtudiant WHERE etudiant_id = ? AND examen_id = ?");
                                    $check_correction->execute([$student['id'], $exam_id]);
                                    $correction_data = $check_correction->fetch(PDO::FETCH_ASSOC);
                                    $exam_status = $correction_data ? $correction_data['statut'] : null;
                                    
                                    // Déterminer le statut et la classe du badge
                                    if (!$has_participated) {
                                        $status_class = "status-not-taken";
                                        $status_text = "Non participé";
                                        $button_class = "btn-not-taken";
                                        $button_text = "Non pratiqué";
                                        $button_disabled = true;
                                    } elseif ($exam_status == 'Corrigé') {
                                        $status_class = "status-corrected";
                                        $status_text = "Déjà corrigé";
                                        $button_class = "btn-corrected";
                                        $button_text = "Déjà corrigé";
                                        $button_disabled = false;
                                    } else {
                                        $status_class = "status-waiting-correction";
                                        $status_text = "À corriger";
                                        $button_class = "btn-waiting-correction";
                                        $button_text = "Corriger";
                                        $button_disabled = false;
                                    }
                                    ?>
                                    <tr class="student-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-3 bg-primary">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($student['nom']); ?></h6>
                                                    <?php if ($student['incident_count'] > 0): ?>
                                                        <small class="text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> 
                                                            Comportement suspect détecté
                                                            <?php if ($student['has_tab_switch']): ?>
                                                                (Changement d'onglet)
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <?php if ($student['incident_count'] > 0): ?>
                                                <span class="badge bg-danger incident-badge" 
                                                      data-bs-toggle="tooltip" 
                                                      title="<?php echo $student['incident_count'] ?> incident(s) détecté(s)">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?php echo $student['incident_count'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    Aucun
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($button_disabled): ?>
                                                <button class="btn btn-action <?php echo $button_class; ?>" disabled>
                                                    <?php echo $button_text; ?>
                                                </button>
                                            <?php else: ?>
                                                
                                                <a href="corriger_student.php?exam_id=<?= $exam_id ?>&student_id=<?= $student['id'] ?>&group_id=<?= $group_id ?>" 
   class="btn btn-action">

                                                    <?php if ($exam_status == 'Corrigé'): ?>
                                                        <i class="fas fa-check-circle me-1"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-pen me-1"></i>
                                                    <?php endif; ?>
                                                    <?php echo $button_text; ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="py-5">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p>Aucun étudiant trouvé dans ce groupe.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour afficher les détails des incidents -->
    <div class="modal fade" id="incidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails des incidents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="incidentDetails">
                    <!-- Les détails seront chargés ici via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton de changement de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Recherche d'étudiants
        document.getElementById('searchStudent').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const name = row.querySelector('h6').textContent.toLowerCase();
                const email = row.querySelectorAll('td')[1].textContent.toLowerCase();
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

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

            // Activer les tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Gestion des clics sur les badges d'incident
            const incidentBadges = document.querySelectorAll('.incident-badge');
            
            incidentBadges.forEach(badge => {
                badge.addEventListener('click', function() {
                    const studentId = this.closest('tr').querySelector('a.btn-action').href.split('student_id=')[1].split('&')[0];
                    const examId = '<?php echo $exam_id; ?>';
                    
                    fetch(`get_incidents.php?exam_id=${examId}&student_id=${studentId}`)
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('incidentDetails').innerHTML = data;
                            const modal = new bootstrap.Modal(document.getElementById('incidentModal'));
                            modal.show();
                        });
                });
            });
        });
    </script>
</body>
</html>
