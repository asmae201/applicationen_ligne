<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Formateur | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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

        /* Sidebar modernisée */
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

        /* Contenu principal */
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
        
        .welcome-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .welcome-message h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        [data-bs-theme="dark"] .welcome-message h2 {
            color: var(--primary);
        }
        
        .welcome-message p {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 700px;
            margin: 0 auto 30px;
        }
        
        [data-bs-theme="dark"] .welcome-message p {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .welcome-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        [data-bs-theme="dark"] .welcome-image {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            filter: brightness(0.9);
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
    <!-- Bouton menu mobile -->
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
                <a href="dashboard.php" class="sidebar-link active">
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
                <a href="exam_form.php" class="sidebar-link">
                    <i class="fas fa-pencil-alt"></i>
                    <span>Créer un examen</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="choisir_examen.php" class="sidebar-link">
                    <i class="fas fa-edit"></i>
                    <span>Corriger un examen</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="exam_planned.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Examens programmés</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="list_notes.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Consulter résultats</span>
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

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Tableau de bord - Enseignant</h1>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Bienvenue, Formateur</h2>
            </div>
            <div class="card-body">
                <div class="welcome-message">
                    <h2>Gérez vos examens en toute simplicité</h2>
                    <p>
                        Vous pouvez créer de nouveaux examens, corriger les copies, consulter les résultats 
                        et suivre la progression de vos étudiants directement depuis cette plateforme.
                    </p>
                    <img src="photoOfppt/ofpptformateur.png" alt="Formateur" class="welcome-image">
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
