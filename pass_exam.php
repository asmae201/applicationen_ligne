<?php
// Activation du rapport d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// Vérification de la session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Etudiant') {
    header('Location: login.php');
    exit();
}

// Valeurs par défaut
$examens_disponibles = [];
$examens_passes = [];
$error = null;

try {
    // Récupérer le groupe de l'étudiant
    $stmt = $pdo->prepare("SELECT groupe FROM Utilisateur WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_group = $stmt->fetchColumn();

    // Query pour examens disponibles
    $stmt = $pdo->prepare("
        SELECT 
            e.id, 
            e.titre, 
            e.duree, 
            e.date, 
            e.date_creation, 
            e.description, 
            COALESCE(ee.statut, 'Non commencé') as statut
        FROM Examen e
        LEFT JOIN ExamenEtudiant ee ON e.id = ee.examen_id AND ee.etudiant_id = ?
        WHERE 
            e.date >= CURDATE() 
            AND e.statut = 'publie'
            AND (e.groupe_cible IS NULL OR e.groupe_cible = ?)
        ORDER BY e.date ASC
    ");
    
    $stmt->execute([$_SESSION['user_id'], $student_group]);
    $examens_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query pour examens passés
    $stmt = $pdo->prepare("
        SELECT 
            e.id, 
            e.titre, 
            e.duree, 
            e.date, 
            e.date_creation, 
            e.description, 
            ee.score_final
        FROM Examen e
        JOIN ExamenEtudiant ee ON e.id = ee.examen_id
        WHERE 
            ee.etudiant_id = ? 
            AND ee.statut = 'Terminé'
        ORDER BY e.date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $examens_passes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Erreur lors de la récupération des examens: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des examens.";
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Étudiant | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4db8b8;
            --primary-dark: #3a9a9a;
            --secondary: #34495e;
            --accent: #1abc9c;
            --highlight: #f0a500;
            --text-light: #f8f9fa;
            --text-dark: #212529;
            --bg-light: #f8fafc;
            --bg-dark: #1a1a2e;
            --sidebar-dark: #16213e;
            --card-dark: #0f3460;
        }
        
        [data-bs-theme="dark"] {
            --bs-body-bg: var(--bg-dark);
            --bs-body-color: var(--text-light);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background-color: var(--secondary);
            padding: 20px 0;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        [data-bs-theme="dark"] .sidebar {
            background-color: var(--sidebar-dark);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.3);
        }
        
        .sidebar-brand {
            color: var(--text-light);
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 40px;
            padding: 0 20px;
        }
        
        .sidebar-brand span {
            color: var(--primary);
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-item {
            margin-bottom: 5px;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 0 30px 30px 0;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            box-shadow: 0 4px 15px rgba(26, 188, 156, 0.3);
        }
        
        .sidebar-link i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        [data-bs-theme="dark"] .dashboard-header {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .theme-toggle {
            background: var(--secondary);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
        }
        
        .theme-toggle:hover {
            transform: rotate(30deg);
        }

        /* Cards */
        .dashboard-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            margin-bottom: 30px;
            background-color: white;
        }
        
        [data-bs-theme="dark"] .dashboard-card {
            background-color: var(--card-dark);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        [data-bs-theme="dark"] .dashboard-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        }
        
        .card-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary-dark));
            color: white;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: none;
        }
        
        [data-bs-theme="dark"] .card-header {
            background: linear-gradient(90deg, var(--sidebar-dark), var(--primary-dark));
        }
        
        .card-body {
            padding: 25px;
        }
        
        .exam-card {
            transition: all 0.3s;
            height: 100%;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            background-color: white;
        }
        
        [data-bs-theme="dark"] .exam-card {
            background-color: var(--card-dark);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        [data-bs-theme="dark"] .exam-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .bg-warning {
            background-color: var(--highlight) !important;
        }
        
        .table {
            color: inherit;
        }
        
        [data-bs-theme="dark"] .table {
            --bs-table-bg: var(--card-dark);
            --bs-table-striped-bg: rgba(255, 255, 255, 0.05);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.1);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
            .main-content {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: block !important;
            }
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
        }
    </style>
</head>
<body data-bs-theme="light">
    <!-- Mobile menu button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span>Exam</span>Pro
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard_student.php" class="sidebar-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user"></i>
                    <span>Mon profil</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="pass_exam.php" class="sidebar-link">
                    <i class="fas fa-pencil-alt"></i>
                    <span>Passer un examen</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="results.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Mes résultats</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Tableau de bord - Étudiant</h1>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Available Exams -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i> Examens à venir
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($examens_disponibles): ?>
                        <?php foreach ($examens_disponibles as $examen): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card exam-card h-100">
                                <div class="card-body">
                                    <span class="status-badge bg-<?php echo $examen['statut'] === 'Non commencé' ? 'warning' : 'info'; ?>">
                                        <?php echo htmlspecialchars($examen['statut']); ?>
                                    </span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($examen['titre']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i> Durée: <?php echo $examen['duree']; ?> minutes
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i> Date: <?php echo date('d/m/Y', strtotime($examen['date'])); ?>
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i> Description: <?php echo htmlspecialchars($examen['description']); ?>
                                        </small>
                                    </p>
                                    <?php if ($examen['statut'] === 'Non commencé'): ?>
                                    <a href="take_exam.php?exam_id=<?php echo $examen['id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        Commencer l'examen
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucun examen à venir.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Past Exams -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i> Historique des examens
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Examen</th>
                                <th>Date</th>
                                <th>Score</th>
                                <!-- <th>Actions</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($examens_passes): ?>
                                <?php foreach ($examens_passes as $examen): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($examen['titre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($examen['date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $examen['score_final'] >= 60 ? 'success' : 'danger'; ?>">
                                            <?php echo $examen['score_final']; ?>%
                                        </span>
                                    </td>
                                    <!-- <td>
                                        <a href="exam_details.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Détails
                                        </a>
                                    </td> -->
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-muted">Aucun examen terminé.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du mode sombre
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        // Vérifier le thème stocké
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        
        // Mettre à jour l'icône
        updateThemeIcon(currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            updateThemeIcon(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }
        
        // Gestion du menu mobile
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>
