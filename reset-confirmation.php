<?php

session_start();
if (!isset($_SESSION['reset_token'])) {
    header('Location: forgot-password.php');
    exit();
}

$email = $_SESSION['reset_email'];
$token = $_SESSION['reset_token'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email envoyé | Plateforme Examens</title>
    
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
        
        .confirmation-container {
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
        
        .confirmation-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .confirmation-card::before {
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
        
        .email-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }
        
        .btn-back {
            background: var(--primary);
            border: none;
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            max-width: 300px;
            transition: all 0.3s;
            color: white;
            margin-top: 2rem;
        }
        
        .btn-back:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(26, 188, 156, 0.3);
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
        
        /* Responsive */
        @media (max-width: 992px) {
            .confirmation-container {
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

    <div class="confirmation-container">
        <!-- Partie graphique -->
        <div class="graphic-side">
            <!-- Éléments décoratifs -->
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="wave"></div>
            
            <div class="graphic-content">
                <img src="photoOfppt/OFPPT_Logo.png" class="img-fluid" alt="image">
                <h1 class="text-white mb-3">Réinitialisation en cours</h1>
                <p class="lead text-white-50 mb-4">Vérifiez votre boîte email</p>
            </div>
        </div>
        
        <!-- Partie confirmation -->
        <div class="form-side">
            <div class="confirmation-card">
                <div class="logo">
                    <div class="logo-text">Exam<span>Pro</span></div>
                </div>
                
                <div class="email-icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                
                <h2>Email envoyé avec succès</h2>
                
                <div class="mb-4">
                    <p>Nous avons envoyé un lien de réinitialisation à :</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($email); ?></p>
                    <p>Veuillez vérifier votre boîte de réception et cliquer sur le lien pour réinitialiser votre mot de passe.</p>
                </div>
                
<!-- Code HTML inchangé jusqu'à la section des boutons -->

<div class="mb-3">
    <p class="text-muted small">Si vous ne voyez pas l'email, vérifiez votre dossier de spam.</p>
</div>

<!-- Ajouter un nouveau bouton pour la réinitialisation directe -->
<a href="reset-password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>" class="btn btn-primary mb-3 w-100" style="background: var(--accent);">
    <i class="fas fa-key me-2"></i> Réinitialiser mon mot de passe
</a>

<a href="login.php" class="btn btn-back">
    <i class="fas fa-arrow-left me-2"></i> Retour à la connexion
</a>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Nettoyer la session après affichage
    unset($_SESSION['reset_token']);
    unset($_SESSION['reset_email']);
    ?>
</body>
</html>
