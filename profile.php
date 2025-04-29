<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Traiter la mise à jour du profil
    if (isset($_POST['update_profile'])) {
        $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Vérifier si l'email existe déjà pour un autre utilisateur
        $stmt = $pdo->prepare("SELECT id FROM Utilisateur WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error_message = "Cet email est déjà utilisé par un autre compte.";
        } 
        // Vérifier le mot de passe actuel
        else if ($current_password !== $user['motDePasse']) { // En production, utilisez password_verify()
            $error_message = "Le mot de passe actuel est incorrect.";
        }
        // Vérifier si les nouveaux mots de passe correspondent
        else if ($new_password && $new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        }
        else {
            // Préparer la requête de mise à jour
            if ($new_password) {
                $stmt = $pdo->prepare("
                    UPDATE Utilisateur 
                    SET nom = ?, email = ?, motDePasse = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $email, $new_password, $user_id]); // En production, utilisez password_hash()
            } else {
                $stmt = $pdo->prepare("
                    UPDATE Utilisateur 
                    SET nom = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $email, $user_id]);
            }

            $_SESSION['nom'] = $nom;
            $success_message = "Profil mis à jour avec succès.";
            
            // Recharger les informations de l'utilisateur
            $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }

} catch(PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors de la mise à jour du profil.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | ExamPro</title>
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
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        [data-bs-theme="dark"] {
            --bs-body-bg: var(--bg-dark);
            --bs-body-color: var(--text-light);
        }
        
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
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
        }
        
        .btn-logout {
            background-color: var(--primary);
            color: white;
            transition: var(--transition);
        }
        
        .btn-logout:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Profile Container */
        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            transition: var(--transition);
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border-top: 4px solid var(--primary);
            transition: var(--transition);
        }
        
        [data-bs-theme="dark"] .profile-card {
            background-color: var(--card-dark);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05) rotate(5deg);
        }
        
        .nav-pills .nav-link {
            color: var(--secondary);
            font-weight: 500;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            box-shadow: 0 4px 15px rgba(78, 184, 184, 0.3);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
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
            transition: var(--transition);
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
        }
        
        .theme-toggle:hover {
            transform: rotate(30deg) scale(1.1);
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-card {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</head>
<body data-bs-theme="light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
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
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user me-1"></i>
                            Mon profil
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo isset($_SESSION['nom']) ? htmlspecialchars($_SESSION['nom']) : 'Utilisateur'; ?>
                    </span>
                    <a href="logout.php" class="btn btn-logout btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container profile-container">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['nom'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['nom']); ?></h3>
                <p class="text-muted">
                    <i class="fas fa-envelope me-1"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>

            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-profile">
                        <i class="fas fa-user-edit me-1"></i>
                        Information du profil
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="pills-profile">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label for="nom" class="form-label">Nom complet</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="new_password" class="form-label">Nouveau mot de passe (optionnel)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                Mettre à jour le profil
                            </button>
                        </div>
                    </form>
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

        // Validation des formulaires
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
