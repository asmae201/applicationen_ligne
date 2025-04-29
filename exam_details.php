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

// Récupérer les détails de l'examen
$query = "SELECT * FROM examen WHERE id = ? AND enseignant_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$examen_id, $_SESSION['user_id']]);
$examen = $stmt->fetch();

if (!$examen) {
    echo "Examen non trouvé ou vous n'êtes pas autorisé à voir cet examen.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'examen | ExamPro</title>
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

        .container {
            max-width: 800px;
            margin-top: 50px;
        }

        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            border: none;
            transition: var(--transition);
            background-color: white;
            overflow: hidden;
        }

        [data-bs-theme="dark"] .card {
            background-color: var(--card-dark);
            box-shadow: var(--box-shadow-dark);
        }

        .card-header {
            background-color: var(--primary);
            color: white;
            font-size: 1.5rem;
            border-radius: 0;
            padding: 1.25rem;
            border-bottom: none;
            font-weight: 600;
            text-align: center;
        }

        [data-bs-theme="dark"] .card-header {
            background-color: var(--primary-dark);
        }

        .card-body {
            padding: 1.75rem;
        }

        .card-body p {
            font-size: 1.05rem;
            margin-bottom: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        [data-bs-theme="dark"] .card-body p {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .card-body p:last-child {
            border-bottom: none;
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.6rem 1.5rem;
            transition: var(--transition);
            border: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(77, 184, 184, 0.3);
        }

        .btn-warning {
            background-color: var(--highlight);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e09b00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(240, 165, 0, 0.3);
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .theme-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--secondary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--box-shadow-light);
            border: none;
            z-index: 1000;
            transition: var(--transition);
        }

        [data-bs-theme="dark"] .theme-toggle {
            background-color: var(--highlight);
        }

        .theme-toggle:hover {
            transform: scale(1.1) rotate(15deg);
        }

        h2 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1.75rem;
            text-align: center;
        }

        [data-bs-theme="dark"] h2 {
            color: var(--accent);
        }

        strong {
            color: var(--primary);
            font-weight: 600;
        }

        [data-bs-theme="dark"] strong {
            color: var(--accent);
        }

        .text-center a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .text-center a.btn i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .container {
                margin-top: 30px;
                padding: 0 15px;
            }
            
            .card-header {
                font-size: 1.3rem;
                padding: 1rem;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .theme-toggle {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
            
            .actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Détails de l'examen</h2>

        <div class="card">
            <div class="card-header">
                <?php echo htmlspecialchars($examen['titre']); ?>
            </div>
            <div class="card-body">
                <p><strong>Date de l'examen:</strong> <?php echo htmlspecialchars($examen['date']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($examen['description']); ?></p>
                <p><strong>Durée:</strong> <?php echo htmlspecialchars($examen['duree']); ?> minutes</p>

                <div class="actions">
                    <a href="edit_exam.php?id=<?php echo $examen['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Modifier
                    </a>
                    <a href="delete_exam.php?id=<?php echo $examen['id']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet examen ?')">
                        <i class="fas fa-trash-alt me-2"></i>Supprimer
                    </a>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="exam_planned.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Retour à la liste
            </a>
        </div>
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
