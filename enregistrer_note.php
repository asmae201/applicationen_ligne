<?php
session_start();
include('db.php');

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

// Vérification de l'ID de l'examen
if (!isset($_GET['examenId'])) {
    header('Location: dashboard.php');
    exit();
}

$examen_id = $_GET['examenId'];  // Récupérer l'ID de l'examen

// Mettre à jour les corrections
foreach ($_POST as $key => $value) {
    if (strpos($key, 'corriger_') === 0) {
        $reponse_id = str_replace('corriger_', '', $key);
        $corriger = (int) $value;

        // Mettre à jour la colonne 'reponse_correcte'
        $sql_update = "UPDATE reponse SET reponse_correcte = ? WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ii", $corriger, $reponse_id);
        $stmt->execute();
    }
}

$_SESSION['message'] = "Corrections enregistrées avec succès!";
header('Location: dashboard.php');
exit();
