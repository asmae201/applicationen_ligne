<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

// Gestion du mode sombre
if (isset($_POST['toggle_dark_mode'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) ? true : !$_SESSION['dark_mode'];
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit();
}

// Vérification de l'ID de l'examen
if (!isset($_GET['id'])) {
    echo "ID d'examen non spécifié.";
    exit();
}

$examen_id = $_GET['id'];
$message = '';

// Récupérer les détails de l'examen
$query = "SELECT * FROM examen WHERE id = ? AND enseignant_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$examen_id, $_SESSION['user_id']]);
$examen = $stmt->fetch();

if (!$examen) {
    echo "Examen non trouvé ou vous n'êtes pas autorisé à modifier cet examen.";
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['toggle_dark_mode'])) {
    $titre = $_POST['titre'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $duree = $_POST['duree'];
    $type = isset($_POST['type']) ? $_POST['type'] : 'normal';

    // Mise à jour des données
    $query = "UPDATE examen SET titre = ?, date = ?, description = ?, duree = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    
    if ($stmt->execute([$titre, $date, $description, $duree, $examen_id])) {
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        Les modifications ont été enregistrées avec succès!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        
        // Recharger les données
        $query = "SELECT * FROM examen WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$examen_id]);
        $examen = $stmt->fetch();
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Erreur lors de la mise à jour de l\'examen.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'examen | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --card-dark: #0f3460;
            --alert-info: #d1ecf1;
            --alert-warning: #fff3cd;
            --alert-success: #d4edda;
            --alert-danger: #f8d7da;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --border-radius: 12px;
            --box-shadow-light: 0 5px 15px rgba(0, 0, 0, 0.1);
            --box-shadow-dark: 0 5px 15px rgba(0, 0, 0, 0.3);
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

        .navbar {
            background-color: var(--secondary) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        [data-bs-theme="dark"] .navbar {
            background-color: #16213e !important;
        }

        .navbar-brand {
            font-weight: 700;
        }

        .navbar-brand span {
            color: var(--primary);
        }

        .main-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
            transition: var(--transition);
        }

        [data-bs-theme="dark"] .main-container {
            background-color: var(--card-dark);
            box-shadow: var(--box-shadow-dark);
        }

        .page-header {
            background: linear-gradient(to right, var(--primary), var(--accent));
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        [data-bs-theme="dark"] .page-header {
            background: linear-gradient(to right, var(--primary-dark), var(--accent));
        }

        h2 {
            color: var(--dark);
            font-weight: 600;
        }

        [data-bs-theme="dark"] h2 {
            color: var(--accent);
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }

        [data-bs-theme="dark"] .form-label {
            color: var(--text-light);
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: var(--transition);
            background-color: white;
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: var(--card-dark);
            border-color: var(--secondary);
            color: var(--text-light);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 12px 24px;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--accent));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
        }

        .btn-success {
            background: linear-gradient(to right, #28a745, #218838);
            border: none;
        }

        .btn-success:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-outline-light {
            border-color: var(--text-light);
            color: var(--text-light);
        }

        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
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
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: var(--box-shadow-light);
            transition: var(--transition);
        }

        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
            box-shadow: 0 5px 15px rgba(240, 165, 0, 0.3);
        }

        .theme-toggle:hover {
            transform: rotate(15deg) scale(1.1);
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
        }

        [data-bs-theme="dark"] .alert-success {
            background-color: #155724;
            color: #d4edda;
        }

        [data-bs-theme="dark"] .alert-danger {
            background-color: #721c24;
            color: #f8d7da;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 20px;
                margin-top: 15px;
            }
            
            .theme-toggle {
                bottom: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <span>Exam</span>Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="exam_planned.php">
                            <i class="fas fa-calendar-alt me-1"></i> Examens
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['nom'] ?? 'Enseignant'); ?>
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <!-- En-tête -->
        <div class="page-header">
            <h2><i class="fas fa-edit me-2"></i> Modifier l'examen</h2>
        </div>
        
        <!-- Message de confirmation -->
        <?php echo $message; ?>
        
        <!-- Formulaire -->
        <form method="POST">
            <div class="mb-4">
                <label for="titre" class="form-label">Titre de l'examen</label>
                <input type="text" class="form-control" id="titre" name="titre" 
                       value="<?php echo htmlspecialchars($examen['titre']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="date" class="form-label">Date de l'examen</label>
                <input type="date" class="form-control" id="date" name="date" 
                       value="<?php echo htmlspecialchars($examen['date']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="4" required><?php echo htmlspecialchars($examen['description']); ?></textarea>
            </div>
            
            <div class="mb-4">
                <label for="duree" class="form-label">Durée (minutes)</label>
                <input type="number" class="form-control" id="duree" name="duree" 
                       value="<?php echo htmlspecialchars($examen['duree']); ?>" required>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="exam_details.php?id=<?php echo $examen_id; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i> Retour
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Bouton de basculement du thème -->
    <button class="theme-toggle" id="themeToggle">
        <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>'; ?>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du mode sombre
        document.getElementById('themeToggle').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('toggle_dark_mode', 'true');
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.reload();
            });
        });
    </script>
</body>
</html>
