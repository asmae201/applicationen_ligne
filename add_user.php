<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit;
}

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $group = isset($_POST['group']) ? $_POST['group'] : '';

    try {
        // Validation des champs
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            throw new Exception("Tous les champs sont obligatoires.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide.");
        }

        if (($role == 'Etudiant' || $role == 'Enseignant') && empty($group)) {
            throw new Exception("Le groupe est obligatoire pour ce rôle.");
        }

        // Vérification de l'unicité de l'email
        $stmt = $conn->prepare("SELECT id FROM Utilisateur WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Cette adresse email est déjà utilisée.");
        }

        // Insertion de l'utilisateur
        $user_id = uniqid('user_');
        $groupe_val = ($role == 'Administrateur') ? NULL : $group;
        
        $stmt = $conn->prepare("INSERT INTO Utilisateur (id, nom, email, motDePasse, role, groupe) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $user_id, $name, $email, $password, $role, $groupe_val);

        if ($stmt->execute()) {
            // Si enseignant, ajouter l'association dans ProfesseurGroupe
            if ($role == 'Enseignant' && !empty($group)) {
                $sql_get_group = "SELECT group_name FROM groups WHERE id = ?";
                $stmt_get_group = $conn->prepare($sql_get_group);
                $stmt_get_group->bind_param("i", $group);
                $stmt_get_group->execute();
                $result = $stmt_get_group->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $group_name = $row['group_name'];
                    
                    $sql_prof_group = "INSERT INTO ProfesseurGroupe (professeur_id, group_name) VALUES (?, ?)";
                    $stmt_prof_group = $conn->prepare($sql_prof_group);
                    $stmt_prof_group->bind_param("ss", $user_id, $group_name);
                    $stmt_prof_group->execute();
                }
            }
            
            $_SESSION['success_message'] = "Utilisateur ajouté avec succès.";
            header("Location: dashboard_admin.php");
            exit;
        } else {
            throw new Exception("Erreur lors de l'ajout de l'utilisateur.");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur</title>
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
            --card-dark: #0f3460;
        }
        
        [data-bs-theme="dark"] {
            --bs-body-bg: var(--bg-dark);
            --bs-body-color: var(--text-light);
            --bs-card-bg: var(--card-dark);
            --bs-border-color: #2a3b4d;
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

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            overflow: hidden;
        }
        
        [data-bs-theme="dark"] .card {
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .card-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }

        .form-control, .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .form-control, 
        [data-bs-theme="dark"] .form-select {
            background-color: #2a3b4d;
            border-color: #3a4b5d;
            color: var(--text-light);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
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
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
        }
        
        .theme-toggle:hover {
            transform: rotate(30deg);
        }

        .invalid-feedback {
            font-size: 0.85rem;
        }

        .was-validated .form-control:invalid, 
        .was-validated .form-select:invalid {
            border-color: #dc3545;
        }
        
        [data-bs-theme="dark"] .was-validated .form-control:invalid,
        [data-bs-theme="dark"] .was-validated .form-select:invalid {
            border-color: #dc3545;
            background-color: #2a3b4d;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        [data-bs-theme="dark"] .password-toggle {
            color: #adb5bd;
        }

        .input-group-text {
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .input-group-text {
            background-color: #3a4b5d;
            border-color: #4a5b6d;
            color: var(--text-light);
        }
    </style>
</head>
<body data-bs-theme="light">
    <!-- Bouton de bascule de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h2 class="mb-0"><i class="fas fa-user-plus me-2"></i>Ajouter un utilisateur</h2>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_user.php" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">Nom complet</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez entrer un nom valide.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="email" class="form-label fw-bold">Adresse email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez entrer une adresse email valide.
                                </div>
                            </div>

                            <div class="mb-4 position-relative">
                                <label for="password" class="form-label fw-bold">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <span class="password-toggle" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez entrer un mot de passe.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="role" class="form-label fw-bold">Rôle</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <select class="form-select" id="role" name="role" required onchange="toggleGroupField()">
                                        <option value="">Sélectionnez un rôle</option>
                                        <option value="Administrateur">Administrateur</option>
                                        <option value="Enseignant">Enseignant</option>
                                        <option value="Etudiant">Étudiant</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un rôle.
                                </div>
                            </div>

                            <div class="mb-4" id="groupField" style="display: none;">
                                <label for="group" class="form-label fw-bold">Groupe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-users"></i></span>
                                    <select class="form-select" id="group" name="group">
                                        <option value="">Sélectionnez un groupe</option>
                                        <?php
                                        $sql = "SELECT id, group_name FROM groups";
                                        $result = $conn->query($sql);
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<option value='{$row['id']}'>{$row['group_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un groupe.
                                </div>
                            </div>

                            <div class="d-grid gap-3 mt-4">
                                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Ajouter l'utilisateur
                                </button>
                                <a href="dashboard_admin.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
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

        // Validation du formulaire
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

        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Afficher/masquer le champ groupe selon le rôle
        function toggleGroupField() {
            const role = document.getElementById("role").value;
            const groupField = document.getElementById("groupField");
            const groupSelect = document.getElementById("group");
            
            if (role === "Etudiant" || role === "Enseignant") {
                groupField.style.display = "block";
                groupSelect.required = true;
            } else {
                groupField.style.display = "none";
                groupSelect.required = false;
            }
        }

        // Exécuter au chargement pour initialiser l'état
        document.addEventListener('DOMContentLoaded', function() {
            toggleGroupField();
        });
    </script>
</body>
</html>
