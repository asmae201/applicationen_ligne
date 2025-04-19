<?php
session_start();
include('db.php');

// Ajouter les déclarations "use" ici
require 'vendor/autoload.php'; // Assurez-vous d'inclure PhpSpreadsheet via Composer
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: login.php');
    exit();
}

// Vérification des paramètres
if (!isset($_GET['group_id']) || !isset($_GET['exam_id'])) {
    die("Informations manquantes");
}

$group_id = $_GET['group_id'];
$exam_id = $_GET['exam_id'];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Requête pour récupérer les notes avec possibilité de recherche
    $query = "
        SELECT 
            u.nom AS nom_etudiant, 
            n.score, 
            n.statut, 
            n.date_note,
            g.group_name
        FROM 
            note n
        JOIN 
            Utilisateur u ON n.etudiant_id = u.id
        JOIN 
            groups g ON u.groupe = g.group_name
        WHERE 
            n.examen_id = :exam_id 
            AND g.id = :group_id
            " . ($search_term ? "AND u.nom LIKE :search_term" : "") . "
        ORDER BY 
            n.score DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':exam_id', $exam_id);
    $stmt->bindValue(':group_id', $group_id);
    
    if ($search_term) {
        $stmt->bindValue(':search_term', "%$search_term%");
    }

    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération du titre de l'examen
    $exam_query = "SELECT titre FROM examen WHERE id = :exam_id";
    $exam_stmt = $pdo->prepare($exam_query);
    $exam_stmt->execute([':exam_id' => $exam_id]);
    $exam_title = $exam_stmt->fetchColumn();

    // Exportation vers Excel si demandé
    if (isset($_GET['export']) && $_GET['export'] == 'excel') {
        // Assurez-vous qu'aucune sortie n'a été envoyée avant ce point
        if (ob_get_length()) ob_clean();
        
        // Utiliser PhpSpreadsheet pour exporter en format Excel (.xlsx)
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Ajouter le titre de l'examen
        $sheet->setCellValue('A1', $exam_title . ' - Résultats');
        $sheet->mergeCells('A1:D1');
        
        // En-têtes de colonnes
        $sheet->setCellValue('A2', 'Nom de l\'étudiant');
        $sheet->setCellValue('B2', 'Note');
        $sheet->setCellValue('C2', 'Statut');
        $sheet->setCellValue('D2', 'Date de notation');
        
        $row = 3;
        // Ajouter les données des notes
        foreach ($notes as $note) {
            $sheet->setCellValue('A' . $row, htmlspecialchars($note['nom_etudiant']));
            $sheet->setCellValue('B' . $row, number_format($note['score'], 2) . '/20');
            $sheet->setCellValue('C' . $row, htmlspecialchars($note['statut']));
            $sheet->setCellValue('D' . $row, date('d/m/Y H:i', strtotime($note['date_note'])));
            $row++;
        }
        
        // Ajouter la ligne de la moyenne
        $sheet->setCellValue('A' . $row, 'Moyenne');
        $sheet->setCellValue('B' . $row, number_format(array_sum(array_column($notes, 'score')) / count($notes), 2) . '/20');
        
        // Créer un fichier temporaire
        $temp_file = tempnam(sys_get_temp_dir(), 'excel_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_file);
        
        // Définir les en-têtes pour forcer le téléchargement
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="resultats_' . str_replace(' ', '', $exam_title) . '' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . filesize($temp_file));
        header('Cache-Control: max-age=0');
        
        // Envoyer le contenu du fichier et supprimer le fichier temporaire
        readfile($temp_file);
        unlink($temp_file);
        exit;
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Détails des notes | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variables CSS pour les styles globaux */
        :root {
            /* Palette de couleurs principales */
            --primary: #4db8b8;         /* Couleur primaire (bleu-vert) */
            --primary-dark: #3a9a9a;    /* Version plus sombre du primaire */
            --secondary: #34495e;       /* Couleur secondaire (bleu-gris) */
            --accent: #1abc9c;          /* Couleur d'accent (vert émeraude) */
            --highlight: #f0a500;       /* Couleur de mise en valeur (orange) */
            
            /* Couleurs de texte */
            --text-light: #f8f9fa;      /* Texte clair pour fonds sombres */
            --text-dark: #212529;       /* Texte foncé pour fonds clairs */
            
            /* Couleurs de fond */
            --bg-light: #f8fafc;        /* Arrière-plan clair */
            --bg-dark: #1a1a2e;         /* Arrière-plan sombre */
            --sidebar-dark: #16213e;    /* Couleur pour barre latérale */
            --card-dark: #0f3460;      /* Couleur pour les cartes en mode sombre */
            
            /* Propriétés globales */
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Animation de transition */
            --border-radius: 12px;      /* Rayon des bordures arrondies */
            --box-shadow-light: 0 5px 15px rgba(0, 0, 0, 0.1); /* Ombre légère */
            --box-shadow-dark: 0 5px 15px rgba(0, 0, 0, 0.3);  /* Ombre plus prononcée */
        }

        /* Styles de base pour le corps de la page */
        body {
            font-family: 'Poppins', sans-serif; /* Police principale */
            background-color: var(--bg-light);   /* Couleur de fond par défaut */
            color: var(--text-dark);             /* Couleur de texte par défaut */
            transition: var(--transition);       /* Transition fluide pour les changements */
            line-height: 1.6;                    /* Hauteur de ligne confortable */
            margin: 0;                           /* Supprime les marges par défaut */
            padding: 0;                          /* Supprime les paddings par défaut */
        }

        /* Styles pour le mode sombre */
        [data-bs-theme="dark"] body {
            background-color: var(--bg-dark);    /* Fond sombre */
            color: var(--text-light);            /* Texte clair */
        }

        /* Barre de navigation */
        .navbar {
            background-color: var(--secondary) !important;
            box-shadow: var(--box-shadow-light); /* Ombre subtile */
            padding: 12px 0;
            position: sticky;                   /* Barre fixe en haut */
            top: 0;
            z-index: 1030;                      /* Au-dessus des autres éléments */
        }

        /* Barre de navigation en mode sombre */
        [data-bs-theme="dark"] .navbar {
            background-color: var(--sidebar-dark) !important;
            box-shadow: var(--box-shadow-dark);
        }

        /* Style du logo */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        /* Partie colorée du logo */
        .navbar-brand span {
            color: var(--primary);
            transition: var(--transition);
        }

        /* Liens de navigation */
        .nav-link {
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 15px;
            margin: 0 5px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px; /* Espace entre icône et texte */
        }

        /* Effet hover et état actif */
        .nav-link:hover, 
        .nav-link.active {
            color: var(--primary) !important;
            background-color: rgba(77, 184, 184, 0.1);
            transform: translateY(-2px);
        }

        /* Boutons */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 10px 20px;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Bouton primaire */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        /* Effet hover pour bouton primaire */
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent));
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-light);
            color: white;
        }

        /* Bouton secondaire */
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        /* Étiquettes de formulaire */
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-dark);
            display: block;
        }

        /* Étiquettes en mode sombre */
        [data-bs-theme="dark"] .form-label {
            color: var(--text-light);
        }

        /* Champs de formulaire */
        .form-select, 
        .form-control {
            border-radius: var(--border-radius);
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: var(--transition);
            background-color: #fff;
            width: 100%;
        }

        /* Champs en mode sombre */
        [data-bs-theme="dark"] .form-select, 
        [data-bs-theme="dark"] .form-control {
            background-color: var(--card-dark);
            border-color: var(--sidebar-dark);
            color: var(--text-light);
        }

        /* Effet focus sur les champs */
        .form-select:focus, 
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
            outline: none;
        }

        /* Cartes */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            transition: var(--transition);
            overflow: hidden;
            margin-bottom: 20px;
            background-color: white;
        }

        /* Cartes en mode sombre */
        [data-bs-theme="dark"] .card {
            background-color: var(--card-dark);
            box-shadow: var(--box-shadow-dark);
        }

        /* En-tête de carte */
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 20px;
            border: none;
            font-weight: 600;
        }

        /* En-tête en mode sombre */
        [data-bs-theme="dark"] .card-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent));
        }
        
        /* Animation d'apparition */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Conteneur principal avec animation */
        .main-container {
            animation: fadeIn 0.5s ease-out forwards;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Bouton de basculement de thème */
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
            transition: var(--transition);
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: var(--box-shadow-light);
        }

        /* Bouton en mode sombre */
        [data-bs-theme="dark"] .theme-toggle {
            background: var(--highlight);
            box-shadow: var(--box-shadow-dark);
        }

        /* Effet hover sur le bouton */
        .theme-toggle:hover {
            transform: rotate(15deg) scale(1.1);
        }

        /* Animation du bouton */
        .theme-toggle-animate {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2) rotate(180deg); }
            100% { transform: scale(1); }
        }
        
        /* En-tête de page avec dégradé */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 30px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow-light);
            position: relative;
            overflow: hidden;
        }
        
        /* Motif décoratif pour l'en-tête */
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="5" /></svg>') repeat;
            opacity: 0.2;
        }
        
        /* Titre de l'en-tête */
        .page-header h1 {
            margin: 0;
            font-weight: 600;
            font-size: 2rem;
            position: relative; /* Pour le positionnement par rapport au ::before */
        }
        
        /* Sous-titre de l'en-tête */
        .page-header p {
            margin: 10px 0 0;
            opacity: 0.8;
            position: relative;
            font-size: 1.1rem;
        }

        /* Styles responsives */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .page-header {
                padding: 20px 15px;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                bottom: 20px;
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
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
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>
                            Mon profil
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo isset($_SESSION['nom']) ? htmlspecialchars($_SESSION['nom']) : 'Enseignant'; ?>
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header text-center text-md-start">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-file-alt me-2"></i>
                        Résultats de l'examen - <?php echo htmlspecialchars($exam_title); ?>
                    </h1>
                    <p>Détails des notes pour le groupe sélectionné</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <!-- Formulaire de recherche avec bouton d'exportation -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <form method="GET" action="" class="d-flex">
                                <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group_id); ?>">
                                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_id); ?>">
                                <input 
                                    type="text" 
                                    name="search" 
                                    class="form-control" 
                                    placeholder="Rechercher un étudiant par nom" 
                                    value="<?php echo htmlspecialchars($search_term); ?>"
                                >
                                <button type="submit" class="btn btn-primary ms-2">
                                    <i class="fas fa-search me-1"></i> Rechercher
                                </button>
                                <?php if ($search_term): ?>
                                    <a href="afficher_notes_details.php?group_id=<?php echo htmlspecialchars($group_id); ?>&exam_id=<?php echo htmlspecialchars($exam_id); ?>" class="btn btn-secondary ms-2">
                                        <i class="fas fa-undo me-1"></i> Réinitialiser
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="?group_id=<?php echo htmlspecialchars($group_id); ?>&exam_id=<?php echo htmlspecialchars($exam_id); ?><?php echo $search_term ? '&search='.urlencode($search_term) : ''; ?>&export=excel" class="btn btn-success">
                                <i class="fas fa-file-excel me-1"></i> Exporter vers Excel
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($notes)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nom de l'étudiant</th>
                                    <th>Note</th>
                                    <th>Statut</th>
                                    <th>Date de notation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $note): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($note['nom_etudiant']); ?></td>
                                    <td><?php echo number_format($note['score'], 2); ?>/20</td>
                                    <td><?php echo htmlspecialchars($note['statut']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($note['date_note'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Nombre d'étudiants : <?php echo count($notes); ?> 
                        | Moyenne : <?php 
                            $moyenne = array_sum(array_column($notes, 'score')) / count($notes);
                            echo number_format($moyenne, 2); 
                        ?>/20
                        <?php if ($search_term): ?>
                            | Résultats pour : "<?php echo htmlspecialchars($search_term); ?>"
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php 
                        if ($search_term) {
                            echo "Aucun résultat trouvé pour \"" . htmlspecialchars($search_term) . "\"";
                        } else {
                            echo "Aucun résultat pour cet examen dans ce groupe";
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="list_notes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton de changement de thème -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du mode sombre
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
            
            // Vérifier la préférence système
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Récupérer le thème enregistré ou utiliser la préférence système
            const savedTheme = localStorage.getItem('theme');
            const initialTheme = savedTheme || (prefersDarkScheme.matches ? 'dark' : 'light');
            
            // Appliquer le thème initial
            applyTheme(initialTheme);
            
            // Fonction pour appliquer un thème spécifique
            function applyTheme(theme) {
                htmlElement.setAttribute('data-bs-theme', theme);
                updateThemeIcon(theme);
                localStorage.setItem('theme', theme);
            }
            
            // Mettre à jour l'icône du bouton selon le thème actuel
            function updateThemeIcon(theme) {
                const icon = themeToggle.querySelector('i');
                if (theme === 'dark') {
                    icon.className = 'fas fa-sun';
                } else {
                    icon.className = 'fas fa-moon';
                }
            }
            
            // Écouteur d'événements pour le bouton de basculement
            themeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                // Animation de transition du bouton
                themeToggle.classList.add('theme-toggle-animate');
                setTimeout(() => {
                    themeToggle.classList.remove('theme-toggle-animate');
                }, 500);
                
                applyTheme(newTheme);
            });
            
            // Écouter les changements de préférence système
            prefersDarkScheme.addEventListener('change', (e) => {
                const newTheme = e.matches ? 'dark' : 'light';
                // Ne changer que si l'utilisateur n'a pas explicitement choisi un thème
                if (!localStorage.getItem('theme')) {
                    applyTheme(newTheme);
                }
            });
        });
    </script>
</body>
</html>
