<?php
session_start();
include('db.php');

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérification des droits (seuls les enseignants et administrateurs peuvent supprimer)
if ($_SESSION['role'] != 'Enseignant' && $_SESSION['role'] != 'Administrateur') {
    $_SESSION['error_message'] = "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
    header('Location: exam_planned.php');
    exit();
}

// Vérification de l'ID de l'examen
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Identifiant d'examen invalide.";
    header('Location: exam_planned.php');
    exit();
}

$exam_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // Début de la transaction
    $pdo->beginTransaction();
    
    // Vérification que l'examen existe et appartient à l'enseignant (sauf pour admin)
    if ($role == 'Enseignant') {
        $check_query = "SELECT COUNT(*) FROM Examen WHERE id = ? AND enseignant_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$exam_id, $user_id]);
    } else { // Admin peut supprimer n'importe quel examen
        $check_query = "SELECT COUNT(*) FROM Examen WHERE id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$exam_id]);
    }
    
    if ($check_stmt->fetchColumn() == 0) {
        throw new Exception("L'examen n'existe pas ou vous n'avez pas le droit de le supprimer.");
    }
    
    // Suppression des réponses des étudiants liées à cet examen
    $delete_responses = "DELETE FROM ReponseEtudiant WHERE examen_id = ?";
    $stmt_responses = $pdo->prepare($delete_responses);
    $stmt_responses->execute([$exam_id]);
    
    // Suppression des notes liées à cet examen
    $delete_notes = "DELETE FROM note WHERE examen_id = ?";
    $stmt_notes = $pdo->prepare($delete_notes);
    $stmt_notes->execute([$exam_id]);
    
    // Suppression des associations examen-étudiant
    $delete_exam_student = "DELETE FROM ExamenEtudiant WHERE examen_id = ?";
    $stmt_exam_student = $pdo->prepare($delete_exam_student);
    $stmt_exam_student->execute([$exam_id]);
    
    // Suppression des associations examen-groupe
    $delete_exam_group = "DELETE FROM ExamenGroupe WHERE examen_id = ?";
    $stmt_exam_group = $pdo->prepare($delete_exam_group);
    $stmt_exam_group->execute([$exam_id]);
    
    // Suppression des choix liés aux questions de l'examen
    $delete_choices = "DELETE c FROM Choix c 
                      INNER JOIN Question q ON c.question_id = q.id 
                      WHERE q.examen_id = ?";
    $stmt_choices = $pdo->prepare($delete_choices);
    $stmt_choices->execute([$exam_id]);
    
    // Suppression des questions liées à l'examen
    $delete_questions = "DELETE FROM Question WHERE examen_id = ?";
    $stmt_questions = $pdo->prepare($delete_questions);
    $stmt_questions->execute([$exam_id]);
    
    // Enfin, suppression de l'examen
    $delete_exam = "DELETE FROM Examen WHERE id = ?";
    $stmt_exam = $pdo->prepare($delete_exam);
    $stmt_exam->execute([$exam_id]);
    
    // Validation de la transaction
    $pdo->commit();
    
    // Message de succès
    $_SESSION['success_message'] = "L'examen a été supprimé avec succès.";
    
} catch (Exception $e) {
    // Annulation de la transaction en cas d'erreur
    $pdo->rollBack();
    $_SESSION['error_message'] = "Erreur lors de la suppression de l'examen : " . $e->getMessage();
}

// Redirection vers la page des examens
header('Location: exam_planned.php');
exit();
?>
