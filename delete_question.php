<?php
session_start();
require_once('db.php');

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Vérification des paramètres
if (!isset($_GET['questionId']) || !isset($_GET['examenId'])) {
    header('Location: view_questions.php');
    exit();
}

$question_id = $_GET['questionId'];
$exam_id = $_GET['examenId'];

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Supprimer d'abord les choix associés (pour les questions QCM)
    $stmt = $pdo->prepare("DELETE FROM choix WHERE question_id = ?");
    $stmt->execute([$question_id]);

    // 2. Supprimer les réponses des étudiants (si elles existent)
    $stmt = $pdo->prepare("DELETE FROM reponseetudiant WHERE question_id = ?");
    $stmt->execute([$question_id]);

    // 3. Finalement supprimer la question elle-même
    $stmt = $pdo->prepare("DELETE FROM question WHERE id = ? AND examen_id = ?");
    $stmt->execute([$question_id, $exam_id]);

    // Valider la transaction
    $pdo->commit();

    $_SESSION['message'] = "Question supprimée avec succès.";
    $_SESSION['alert_type'] = "success";
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    $_SESSION['message'] = "Erreur lors de la suppression : " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
}

// Rediriger vers la page des questions
header("Location: view_questions.php?examenId=$exam_id");
exit();
?>
