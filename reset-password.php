<?php
session_start();
require_once 'db.php';  // Assurez-vous d'avoir votre connexion à la base de données

// Vérifier si le token et l'email sont présents dans l'URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
    
    // Stocker temporairement ces informations dans la session
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $token;
} elseif (isset($_SESSION['reset_token']) && isset($_SESSION['reset_email'])) {
    // Utiliser les valeurs de la session si disponibles
    $token = $_SESSION['reset_token'];
    $email = $_SESSION['reset_email'];
} else {
    // Rediriger s'il n'y a pas de token ou d'email
    header('Location: forgot-password.php');
    exit();
}

// Traitement du formulaire de réinitialisation
$message = '';
$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifier si les champs du formulaire sont soumis
    if (isset($_POST['password']) && isset($_POST['confirm_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Valider la correspondance des mots de passe
        if ($password != $confirm_password) {
            $error = "Les mots de passe ne correspondent pas.";
        } elseif (strlen($password) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères.";
        } else {
            // Vérifier la validité du token dans la base de données
            $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Token valide, mettre à jour le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Mise à jour du mot de passe dans la table des utilisateurs
                $update_sql = "UPDATE users SET password = ? WHERE email = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $hashed_password, $email);
                
                if ($update_stmt->execute()) {
                    // Supprimer le token utilisé
                    $delete_sql = "DELETE FROM password_resets WHERE email = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("s", $email);
                    $delete_stmt->execute();
                    
                    // Nettoyer la session
                    unset($_SESSION['reset_token']);
                    unset($_SESSION['reset_email']);
                    
                    $success = true;
                    $message = "Votre mot de passe a été réinitialisé avec succès.";
                } else {
                    $error = "Erreur lors de la mise à jour du mot de passe. Veuillez réessayer.";
                }
            } else {
                $error = "Le lien de réinitialisation est invalide ou a expiré.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe | Plateforme Examens</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4db8b8;
            --primary-dark: #3a9a9a;
            --secondary: #34495e;
            --accent: #1abc9c;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            overflow-x: hidden;
        }
        
        .reset-container {
            display: flex;
            min-height: 100vh;
        }
        
        .graphic-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .graphic-content {
            position: relative;
            z-index: 2;
            padding: 2rem;
            color: white;
            text-align: center;
        }
        
        .form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .reset-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
            margin-top: 1rem;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        h2 {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 1.5rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }
        
        .btn-reset {
            background: var(--primary);
            border: none;
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-reset:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(26, 188, 156, 0.3);
        }
        
        .btn-login {
            color: var(--primary);
            font-weight: 500;
        }
        
        .btn-login:hover {
            color: var(--primary-dark);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary);
        }
        
        /* Éléments graphiques */
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .circle-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
        }
        
        .circle-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            right: -50px;
        }
        
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" fill="%23ffffff" opacity=".25"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" fill="%23ffffff" opacity=".5"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23ffffff"/></svg>');
            background-size: cover;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .reset-container {
                flex-direction: column;
            }
            
            .graphic-side {
                padding: 3rem 1rem;
                min-height: 300px;
            }
            
            .form-side {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>

    <div class="reset-container">
        <!-- Partie graphique -->
        <div class="graphic-side">
            <!-- Éléments décoratifs -->
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="wave"></div>
            
            <div class="graphic-content">
                <img src="photoOfppt/OFPPT_Logo.png" class="img-fluid" alt="image">
                <h1 class="text-white mb-3">Réinitialisation de mot de passe</h1>
                <p class="lead text-white-50 mb-4">Créez un nouveau mot de passe sécurisé</p>
            </div>
        </div>
        
        <!-- Partie formulaire -->
        <div class="form-side">
            <div class="reset-card">
                <div class="logo">
                    <div class="logo-text">Exam<span>Pro</span></div>
                </div>
                
                <h2>Nouveau mot de passe</h2>
                
                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-sm btn-outline-success">Se connecter maintenant</a>
                    </div>
                </div>
                <?php else: ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . urlencode($token) . '&email=' . urlencode($email); ?>">
                    <div class="form-floating mb-4 position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Nouveau mot de passe" required>
                        <label for="password">Nouveau mot de passe</label>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                    
                    <div class="form-floating mb-4 position-relative">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                    </div>
                    
                    <div class="mb-4">
                        <ul class="text-muted small">
                            <li>Le mot de passe doit contenir au moins 8 caractères</li>
                            <li>Utilisez une combinaison de lettres, chiffres et caractères spéciaux</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-reset">
                            <i class="fas fa-lock me-2"></i> Réinitialiser mon mot de passe
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn-login text-decoration-none">
                        <i class="fas fa-arrow-left me-2"></i> Retour à la connexion
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
