<?php
session_start();
include('db.php');

// Vérification si l'utilisateur est un étudiant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Etudiant') {
    header('Location: login.php');
    exit();
}

// Récupérer les résultats de l'étudiant
$student_id = $_SESSION['user_id'];

// Requête SQL pour récupérer uniquement la note la plus récente pour chaque examen
$sql_results = "SELECT n.*, e.titre AS examen_titre 
                FROM note n 
                JOIN Examen e ON n.examen_id = e.id 
                JOIN (
                    SELECT examen_id, MAX(date_note) as max_date 
                    FROM note 
                    WHERE etudiant_id = ? 
                    GROUP BY examen_id
                ) as latest ON n.examen_id = latest.examen_id AND n.date_note = latest.max_date
                WHERE n.etudiant_id = ?
                ORDER BY n.date_note DESC";

$stmt_results = $conn->prepare($sql_results);
$stmt_results->bind_param("ss", $student_id, $student_id);
$stmt_results->execute();
$result_results = $stmt_results->get_result();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Résultats - Espace Étudiant | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --warning-rgb: 255, 193, 7;
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
            opacity: 0;
            transition: opacity 0.5s ease;
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

        /* Dashboard card */
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

        /* Table styles */
        .table-results {
            margin-top: 20px;
            --bs-table-bg: transparent;
        }
        
        .table-results thead {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
        }
        
        .table-results th {
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: none;
        }
        
        .status-passed {
            color: var(--success);
            font-weight: bold;
        }
        
        .status-failed {
            color: var(--danger);
            font-weight: bold;
        }
        
        .no-results {
            text-align: center;
            color: #7f8c8d;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }
        
        [data-bs-theme="dark"] .no-results {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .note-pending {
            background-color: rgba(var(--warning-rgb), 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--warning);
        }
        
        [data-bs-theme="dark"] .note-pending {
            background-color: rgba(var(--warning-rgb), 0.2);
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
                <a href="dashboard_student.php" class="sidebar-link">
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
                <a href="results.php" class="sidebar-link active">
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
            <h1>Mes Résultats Académiques</h1>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i> Détails de mes performances
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result_results->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-results">
                            <thead>
                                <tr>
                                    <th scope="col">Examen</th>
                                    <th scope="col">Score</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result_results->fetch_assoc()): ?>
                                    <?php 
                                    // Déterminer le statut basé sur la note
                                    $statut = ($row['score'] >= 10) ? 'Validé' : 'Invalide';
                                    $statusClass = ($row['score'] >= 10) ? 'status-passed' : 'status-failed';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['examen_titre']); ?></td>
                                        <td><?php echo htmlspecialchars($row['score']); ?> / 20</td>
                                        <td><?php echo date('d/m/Y', strtotime($row['date_note'])); ?></td>
                                        <td>
                                            <span class="<?php echo $statusClass; ?>">
                                                <?php echo $statut; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (isset($row['commentaire']) && !empty($row['commentaire'])) {
                                                echo htmlspecialchars($row['commentaire']);
                                            } else {
                                                echo '<span class="text-muted">Aucun commentaire</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="note-pending">
                        <h4><i class="fas fa-info-circle me-2"></i>Aucun résultat disponible</h4>
                        <p>Vos examens n'ont pas encore été notés par votre professeur. Merci de vérifier ultérieurement.</p>
                    </div>
                    <div class="no-results">
                        <p><i class="fas fa-exclamation-circle me-2"></i>Aucun résultat d'examen n'a été trouvé pour le moment.</p>
                    </div>
                <?php endif; ?>
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
