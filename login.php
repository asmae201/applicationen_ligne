<?php
session_start();
include('db.php');  // Connexion à la base de données

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Requête pour vérifier si l'utilisateur existe dans la base de données
    $sql = "SELECT * FROM utilisateur WHERE email='$email' AND motDePasse='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Si l'utilisateur est trouvé
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role']; // Enregistrer le rôle de l'utilisateur dans la session

        // Rediriger selon le rôle
        if ($user['role'] == 'Enseignant') {
            header('Location: dashboard.php'); // Tableau de bord pour l'enseignant
        } elseif ($user['role'] == 'Administrateur') {
            header('Location: dashboard_admin.php'); // Tableau de bord pour l'administrateur
        } elseif ($user['role'] == 'Etudiant') {
            header('Location: dashboard_student.php'); // Tableau de bord pour l'étudiant
        } else {
            // Pour d'autres rôles, on peut rediriger ailleurs ou afficher un message
            echo "Bienvenue !";
        }
    } else {
        // Si les identifiants sont incorrects
        $error = "Identifiants invalides.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Plateforme Examens</title>
    
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
        
        .login-container {
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
        
        .login-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
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
        
        .logo img {
            height: 50px;
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
            text-align: center;
        }
        
        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding-left: 45px;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }
        
        .input-group-text {
            position: absolute;
            z-index: 5;
            height: 50px;
            background: transparent;
            border: none;
            color: var(--primary);
        }
        
        .btn-login {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(26, 188, 156, 0.3);
        }
        
        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        .links a {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .links a:hover {
            color: var(--primary);
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
        
        /* Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .login-container {
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

    <div class="login-container">
        <!-- Partie graphique -->
        <div class="graphic-side">
            <!-- Éléments décoratifs -->
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="wave"></div>
            
            <div class="graphic-content">
            <img src="photoOfppt/OFPPT_Logo.png" class="img-fluid" alt="image">
            <h1 class="text-white mb-3">Bienvenue sur ExamPro</h1>
                <p class="lead text-white-50 mb-4">La plateforme d'examens en ligne la plus complète</p>
                
                <div class="d-flex justify-content-center gap-4">
                    <div class="text-center">
                        <div class="display-6 text-white mb-2"></div>
                        <div class="text-white-50"></div>
                    </div>
                    <div class="text-center">
                        <div class="display-6 text-white mb-2"></div>
                        <div class="text-white-50"></div>
                    </div>
                    <div class="text-center">
                        <div class="display-6 text-white mb-2"></div>
                        <div class="text-white-50"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Partie formulaire -->
        <div class="form-side">
            <div class="login-card">
                <div class="logo">
                    <div class="logo-text">Exam<span>Pro</span></div>
                </div>
                
                <h2>Connectez-vous</h2>
                
                <!-- Affichage du message d'erreur -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="position-relative mb-3">
                        <i class="fas fa-envelope input-group-text"></i>
                        <input type="email" name="email" class="form-control ps-5" placeholder="Adresse email" required>
                    </div>
                    
                    <div class="position-relative mb-4">
                        <i class="fas fa-lock input-group-text"></i>
                        <input type="password" name="password" class="form-control ps-5" placeholder="Mot de passe" required>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none">Mot de passe oublié ?</a>
                    </div>
                    
                    <button type="submit" name="submit" class="btn btn-primary btn-login mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i> Connexion
                    </button>
                    
                    <div class="text-center pt-3">
                        <p class="mb-0">Nouveau sur ExamPro ? <a href="register.php" class="text-decoration-none">Créer un compte</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
