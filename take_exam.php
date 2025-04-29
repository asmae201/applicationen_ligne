<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Etudiant') {
    header('Location: login.php');
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_SESSION['user_id'];

try {
    // Vérifier si l'examen existe et est disponible
    $stmt = $pdo->prepare("
        SELECT * FROM Examen 
        WHERE id = ? AND date >= CURDATE() AND statut = 'publie'
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        die("L'examen n'est pas disponible.");
    }

    // Vérifier si l'étudiant a déjà passé cet examen
    $stmt = $pdo->prepare("
        SELECT * FROM ExamenEtudiant 
        WHERE etudiant_id = ? AND examen_id = ?
    ");
    $stmt->execute([$student_id, $exam_id]);
    $attempt = $stmt->fetch();

    if ($attempt && $attempt['statut'] === 'Terminé') {
        die("Vous avez déjà passé cet examen.");
    }

    // Récupérer les questions pour cet examen
    $stmt = $pdo->prepare("
        SELECT q.*, GROUP_CONCAT(c.texte) as choix_list
        FROM Question q
        LEFT JOIN Choix c ON q.id = c.question_id
        WHERE q.examen_id = ?
        GROUP BY q.id
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['titre']); ?> | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Ton CSS existant reste inchangé */
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        
        /* ... (le reste de ton CSS) ... */
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        
        [data-bs-theme="dark"] {
            --bs-body-bg: var(--bg-dark);
            --bs-body-color: var(--text-light);
            --bs-border-color: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);
            color: var(--text-light);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
        }

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-light);
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            font-size: 1.2em;
            font-weight: bold;
            z-index: 1000;
            border: 2px solid var(--primary);
        }
        
        [data-bs-theme="dark"] .timer {
            background: var(--card-dark);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .timer.warning {
            background: rgba(var(--warning-rgb), 0.2);
            border-color: var(--warning);
            animation: pulse 1s infinite;
        }
        
        .timer.danger {
            background: rgba(var(--danger-rgb), 0.2);
            border-color: var(--danger);
            animation: pulse 0.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .exam-container {
            max-width: 1500px;
            margin: 100px auto 40px;
            padding: 30px;
            background-color: var(--bg-light);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        [data-bs-theme="dark"] .exam-container {
            background-color: var(--card-dark);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .exam-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary-dark));
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .exam-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary-dark));
        }

        .progress {
            height: 10px;
            margin: 20px 0;
            border-radius: 5px;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .progress-bar {
            background-color: var(--accent);
        }

        .question-card {
            margin-bottom: 30px;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--bg-light);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        [data-bs-theme="dark"] .question-card {
            background-color: var(--card-dark);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .question-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .question-card .card-body {
            padding: 25px;
        }

        .question-number {
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .form-check {
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        [data-bs-theme="dark"] .form-check {
            border: 3px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .form-check:hover {
            background-color: rgba(var(--primary-rgb), 0.1);
            border-color: var(--primary);
        }

        .form-check-input:checked + .form-check-label {
            font-weight: bold;
            color: var(--primary);
        }

        .open-question textarea {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 15px;
            min-height: 150px;
            width: 100%;
            transition: all 0.2s;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }
        
        [data-bs-theme="dark"] .open-question textarea {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .open-question textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(77, 184, 184, 0.25);
            outline: none;
        }

        .question-image {
            text-align: center;
            margin: 20px 0;
            border: 3px solid rgba(0, 0, 0, 0.1);
            padding: 15px;
            border-radius: 8px;
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        [data-bs-theme="dark"] .question-image {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .question-image img {
            max-width: 100%;
            height: auto;
            max-height: 300px;
            border-radius: 5px;
        }

        .submit-btn {
            padding: 15px 30px;
            font-size: 1.1em;
            font-weight: 600;
            border-radius: 8px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
            color: white;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(26, 188, 156, 0.3);
        }

        @media (max-width: 768px) {
            .exam-container {
                margin: 80px 15px 30px;
                padding: 20px;
            }
            
            .timer {
                top: 70px;
                right: 10px;
                padding: 10px 15px;
                font-size: 1em;
            }
            
            .theme-toggle {
                top: 15px;
                left: 15px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="timer" id="timer">
        <i class="fas fa-clock me-2"></i>Temps restant: <span id="time"><?php echo $exam['duree']; ?>:00</span>
    </div>

    <div class="exam-container">
        <div class="exam-header">
            <h2 class="mb-3"><i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($exam['titre']); ?></h2>
            <p class="mb-0"><?php echo htmlspecialchars($exam['description']); ?></p>
            <div class="progress mt-4">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     id="progressBar"
                     style="width: 0%">
                </div>
            </div>
        </div>

        <form id="examForm" method="POST" action="submit_exam.php">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="card question-card">
                    <div class="card-body">
                        <span class="question-number">Question <?php echo $index + 1; ?></span>
                        <h5 class="card-title mt-2"><?php echo htmlspecialchars($question['texte']); ?></h5>
                        
                        <?php if (!empty($question['image_path'])): ?>
                            <div class="question-image mt-3 mb-3">
                                <img src="<?php echo htmlspecialchars($question['image_path']); ?>" 
                                     alt="Image de la question" 
                                     class="img-fluid">
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($question['type'] === 'QCM'): ?>
                            <div class="qcm-options mt-4">
                                <?php 
                                $stmt_choices = $pdo->prepare("SELECT * FROM Choix WHERE question_id = ?");
                                $stmt_choices->execute([$question['id']]);
                                $choices = $stmt_choices->fetchAll();
                                
                                foreach ($choices as $choice): 
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="q_<?php echo $question['id']; ?>[]" 
                                               value="<?php echo htmlspecialchars($choice['texte']); ?>">
                                        <label class="form-check-label">
                                            <?php echo htmlspecialchars($choice['texte']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="open-question mt-4">
                                <textarea class="form-control" 
                                          name="q_<?php echo $question['id']; ?>"
                                          placeholder="Écrivez votre réponse ici..."
                                          required></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-lg submit-btn">
                <i class="fas fa-paper-plane me-2"></i>Soumettre l'examen
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        // Check for saved theme preference
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        
        // Update icon based on current theme
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

        // Timer functionality
        let timeLeft = <?php echo $exam['duree']; ?> * 60;
        const timerElement = document.getElementById('time');
        const timerContainer = document.getElementById('timer');
        const totalTime = timeLeft;

        const timer = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            // Update progress bar
            const progress = ((totalTime - timeLeft) / totalTime) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;

            // Add warning classes based on remaining time
            if (timeLeft <= 300) {
                timerContainer.classList.add('danger');
                timerContainer.classList.remove('warning');
            } else if (timeLeft <= 600) {
                timerContainer.classList.add('warning');
            }

            if (timeLeft <= 0) {
                clearInterval(timer);
                document.getElementById('examForm').submit();
            }
        }, 1000);

        // Form auto-save functionality
        const form = document.getElementById('examForm');
        const inputs = form.querySelectorAll('input, textarea');

        inputs.forEach(input => {
            input.addEventListener('change', () => {
                localStorage.setItem(input.name, input.value);
            });

            // Restore saved values
            const savedValue = localStorage.getItem(input.name);
            if (savedValue) {
                if (input.type === 'radio') {
                    if (input.value === savedValue) {
                        input.checked = true;
                    }
                } else {
                    input.value = savedValue;
                }
            }
        });

        // Variable pour suivre si le formulaire a été soumis
        let formSubmitted = false;
        
        // Ajout de la confirmation avant soumission
       // Ajout de la confirmation avant soumission
form.addEventListener('submit', function(e) {
    if (!confirm('Êtes-vous sûr de vouloir soumettre l\'examen?')) {
        e.preventDefault(); // Si l'utilisateur clique sur "Non", ne pas soumettre
    } else {
        // Marquer comme soumis avant même que la page ne change
        formSubmitted = true;
        
        // Nettoyer le localStorage après soumission
        inputs.forEach(input => {
            localStorage.removeItem(input.name);
        });
        
        // Soumettre normalement
        return true;
    }
});

        // Détection des comportements suspects
        let lastBlurTime = 0;
        let blurCount = 0;
        const maxBlurCount = 3;
        let tabSwitchDuration = 0;
        let tabSwitchStartTime = 0;
        let tabSwitchCount = 0;
        const maxTabSwitchCount = 2;

        function recordIncident(incidentType, duration = 0) {
            const data = new FormData();
            data.append('exam_id', '<?php echo $exam_id; ?>');
            data.append('student_id', '<?php echo $student_id; ?>');
            data.append('incident_type', incidentType);
            data.append('duration', duration);
            
            fetch('record_incident.php', {
                method: 'POST',
                body: data
            }).catch(error => console.error('Error:', error));
        }

        document.addEventListener('visibilitychange', function() {
    // Ne pas enregistrer d'incident si le formulaire est en cours de soumission
    if (formSubmitted) {
        return;
    }
    
    if (document.visibilityState === 'hidden') {
        lastBlurTime = Date.now();
        blurCount++;
        tabSwitchStartTime = Date.now();
        recordIncident('window_blur');
        
        if (blurCount > maxBlurCount) {
            alert("Vous avez quitté l'examen trop de fois. L'examen va être soumis automatiquement.");
            form.submit();
        } else {
            alert(`Attention! Vous avez quitté l'examen. Avertissement ${blurCount}/${maxBlurCount}.`);
        }
    } else if (document.visibilityState === 'visible') {
        if (tabSwitchStartTime > 0) {
            const duration = Date.now() - tabSwitchStartTime;
            tabSwitchDuration += duration;
            tabSwitchCount++;
            
            if (duration > 2000) {
                recordIncident('tab_switch', duration);
            }
            
            tabSwitchStartTime = 0;
            
            if (tabSwitchCount > maxTabSwitchCount) {
                alert("Vous avez changé d'onglet trop souvent. L'examen va être soumis automatiquement.");
                form.submit();
            }
        }
    }
});

        window.addEventListener('beforeunload', function(e) {
        if (!formSubmitted && !document.getElementById('examForm').checkValidity()) {
            e.preventDefault();
            e.returnValue = 'Vous êtes en train de passer un examen. Êtes-vous sûr de vouloir quitter?';
            return e.returnValue;
        }
    });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') || 
                (e.ctrlKey && e.shiftKey && e.key === 'J') || 
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                recordIncident('devtools_attempt');
                alert('Cette fonctionnalité est désactivée pendant l\'examen');
            }
        });

        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            recordIncident('right_click_attempt');
            alert('Le clic droit est désactivé pendant l\'examen');
        });
    </script>
</body>
</html>

<?php
} catch(PDOException $e) {
    error_log("Error in take_exam.php: " . $e->getMessage());
    die("Une erreur est survenue.");
}
