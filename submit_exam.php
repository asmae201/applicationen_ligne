<?php
session_start();
require_once 'db.php';

// Verify that the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Etudiant') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$exam_id = $_POST['exam_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get the exam details
    $stmt = $pdo->prepare("SELECT * FROM Examen WHERE id = ? AND date >= CURDATE() AND statut = 'publie'");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        throw new Exception("L'examen n'est plus disponible.");
    }
    
    // Get all questions for this exam
    $stmt = $pdo->prepare("SELECT * FROM Question WHERE examen_id = ?");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll();
    
    // Record attempt
    $stmt = $pdo->prepare("
        INSERT INTO ExamenEtudiant 
        (etudiant_id, examen_id, date_tentative, nombre_tentatives, statut) 
        VALUES (?, ?, NOW(), 1, 'Terminé')
        ON DUPLICATE KEY UPDATE 
        nombre_tentatives = nombre_tentatives + 1,
        date_tentative = NOW(),
        statut = 'Terminé'
    ");
    $stmt->execute([$student_id, $exam_id]);
    
    // Initialize score
    $totalScore = 0;
    $maxPossibleScore = 0;
    
    // Process each question
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $answer_key = "q_" . $question_id;
        
        if (isset($_POST[$answer_key])) {
            $student_answer = $_POST[$answer_key];
            
            // Handle array responses (multiple choices)
            if (is_array($student_answer)) {
                $student_answer = implode("|||", $student_answer);
            }
            
            // Insert response
            $stmt = $pdo->prepare("
                INSERT INTO ReponseEtudiant 
                (etudiant_id, question_id, examen_id, reponse, date_reponse) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$student_id, $question_id, $exam_id, $student_answer]);
            
            // Grading logic
            if ($question['type'] === 'QCM' || $question['type'] === 'choix_multiple') {
                $maxPossibleScore += $question['point_attribue'];
                
                // Compare answers (you might need more complex logic for multiple correct answers)
                $correctAnswers = explode("|||", $question['reponseCorrecte']);
                $studentAnswers = is_array($_POST[$answer_key]) ? $_POST[$answer_key] : [$_POST[$answer_key]];
                
                // Check if all correct answers were selected and no incorrect ones
                $isCorrect = empty(array_diff($correctAnswers, $studentAnswers)) 
                          && empty(array_diff($studentAnswers, $correctAnswers));
                
                if ($isCorrect) {
                    $totalScore += $question['point_attribue'];
                    $status = 'Correct';
                    $is_correct = 1;
                } else {
                    $status = 'Incorrect';
                    $is_correct = 0;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO reponse 
                    (id_etudiant, id_examen, id_question, reponse, date_reponse, statut, reponseCorrecte) 
                    VALUES (?, ?, ?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$student_id, $exam_id, $question_id, $student_answer, $status, $is_correct]);
            } else {
                // Open questions
                $maxPossibleScore += $question['point_attribue'];
                $stmt = $pdo->prepare("
                    INSERT INTO reponse 
                    (id_etudiant, id_examen, id_question, reponse, date_reponse, statut, reponseCorrecte) 
                    VALUES (?, ?, ?, ?, NOW(), 'En attente', 0)
                ");
                $stmt->execute([$student_id, $exam_id, $question_id, $student_answer]);
            }
        }
    }
    
    // Calculate score
    $preliminaryScore = ($maxPossibleScore > 0) ? round(($totalScore / $maxPossibleScore) * 100, 2) : 0;
    
    // Save score
    $stmt = $pdo->prepare("
        INSERT INTO note 
        (etudiant_id, examen_id, score, statut, date_note) 
        VALUES (?, ?, ?, 'Préliminaire', NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), statut = VALUES(statut), date_note = NOW()
    ");
    $stmt->execute([$student_id, $exam_id, $preliminaryScore]);
    
    // Commit transaction
    $pdo->commit();
    
    // Redirect
    header('Location: exam_submitted.php?exam_id=' . $exam_id);
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in submit_exam.php: " . $e->getMessage());
    header('Location: error.php?message=' . urlencode($e->getMessage()));
    exit();
}
?>
