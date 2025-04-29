<?php
session_start();
include('db.php');

if (isset($_POST['register'])) {
    $nom = $_POST['nom'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $groupe = isset($_POST['groupe']) ? $_POST['groupe'] : null;

    // Vérifier si l'email existe déjà
    $check_sql = "SELECT * FROM utilisateur WHERE email='$email'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $error = "Cet email est déjà utilisé.";
    } else {
        // Générer un ID unique
        $id = uniqid('user_');
        
        // Insérer le nouvel utilisateur
        $insert_sql = "INSERT INTO utilisateur (id, nom, email, motDePasse, role, groupe) 
                      VALUES ('$id', '$nom', '$email', '$password', '$role', '$groupe')";
        
        if ($conn->query($insert_sql) === TRUE) {
            $_SESSION['user_id'] = $id;
            $_SESSION['role'] = $role;
            
            // Redirection selon le rôle
            switch($role) {
                case 'Enseignant':
                    header('Location: dashboard.php');
                    break;
                case 'Administrateur':
                    header('Location: dashboard_admin.php');
                    break;
                case 'Etudiant':
                    header('Location: dashboard_student.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit();
        } else {
            $error = "Erreur lors de la création du compte: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Plateforme Examens</title>
    
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
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .register-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
        }
        
        .graphic-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            padding: 60px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .form-side {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .brand-logo {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 40px;
        }
        
        .brand-logo span {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .welcome-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 20px;
        }
        
        .benefit-list {
            list-style: none;
            padding: 0;
            margin-bottom: 40px;
        }
        
        .benefit-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .benefit-list i {
            margin-right: 10px;
            font-size: 1.2rem;
            color: white;
        }
        
        .form-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding-left: 45px;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }
        
        .input-icon {
            position: absolute;
            z-index: 5;
            height: 50px;
            background: transparent;
            border: none;
            color: var(--primary);
            display: flex;
            align-items: center;
            padding-left: 15px;
        }
        
        .btn-register {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            color: white;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            background: linear-gradient(90deg, var(--primary-dark), var(--accent));
            color: white;
        }
        
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .role-option:hover {
            border-color: var(--primary);
        }
        
        .role-option.active {
            border-color: var(--primary);
            background-color: rgba(77, 184, 184, 0.1);
        }
        
        .role-option input {
            display: none;
        }
        
        .alert {
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            border-radius: 10px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* Éléments graphiques simplifiés */
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
        
        /* Responsive */
        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .graphic-side, .form-side {
                padding: 40px;
            }
        }
        
        @media (max-width: 576px) {
            .graphic-side, .form-side {
                padding: 30px;
            }
            
            .role-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="register-container">
        <!-- Partie graphique simplifiée -->
        <div class="graphic-side">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Study</span>Space
            </div>
            
            <h2 class="welcome-text">Bienvenue sur notre plateforme</h2>
            
            <ul class="benefit-list">
                <li><i class="fas fa-check-circle"></i> Accès à tous vos examens</li>
                <li><i class="fas fa-check-circle"></i> Résultats en temps réel</li>
                <li><i class="fas fa-check-circle"></i> Interface intuitive</li>
                <li><i class="fas fa-check-circle"></i> Support 24/24</li>
            </ul>
        </div>
        
        <!-- Partie formulaire -->
        <div class="form-side">
            <div class="form-card">
                <h3 class="form-title">Créer un compte</h3>
                
                <!-- Affichage du message d'erreur -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="register.php">
                    <div class="mb-3 position-relative">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="nom" class="form-control ps-5" placeholder="Nom complet" required>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" class="form-control ps-5" placeholder="Adresse email" required>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" class="form-control ps-5" placeholder="Mot de passe" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type de compte</label>
                        <div class="role-selector">
                            <label class="role-option">
                                <input type="radio" name="role" value="Etudiant" checked> Étudiant
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="Enseignant"> Enseignant
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="groupe-field">
                        <label class="form-label">Groupe</label>
                        <select name="groupe" class="form-control">
                            <option value="Group 1">Group 1</option>
                            <option value="Group 2">Group 2</option>
                            <option value="Group 3">Group 3</option>
                            <option value="Group 4">Group 4</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i> S'inscrire
                    </button>
                </form>
                
                <div class="login-link">
                    Déjà un compte ? <a href="login.php">Se connecter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Gestion de l'affichage du champ groupe et du style des options
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('input[name="role"]');
            const groupeField = document.getElementById('groupe-field');
            
            roleOptions.forEach(option => {
                option.addEventListener('change', function() {
                    // Afficher/masquer le champ groupe
                    groupeField.style.display = this.value === 'Etudiant' ? 'block' : 'none';
                    
                    // Mettre à jour le style des options
                    document.querySelectorAll('.role-option').forEach(el => {
                        el.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                });
            });
            
            // Activer par défaut l'option Étudiant
            document.querySelector('input[value="Etudiant"]').dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>
