<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit;
}

include('db.php');

// Initialisation des variables
$user = null;
$message = '';
$messageType = '';
$groupes = [];
$groupes_prof = [];

// Récupération de la liste des groupes
try {
    $stmt = $conn->prepare("SELECT id, group_name FROM groups ORDER BY group_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $groupes[] = $row;
    }
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des groupes: " . $e->getMessage();
    $messageType = 'danger';
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

// Récupération de l'utilisateur
if (isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM Utilisateur WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("Utilisateur non trouvé.");
        }
        
        // Si l'utilisateur est un enseignant, récupérer ses groupes
        if ($user['role'] === 'Enseignant') {
            $stmt = $conn->prepare("SELECT group_name FROM ProfesseurGroupe WHERE professeur_id = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $groupes_prof = [];
            while ($row = $result->fetch_assoc()) {
                $groupes_prof[] = $row['group_name'];
            }
        }
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = 'danger';
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// Traitement du formulaire
if (isset($_POST['edit_user'])) {
    try {
        $id = $_GET['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $groupes_selection = isset($_POST['groupes']) ? (is_array($_POST['groupes']) ? $_POST['groupes'] : [$_POST['groupes']]) : [];

        // Validation des champs
        if (empty($name) || empty($email) || empty($role)) {
            throw new Exception("Le nom, l'email et le rôle sont obligatoires.");
        }

        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide.");
        }

        // Vérification des rôles valides
        $valid_roles = ['Administrateur', 'Enseignant', 'Etudiant'];
        if (!in_array($role, $valid_roles)) {
            throw new Exception("Rôle invalide sélectionné.");
        }
        
        // Validation des groupes selon le rôle
        if (($role === 'Etudiant' || $role === 'Enseignant') && empty($groupes_selection)) {
            throw new Exception("Veuillez sélectionner au moins un groupe.");
        }

        // Vérification de l'unicité de l'email
        $stmt = $conn->prepare("SELECT id FROM Utilisateur WHERE email = ? AND id != ?");
        $stmt->bind_param("ss", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Cette adresse email est déjà utilisée par un autre utilisateur.");
        }

        // Début de la transaction
        $conn->begin_transaction();

        // Préparation du mot de passe
        $password_update = "";
        $password_params = "";
        
        if (!empty($password)) {
            // Générer un hash pour le nouveau mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $password_update = ", motDePasse = ?";
            $password_params = "s";
        }

        // Déterminer le groupe principal pour les étudiants
        $groupe_principal = null;
        if ($role === 'Etudiant' && !empty($groupes_selection)) {
            $groupe_principal = $groupes_selection[0]; // Premier groupe sélectionné
        }

        // Mise à jour de l'utilisateur (avec ou sans mot de passe)
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE Utilisateur SET nom = ?, email = ?, motDePasse = ?, role = ?, groupe = ? WHERE id = ?");
            $stmt->bind_param("ssssss", $name, $email, $password_hash, $role, $groupe_principal, $id);
        } else {
            $stmt = $conn->prepare("UPDATE Utilisateur SET nom = ?, email = ?, role = ?, groupe = ? WHERE id = ?");
            $stmt->bind_param("sssss", $name, $email, $role, $groupe_principal, $id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de la mise à jour de l'utilisateur: " . $conn->error);
        }

        // Si c'est un enseignant, gérer les groupes associés
        if ($role === 'Enseignant') {
            // Supprimer les associations précédentes
            $stmt = $conn->prepare("DELETE FROM ProfesseurGroupe WHERE professeur_id = ?");
            $stmt->bind_param("s", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la suppression des anciennes associations de groupes: " . $conn->error);
            }
            
            // Ajouter les nouvelles associations
            if (!empty($groupes_selection)) {
                $stmt = $conn->prepare("INSERT INTO ProfesseurGroupe (professeur_id, group_name) VALUES (?, ?)");
                
                foreach ($groupes_selection as $groupe) {
                    $stmt->bind_param("ss", $id, $groupe);
                    if (!$stmt->execute()) {
                        throw new Exception("Erreur lors de l'ajout des associations de groupes: " . $conn->error);
                    }
                }
            }
        }

        // Valider la transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Utilisateur modifié avec succès.";
        header("Location: dashboard_admin.php");
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $message = "Erreur: " . $e->getMessage();
        $messageType = 'danger';
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'utilisateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: all 0.3s ease;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(120deg, #f6f9ff 0%, #ecf4ff 100%);
        }
        
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            background-image: linear-gradient(120deg, #1a1a2e 0%, #0f172a 100%);
        }

        .container {
            padding: 2rem 1rem;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.3s;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        [data-bs-theme="dark"] .card {
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem 1.5rem;
            border-bottom: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -75px;
            right: -75px;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -40px;
            left: 30px;
        }

        .card-header h2 {
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 2;
        }

        .card-body {
            padding: 2.5rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.75rem;
            color: var(--secondary);
            letter-spacing: 0.3px;
        }
        
        [data-bs-theme="dark"] .form-label {
            color: var(--text-light);
        }

        .form-control, .form-select, .select2-container--default .select2-selection--multiple {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            font-size: 0.95rem;
            background-color: #fff;
        }
        
        [data-bs-theme="dark"] .form-control, 
        [data-bs-theme="dark"] .form-select,
        [data-bs-theme="dark"] .select2-container--default .select2-selection--multiple {
            background-color: #2c3e50;
            border-color: #40556c;
            color: var(--text-light);
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            color: var(--secondary);
        }
        
        [data-bs-theme="dark"] .input-group-text {
            background-color: #40556c;
            border-color: #40556c;
            color: var(--text-light);
        }
        
        .input-group .form-control, 
        .input-group .form-select {
            border-radius: 0 10px 10px 0;
        }

        .form-control:focus, 
        .form-select:focus,
        .select2-container--focus .select2-selection--multiple {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }

        .btn {
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 10px;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            box-shadow: 0 4px 10px rgba(77, 184, 184, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(77, 184, 184, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(77, 184, 184, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--secondary);
            border: 1px solid #dde2e6;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        [data-bs-theme="dark"] .btn-secondary {
            background: #40556c;
            color: var(--text-light);
            border: 1px solid #526a82;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        
        [data-bs-theme="dark"] .btn-secondary:hover {
            background: #526a82;
            color: var(--text-light);
        }
        
        .btn-secondary:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
        }

        .theme-toggle {
            background: var(--secondary);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
        }
        
        .theme-toggle:hover {
            transform: rotate(30deg) scale(1.1);
        }
        
        .theme-toggle i {
            font-size: 1.2rem;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        [data-bs-theme="dark"] .alert {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        
        [data-bs-theme="dark"] .alert-danger {
            background-color: #42181c;
            color: #f8d7da;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        [data-bs-theme="dark"] .alert-success {
            background-color: #0f3824;
            color: #d1e7dd;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        [data-bs-theme="dark"] .alert-warning {
            background-color: #332702;
            color: #fff3cd;
        }

        .invalid-feedback {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            color: #dc3545;
        }
        
        [data-bs-theme="dark"] .invalid-feedback {
            color: #f77;
        }

        .was-validated .form-control:invalid, 
        .was-validated .form-select:invalid {
            border-color: #dc3545;
        }
        
        [data-bs-theme="dark"] .was-validated .form-control:invalid,
        [data-bs-theme="dark"] .was-validated .form-select:invalid {
            border-color: #f77;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        
        [data-bs-theme="dark"] .password-toggle {
            color: #adb5bd;
        }
        
        /* Style pour Select2 */
        .select2-container {
            width: 100% !important;
        }
        
        .select2-container--default .select2-selection--multiple {
            min-height: 100px;
            border-radius: 10px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: var(--primary);
            border: none;
            color: white;
            border-radius: 6px;
            padding: 4px 10px;
            margin: 4px;
        }
        
        [data-bs-theme="dark"] .select2-dropdown,
        [data-bs-theme="dark"] .select2-results {
            background-color: #2c3e50;
            color: var(--text-light);
        }
        
        [data-bs-theme="dark"] .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: var(--primary);
        }
        
        [data-bs-theme="dark"] .select2-container--default .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 10px;
            color: white;
        }
        
        .role-admin {
            background-color: var(--admin);
        }
        
        .role-teacher {
            background-color: var(--teacher);
        }
        
        .role-student {
            background-color: var(--student);
        }
        
        .select-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
        }
        
        [data-bs-theme="dark"] .select-hint {
            color: #adb5bd;
        }
        
        .password-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
        }
        
        [data-bs-theme="dark"] .password-info {
            color: #adb5bd;
        }
        
        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }
        
        .card-header-icon {
            font-size: 2rem;
            position: absolute;
            top: 20px;
            right: 20px;
            color: rgba(255, 255, 255, 0.3);
            z-index: 2;
        }
        
        @media (min-width: 992px) {
            .container {
                padding: 3rem;
            }
            
            .card-body {
                padding: 3rem;
            }
        }
    </style>
</head>
<body data-bs-theme="light">
    <!-- Bouton de bascule de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h2 class="mb-0"><i class="fas fa-user-edit me-2"></i>Modifier l'utilisateur</h2>
                        <div class="card-header-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <i class="fas <?php echo $messageType === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($user): ?>
                            <form method="POST" action="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="needs-validation" id="editUserForm" novalidate>
                                <div class="form-group">
                                    <label for="name" class="form-label">Nom complet</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez entrer un nom valide.
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email" class="form-label">Adresse email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez entrer une adresse email valide.
                                    </div>
                                </div>

                                <div class="form-group position-relative">
                                    <label for="password" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Laisser vide pour conserver l'actuel">
                                        <span class="password-toggle" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <div class="password-info">
                                        <i class="fas fa-info-circle me-1"></i> Laisser vide pour ne pas modifier le mot de passe actuel
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="role" class="form-label">Rôle</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="Administrateur" <?php echo ($user['role'] == 'Administrateur') ? 'selected' : ''; ?>>
                                                Administrateur
                                            </option>
                                            <option value="Enseignant" <?php echo ($user['role'] == 'Enseignant') ? 'selected' : ''; ?>>
                                                Enseignant
                                            </option>
                                            <option value="Etudiant" <?php echo ($user['role'] == 'Etudiant') ? 'selected' : ''; ?>>
                                                Étudiant
                                            </option>
                                        </select>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner un rôle.
                                    </div>
                                </div>
                                
                                <div class="form-group" id="groupeField" style="display: <?php echo ($user['role'] == 'Etudiant' || $user['role'] == 'Enseignant') ? 'block' : 'none'; ?>;">
                                    <label for="groupes" class="form-label" id="groupeLabel">
                                        <?php echo ($user['role'] == 'Enseignant') ? 'Groupes enseignés' : 'Groupe'; ?>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-users"></i></span>
                                        <select name="groupes[]" id="groupesSelect" class="form-select" <?php echo ($user['role'] == 'Enseignant') ? 'multiple' : ''; ?>>
                                            <?php foreach ($groupes as $g): ?>
                                                <option value="<?php echo htmlspecialchars($g['group_name']); ?>" 
                                                    <?php 
                                                    if ($user['role'] == 'Enseignant') {
                                                        echo in_array($g['group_name'], $groupes_prof) ? 'selected' : '';
                                                    } else {
                                                        echo $user['groupe'] == $g['group_name'] ? 'selected' : '';
                                                    }
                                                    ?>>
                                                    <?php echo htmlspecialchars($g['group_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="select-hint" id="selectHint" style="display: <?php echo ($user['role'] == 'Enseignant') ? 'block' : 'none'; ?>;">
                                        <i class="fas fa-info-circle me-1"></i> Vous pouvez sélectionner plusieurs groupes
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner au moins un groupe.
                                    </div>
                                </div>

                                <div class="d-grid gap-3 mt-4">
                                    <button type="submit" name="edit_user" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Sauvegarder
                                    </button>
                                    <a href="dashboard_admin.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                L'utilisateur demandé n'existe pas ou n'a pas pu être chargé.
                                <div class="mt-3">
                                    <a href="dashboard_admin.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialisation de Select2 pour améliorer la sélection multiple
        $(document).ready(function() {
            // Détection du thème
            const isDarkMode = $('body').attr('data-bs-theme') === 'dark';
            const theme = isDarkMode ? 'bootstrap5-dark' : 'bootstrap5';
            
            // Initialiser Select2
            function initSelect2() {
                if ($('#role').val() === 'Enseignant') {
                    $('#groupesSelect').select2({
                        theme: theme,
                        placeholder: 'Sélectionnez un ou plusieurs groupes',
                        allowClear: true,
                        width: '100%'
                    });
                }
            }
            
            // Initialiser Select2 au chargement
            initSelect2();
            
            // Réinitialiser Select2 lorsque le rôle change
            $('#role').change(function() {
                if ($(this).val() === 'Enseignant') {
                    setTimeout(function() {
                        $('#groupesSelect').select2({
                            theme: theme,
                            placeholder: 'Sélectionnez un ou plusieurs groupes',
                            allowClear: true,
                            width: '100%'
                        });
                    }, 100);
                } else if ($(this).val() === 'Etudiant') {
                    if ($('#groupesSelect').hasClass('select2-hidden-accessible')) {
                        $('#groupesSelect').select2('destroy');
                    }
                }
            });
            
            // Mettre à jour Select2 lorsque le thème change
            $('#themeToggle').click(function() {
                setTimeout(function() {
                    if ($('#role').val() === 'Enseignant') {
                        if ($('#groupesSelect').hasClass('select2-hidden-accessible')) {
                            $('#groupesSelect').select2('destroy');
                        }
                        const newTheme = $('body').attr('data-bs-theme') === 'dark' ? 'bootstrap5-dark' : 'bootstrap5';
                        $('#groupesSelect').select2({
                            theme: newTheme,
                            placeholder: 'Sélectionnez un ou plusieurs groupes',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                }, 100);
            });
        });

        // Gestion du mode sombre
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        // Vérifier le thème stocké
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        document.body.setAttribute('data-bs-theme', currentTheme);
        
        // Mettre à jour l'icône
        updateThemeIcon(currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            document.body.setAttribute('data-bs-theme', newTheme);
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

        // Afficher/masquer champ groupe selon rôle
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const groupeField = document.getElementById('groupeField');
            const groupesSelect = document.getElementById('groupesSelect');
            const groupeLabel = document.getElementById('groupeLabel');
            const selectHint = document.getElementById('selectHint');
            
            if (role === 'Etudiant') {
                groupeField.style.display = 'block';
                groupesSelect.multiple = false;
                groupeLabel.textContent = 'Groupe';
                selectHint.style.display = 'none';
            } else if (role === 'Enseignant') {
                groupeField.style.display = 'block';
                groupesSelect.multiple = true;
                groupeLabel.textContent = 'Groupes enseignés';
                selectHint.style.display = 'block';
            } else {
                groupeField.style.display = 'none';
            }
        });

        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        // Animation d'entrée pour la carte
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Validation du formulaire
        document.getElementById('editUserForm').addEventListener('submit', function(event) {
            const form = this;
            const role = document.getElementById('role').value;
            const groupesSelect = document.getElementById('groupesSelect');
            
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Validation spécifique pour les groupes
            if ((role === 'Etudiant' || role === 'Enseignant') && 
                groupesSelect.selectedOptions.length === 0) {
                groupesSelect.setCustomValidity('Veuillez sélectionner au moins un groupe');
                event.preventDefault();
            } else {
                groupesSelect.setCustomValidity('');
            }
            
            form.classList.add('was-validated');
        });
    </script>
</body>
</html>
