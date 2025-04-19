<?php
session_start();
require 'db.php';

// Vérifier si admin
if ($_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $mdp = $_POST['mdp'];
    $role = $_POST['role'];
    $groupe = ($role === 'Etudiant' || $role === 'Enseignant') ? $_POST['groupe'] : null;

    try {
        // Validation
        if (empty($nom) || empty($email) || empty($mdp)) {
            throw new Exception("Tous les champs sont obligatoires");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide");
        }

        // Vérifier email unique
        $stmt = $pdo->prepare("SELECT id FROM Utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email déjà utilisé");
        }

        // Hash mot de passe
        $mdp_hash = password_hash($mdp, PASSWORD_DEFAULT);

        // Créer ID unique
        $id_user = 'user_' . uniqid();

        // Ajouter utilisateur
        $stmt = $pdo->prepare(
            "INSERT INTO Utilisateur (id, nom, email, motDePasse, role, groupe) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$id_user, $nom, $email, $mdp_hash, $role, $groupe]);

        // Si enseignant, ajouter à ProfesseurGroupe
        if ($role === 'Enseignant' && $groupe) {
            $stmt = $pdo->prepare(
                "INSERT INTO ProfesseurGroupe (professeur_id, group_name) 
                VALUES (?, ?)"
            );
            $stmt->execute([$id_user, $groupe]);
        }

        $success = "Utilisateur ajouté avec succès!";
        $_POST = []; // Vider le formulaire

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer la liste des groupes
$groupes = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Utilisateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #2575fc;
            border: none;
        }
        .btn-primary:hover {
            background-color: #1a5dc8;
        }
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h3><i class="fas fa-user-plus me-2"></i>Ajouter un Utilisateur</h3>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nom Complet</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3 password-container">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" name="mdp" id="password" class="form-control" required>
                                <span class="toggle-password" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Rôle</label>
                                <select name="role" id="roleSelect" class="form-select" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="Administrateur" <?= ($_POST['role'] ?? '') === 'Administrateur' ? 'selected' : '' ?>>Administrateur</option>
                                    <option value="Enseignant" <?= ($_POST['role'] ?? '') === 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                                    <option value="Etudiant" <?= ($_POST['role'] ?? '') === 'Etudiant' ? 'selected' : '' ?>>Étudiant</option>
                                </select>
                            </div>

                            <div class="mb-3" id="groupeField" style="display: none;">
                                <label class="form-label">Groupe</label>
                                <select name="groupe" class="form-select">
                                    <option value="">Sélectionner un groupe...</option>
                                    <?php foreach ($groupes as $g): ?>
                                        <option value="<?= htmlspecialchars($g['group_name']) ?>" <?= ($_POST['groupe'] ?? '') === $g['group_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['group_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i> Enregistrer
                                </button>
                                <a href="dashboard_admin.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i> Retour
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Afficher/masquer champ groupe selon rôle
        document.getElementById('roleSelect').addEventListener('change', function() {
            const role = this.value;
            const groupeField = document.getElementById('groupeField');
            
            if (role === 'Etudiant' || role === 'Enseignant') {
                groupeField.style.display = 'block';
            } else {
                groupeField.style.display = 'none';
            }
        });

        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.querySelector('.toggle-password i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Initialiser l'affichage du champ groupe si on revient sur la page
        document.addEventListener('DOMContentLoaded', function() {
            const role = document.getElementById('roleSelect').value;
            if (role === 'Etudiant' || role === 'Enseignant') {
                document.getElementById('groupeField').style.display = 'block';
            }
        });
    </script>
</body>
</html>
