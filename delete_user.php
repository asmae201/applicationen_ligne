<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit;
}

include('db.php');

if (isset($_GET['id']) && isset($_GET['role'])) {
    $id = $_GET['id'];
    $role = $_GET['role'];
    
    // Prevent deleting the main admin account
    if ($id === 'admin1') {
        header('Location: dashboard_admin.php?message=Cannot delete the main administrator account');
        exit;
    }
    
    // Delete user from database
    $stmt = $conn->prepare("DELETE FROM utilisateur WHERE id = ?");
    $stmt->bind_param("s", $id);
    
    if ($stmt->execute()) {
        // If the user was a professor, remove from professeurgroupe table
        if ($role === 'Enseignant') {
            $conn->query("DELETE FROM professeurgroupe WHERE professeur_id = '$id'");
        }
        
        // Remove from examenetudiant if student
        if ($role === 'Etudiant') {
            $conn->query("DELETE FROM examenetudiant WHERE etudiant_id = '$id'");
        }
        
        header('Location: dashboard_admin.php?message=Utilisateur supprimé avec succès');
    } else {
        header('Location: dashboard_admin.php?message=Erreur lors de la suppression');
    }
    exit;
} else {
    header('Location: dashboard_admin.php');
    exit;
}
?>
