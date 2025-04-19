<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit;
}

include('db.php');

// Function to get users with optional filtering and sorting
function getUsers($conn, $filter = '', $sort = 'nom', $order = 'ASC') {
    $sql = "SELECT * FROM utilisateur WHERE 1=1";
    
    if (!empty($filter)) {
        $filter = $conn->real_escape_string($filter);
        $sql .= " AND (nom LIKE '%$filter%' OR email LIKE '%$filter%' OR role LIKE '%$filter%')";
    }
    
    $sql .= " ORDER BY $sort $order";
    return $conn->query($sql);
}

// Get filter and sort parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nom';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Get user statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM utilisateur")->fetch_assoc()['count'],
    'admins' => $conn->query("SELECT COUNT(*) as count FROM utilisateur WHERE role = 'Administrateur'")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM utilisateur WHERE role = 'Enseignant'")->fetch_assoc()['count'],
    'students' => $conn->query("SELECT COUNT(*) as count FROM utilisateur WHERE role = 'Etudiant'")->fetch_assoc()['count']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4db8b8;
            --primary-dark: #3a9a9a;
            --secondary: #34495e;
            --accent: #1abc9c;
            --highlight: #f0a500;
            --admin: #dc3545;
            --teacher: #198754;
            --student: #0d6efd;
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
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
        }

        /* Navbar */
        .navbar {
            background-color: var(--secondary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        [data-bs-theme="dark"] .navbar {
            background-color: var(--sidebar-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        /* Cards */
        .stats-card {
            transition: transform 0.3s;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        [data-bs-theme="dark"] .stats-card {
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        [data-bs-theme="dark"] .stats-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }

        /* Table */
        .table th {
            background-color: rgba(0,0,0,0.05);
        }
        
        [data-bs-theme="dark"] .table th {
            background-color: rgba(255,255,255,0.05);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        /* Badges */
        .role-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            color: black;
        }
        
        .role-admin { background-color: var(--admin); }
        .role-teacher { background-color: var(--teacher); }
        .role-student { background-color: var(--student); }

        /* Main Content */
        .container {
            margin-top: 30px;
            transition: all 0.3s;
        }

        /* Theme Toggle */
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

        /* Mobile Menu */
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

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
    
</head>
<body data-bs-theme="light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-graduation-cap me-2"></i>EXAM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_admin.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user-circle me-1"></i>Mon profil
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <button class="theme-toggle me-3" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <span class="text-white me-3">
                        <i class="fas fa-user me-1"></i>
                        <?php echo isset($_SESSION['nom']) ? htmlspecialchars($_SESSION['nom']) : 'Utilisateur'; ?>
                    </span>
                    <a href="logout.php" class="btn btn-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-white" style="background-color: var(--primary)">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users me-2"></i>Total Utilisateurs</h5>
                        <h2><?php echo $stats['total']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white" style="background-color: var(--admin)">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-shield me-2"></i>Administrateurs</h5>
                        <h2><?php echo $stats['admins']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white" style="background-color: var(--teacher)">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chalkboard-teacher me-2"></i>Enseignants</h5>
                        <h2><?php echo $stats['teachers']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white" style="background-color: var(--student)">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-graduate me-2"></i>Étudiants</h5>
                        <h2><?php echo $stats['students']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Users List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(90deg, var(--secondary), var(--primary-dark)); color: white;">
                <h4 class="mb-0"><i class="fas fa-users me-2"></i>Liste des utilisateurs</h4>
                <a href="add_user.php" class="btn btn-light">
                    <i class="fas fa-plus me-1"></i>Ajouter un utilisateur
                </a>
            </div>
            <div class="card-body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form class="d-flex" method="GET">
                            <input type="text" name="filter" class="form-control me-2" 
                                   placeholder="Rechercher..." value="<?php echo htmlspecialchars($filter); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort=id&order=<?php echo $sort === 'id' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                                       class="text-decoration-none">
                                        ID
                                        <i class="fas fa-sort sort-icon"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=nom&order=<?php echo $sort === 'nom' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>"
                                       class="text-decoration-none">
                                        Nom
                                        <i class="fas fa-sort sort-icon"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=email&order=<?php echo $sort === 'email' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>"
                                       class="text-decoration-none">
                                        Email
                                        <i class="fas fa-sort sort-icon"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=role&order=<?php echo $sort === 'role' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>"
                                       class="text-decoration-none">
                                        Rôle
                                        <i class="fas fa-sort sort-icon"></i>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = getUsers($conn, $filter, $sort, $order);
                            while ($user = $users->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <?php echo htmlspecialchars($user['id']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="edit_user.php?id=<?php echo urlencode($user['id']); ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </a>
                                        <a href="delete_user.php?id=<?php echo urlencode($user['id']); ?>&role=<?php echo urlencode($user['role']); ?>" 
   class="btn btn-danger btn-sm"
   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
    <i class="fas fa-trash-alt me-1"></i>Supprimer
</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
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
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.getElementsByClassName('alert');
                for(var i = 0; i < alerts.length; i++) {
                    var alert = alerts[i];
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        });
    </script>
</body>
</html>
