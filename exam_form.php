<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Enseignant') {
    header('Location: login.php');
    exit();
}

if (isset($_POST['create_exam'])) {
    $exam_name = $_POST['exam_name'];
    $duration = $_POST['duration'];
    $exam_date = $_POST['exam_date'];
    $selected_group = $_POST['group'];
    
    // Generate a unique exam ID
    $exam_id = 'exam_' . uniqid();
    
    // Get the current teacher's ID from session
    $teacher_id = $_SESSION['user_id'];
    
    // Récupérer le nom du groupe
    $group_query = "SELECT group_name FROM groups WHERE id = ?";
    $group_stmt = $conn->prepare($group_query);
    $group_stmt->bind_param("i", $selected_group);
    $group_stmt->execute();
    $group_result = $group_stmt->get_result();
    $group_row = $group_result->fetch_assoc();
    $groupe_cible = $group_row['group_name'];

    // Use prepared statements to prevent SQL injection
    $sql = "INSERT INTO Examen (id, titre, description, duree, date, enseignant_id, groupe_cible) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $description = $_POST['description'] ?? "";
    
    $stmt->bind_param("sssssss", 
        $exam_id,
        $exam_name, 
        $description,
        $duration, 
        $exam_date,
        $teacher_id,
        $groupe_cible
    );
    
    if ($stmt->execute()) {
        header("Location: add_question.php?examenId=" . $exam_id);
        exit();
    } else {
        $message = "Erreur : " . $stmt->error;
        $alert_type = "danger";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Examen | ExamPro</title>
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
            padding-top: 20px;
        }
        
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-top: 4px solid var(--primary);
        }
        
        [data-bs-theme="dark"] .container {
            background-color: var(--card-dark);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 8px;
        }
        
        [data-bs-theme="dark"] .form-label {
            color: var(--primary);
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 184, 184, 0.3);
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .btn-secondary:hover {
            background-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .alert-danger {
            background-color: #ff6b6b;
            color: #fff;
        }
        
        .input-group-text {
            background-color: var(--primary);
            color: white;
            border: none;
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
        
        .container {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</head>
<body data-bs-theme="light">
    <div class="container">
        <h1><i class="fas fa-pencil-alt me-2"></i>Création d'un Examen</h1>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="exam_form.php">
            <div class="mb-4">
                <label for="exam_name" class="form-label">Nom de l'examen</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-book"></i>
                    </span>
                    <input type="text" class="form-control" id="exam_name" name="exam_name" placeholder="Nom de l'examen" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="exam_date" class="form-label">Date de l'examen</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-calendar-day"></i>
                    </span>
                    <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="duration" class="form-label">Durée (en minutes)</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-clock"></i>
                    </span>
                    <input type="number" class="form-control" id="duration" name="duration" placeholder="Durée en minutes" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="group" class="form-label">Groupe</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-users"></i>
                    </span>
                    <select class="form-control" id="group" name="group" required>
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
            </div>
            
            <div class="mb-4">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" placeholder="Description de l'examen (optionnel)"></textarea>
            </div>

            <div class="d-flex justify-content-between mt-5">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary" name="create_exam">
                    <i class="fas fa-save me-2"></i>
                    Créer l'examen
                </button>
            </div>
        </form>
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
    </script>
</body>
</html>
