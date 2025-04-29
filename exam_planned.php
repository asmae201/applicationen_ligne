<?php
session_start();
include('db.php');

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
};

// Empêcher l'accès aux étudiants
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Etudiant') {
    header('Location: student_exams.php'); // Rediriger vers une page pour étudiants
    exit();
}

// Assurer que le rôle est défini
if (!isset($_SESSION['role'])) {
    $_SESSION['error_message'] = "Rôle utilisateur non défini";
    header('Location: login.php');
    exit();
}
$role = $_SESSION['role'];

// Gestion du mode sombre
if (isset($_POST['toggle_dark_mode'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) ? true : !$_SESSION['dark_mode'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Gestion de la publication d'un examen
if (isset($_POST['publish_exam_id']) && isset($_POST['target_groups']) && $role == 'Enseignant') {
    $exam_id = $_POST['publish_exam_id'];
    $target_groups = $_POST['target_groups'];
    
    // Vérifier que l'enseignant a accès à ces groupes
    $has_access = true;
    foreach ($target_groups as $group) {
        $access_query = "SELECT COUNT(*) FROM ProfesseurGroupe WHERE professeur_id = ? AND group_name = ?";
        $access_stmt = $pdo->prepare($access_query);
        $access_stmt->execute([$_SESSION['user_id'], $group]);
        if ($access_stmt->fetchColumn() == 0) {
            $has_access = false;
            break;
        }
    }
    
    if ($has_access) {
        $pdo->beginTransaction();
        try {
            // Mettre à jour l'examen comme publié
            $update_query = "UPDATE Examen SET statut = 'publie' WHERE id = ? AND enseignant_id = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$exam_id, $_SESSION['user_id']]);
            
            // Supprimer les anciennes associations
            $delete_query = "DELETE FROM ExamenGroupe WHERE examen_id = ?";
            $delete_stmt = $pdo->prepare($delete_query);
            $delete_stmt->execute([$exam_id]);
            
            // Insérer les nouvelles associations
            $insert_query = "INSERT INTO ExamenGroupe (examen_id, group_name) VALUES (?, ?)";
            $insert_stmt = $pdo->prepare($insert_query);
            foreach ($target_groups as $group) {
                $insert_stmt->execute([$exam_id, $group]);
            }
            
            $pdo->commit();
            $_SESSION['exam_published'] = true;
            $_SESSION['success_message'] = "L'examen a été publié avec succès pour " . count($target_groups) . " groupe(s).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Erreur lors de la publication: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Vous n'avez pas les droits pour publier à ces groupes";
    }
    
    if (!isset($_SESSION['redirected'])) {
        $_SESSION['redirected'] = true;
        header("Location: exam_planned.php");
        exit();
    }
} else {
    unset($_SESSION['redirected']);
}

// Récupérer les examens
$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = !empty($search) ? " AND (e.titre LIKE :search OR e.description LIKE :search)" : "";

if ($role == 'Enseignant') {
    $query = "SELECT e.*, GROUP_CONCAT(eg.group_name SEPARATOR ', ') as groupes_cibles 
              FROM Examen e 
              LEFT JOIN ExamenGroupe eg ON e.id = eg.examen_id 
              WHERE e.enseignant_id = :user_id" . $searchCondition . " 
              GROUP BY e.id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
    
    // Récupérer les groupes de l'enseignant
    $groups_query = "SELECT group_name FROM ProfesseurGroupe WHERE professeur_id = ?";
    $groups_stmt = $pdo->prepare($groups_query);
    $groups_stmt->execute([$user_id]);
    $teacher_groups = $groups_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

} elseif ($role == 'Administrateur') {
    $query = "SELECT e.*, u.nom as nom_enseignant, u.prenom as prenom_enseignant, 
              GROUP_CONCAT(eg.group_name SEPARATOR ', ') as groupes_cibles 
              FROM Examen e 
              JOIN Utilisateur u ON e.enseignant_id = u.id 
              LEFT JOIN ExamenGroupe eg ON e.id = eg.examen_id 
              WHERE 1=1" . $searchCondition . " 
              GROUP BY e.id";
    $stmt = $pdo->prepare($query);
}

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
$examens = $stmt->fetchAll();

// Vérification des résultats
if (empty($examens)) {
    $noExamsMessage = "Aucun examen disponible pour le moment.";
    if (!empty($search)) {
        $noExamsMessage = "Aucun examen trouvé pour votre recherche.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Examens | ExamPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        }

        [data-bs-theme="dark"] .main-container {
            background-color: var(--card-dark);
        }

        .page-header {
            background: linear-gradient(to right, var(--primary), var(--accent));
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--accent));
        }

        .table thead {
            background: linear-gradient(to right, var(--primary), var(--accent));
            color: white;
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
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: var(--primary);
            color: white;
            border-radius: var(--border-radius);
            z-index: 1000;
            display: none;
            animation: fadeIn 0.5s, fadeOut 0.5s 4.5s;
        }

        .bulk-actions {
            margin-bottom: 20px;
            display: none;
        }

        .bulk-actions.show {
            display: block;
        }

        .search-results-info {
            margin-bottom: 15px;
            font-style: italic;
            color: #6c757d;
        }

        [data-bs-theme="dark"] .search-results-info {
            color: #adb5bd;
        }

        #searchInput {
            padding: 10px 15px;
            border-radius: 50px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        #searchInput:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(77, 184, 184, 0.25);
        }

        .select-all-checkbox {
            margin-right: 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
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
            <div class="collapse navbar-collapse">
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
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['nom'] ?? 'Utilisateur'); ?>
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
            <h2>
                <i class="fas fa-calendar-alt me-2"></i>
                Liste des Examens
            </h2>
        </div>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Barre de recherche -->
        <div class="search-bar mb-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions groupées -->
        <div class="bulk-actions mb-3" id="bulkActions">
            <div class="d-flex align-items-center">
                <button class="btn btn-danger me-2" id="deleteSelected">
                    <i class="fas fa-trash-alt me-1"></i> Supprimer la sélection
                </button>
                <span id="selectedCount">0 élément(s) sélectionné(s)</span>
            </div>
        </div>
        
        <!-- Info résultats recherche -->
        <div id="searchResultsInfo" class="search-results-info"></div>
        
        <?php if (!empty($search) && isset($noExamsMessage)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> 
                <?php echo $noExamsMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($examens) && !isset($noExamsMessage)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                Aucun examen disponible pour le moment.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="select-all-checkbox">
                            </th>
                            <th>#</th>
                            <th>Titre</th>
                            <?php if ($role != 'Enseignant'): ?>
                                <th>Enseignant</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Statut</th>
                            <?php if ($role == 'Enseignant'): ?>
                                <th>Groupes Cibles</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examens as $index => $examen): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="exam-checkbox" value="<?php echo $examen['id']; ?>">
                                </td>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($examen['titre']); ?></td>
                                <?php if ($role != 'Enseignant'): ?>
                                    <td>
                                        <?php 
                                        if (isset($examen['nom_enseignant'])) {
                                            echo htmlspecialchars($examen['prenom_enseignant'] . ' ' . $examen['nom_enseignant']);
                                        } else {
                                            echo "Non spécifié";
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($examen['date'])); ?>
                                </td>
                                <td>
                                    <?php if ($examen['statut'] == 'brouillon'): ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-pencil-alt me-1"></i> Brouillon
                                        </span>
                                    <?php elseif ($examen['statut'] == 'publie'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Publié
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-flag-checkered me-1"></i> Terminé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($role == 'Enseignant'): ?>
                                    <td><?php echo htmlspecialchars($examen['groupes_cibles'] ?? 'Non défini'); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($role == 'Enseignant'): ?>
                                        <?php if ($examen['statut'] == 'brouillon'): ?>
                                            <a href="edit_exam.php?id=<?php echo $examen['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Modifier
                                            </a>
                                            <form class="d-inline-block" method="POST" onsubmit="return confirm('Publier cet examen ?');">
                                                <input type="hidden" name="publish_exam_id" value="<?php echo $examen['id']; ?>">
                                                <select name="target_groups[]" class="form-select form-select-sm select2-multi" required multiple style="width: 200px;">
                                                    <?php foreach ($teacher_groups as $group): ?>
                                                        <option value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars($group); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-paper-plane"></i> Publier
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="exam_details.php?id=<?php echo $examen['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-info-circle"></i> Détails
                                        </a>
                                    <?php elseif ($role == 'Administrateur'): ?>
                                        <a href="exam_details.php?id=<?php echo $examen['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-info-circle"></i> Détails
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete_exam.php?id=<?php echo $examen['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression ?');">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if ($role == 'Enseignant'): ?>
            <div class="mt-4">
                <a href="exam_form.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Créer un examen
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bouton thème -->
    <button class="theme-toggle" id="themeToggle">
        <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>'; ?>
    </button>

    <!-- Notifications -->
    <div id="publishNotification" class="notification">
        <i class="fas fa-check-circle me-2"></i> Examen publié avec succès
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-multi').select2({
                placeholder: "Sélectionner groupes",
                width: 'resolve'
            });

            $('#themeToggle').click(function() {
                $.post('', { toggle_dark_mode: true }, function() {
                    location.reload();
                });
            });

            <?php if (isset($_SESSION['exam_published']) && $_SESSION['exam_published']): ?>
                $('#publishNotification').fadeIn().delay(4500).fadeOut();
                <?php unset($_SESSION['exam_published']); ?>
            <?php endif; ?>

            // Recherche dynamique
            $('#searchInput').on('input', function() {
                const searchText = $(this).val().toLowerCase();
                let visibleCount = 0;
                
                $('tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    if (rowText.includes(searchText)) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });
                
                // Mettre à jour l'info des résultats
                const total = $('tbody tr').length;
                const infoText = searchText ? 
                    `${visibleCount} résultat(s) sur ${total} trouvé(s) pour "${searchText}"` : 
                    `Affichage de tous les ${total} résultats`;
                
                $('#searchResultsInfo').text(infoText);
            });

            // Gestion de la sélection multiple
            $('#selectAll').change(function() {
                $('.exam-checkbox').prop('checked', $(this).prop('checked'));
                updateBulkActions();
            });

            $('.exam-checkbox').change(function() {
                if (!$(this).prop('checked')) {
                    $('#selectAll').prop('checked', false);
                }
                updateBulkActions();
            });

            function updateBulkActions() {
                const selectedCount = $('.exam-checkbox:checked').length;
                $('#selectedCount').text(selectedCount + ' élément(s) sélectionné(s)');
                
                if (selectedCount > 0) {
                    $('#bulkActions').addClass('show');
                } else {
                    $('#bulkActions').removeClass('show');
                }
            }

            // Suppression en masse
            $('#deleteSelected').click(function() {
                const selectedIds = $('.exam-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedIds.length === 0) return;
                
                if (confirm(`Voulez-vous vraiment supprimer les ${selectedIds.length} examens sélectionnés ?`)) {
                    // Envoyer les IDs au serveur pour suppression
                    $.post('delete_exams.php', { ids: selectedIds }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Erreur lors de la suppression : ' + response.message);
                        }
                    }).fail(function() {
                        alert('Erreur lors de la communication avec le serveur');
                    });
                }
            });

            // Initialiser l'info des résultats
            $('#searchInput').trigger('input');
        });
    </script>
</body>
</html>
