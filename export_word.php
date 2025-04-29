<?php
session_start();
require_once('db.php');
// Inclure la bibliothèque PhpWord
require_once 'vendor/autoload.php'; // Assurez-vous d'avoir installé PhpOffice/PhpWord via Composer

// Vérification de la session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Enseignant') {
    header('Location: index.php');
    exit();
}

// Vérification de l'ID examen
if (!isset($_GET['examenId']) || empty($_GET['examenId'])) {
    die("ID d'examen non valide.");
}

$exam_id = $_GET['examenId'];

try {
    // Récupérer les informations de l'examen
    $stmt = $pdo->prepare("SELECT titre FROM examen WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        die("Examen non trouvé.");
    }
    
    $exam_title = $exam['titre'];
    
    // Récupérer toutes les questions
    $stmt = $pdo->prepare("SELECT * FROM question WHERE examen_id = ? ORDER BY id");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Utiliser PhpWord pour créer le document
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Définir les styles
    $sectionStyle = array(
        'orientation' => 'portrait',
        'marginTop' => 1000,
        'marginBottom' => 1000,
        'marginLeft' => 1200,
        'marginRight' => 1200
    );
    
    $titleStyle = array(
        'name' => 'Arial',
        'size' => 16,
        'bold' => true,
        'color' => '333333'
    );
    
    $headingStyle = array(
        'name' => 'Arial',
        'size' => 14,
        'bold' => true,
        'color' => '4db8b8'
    );
    
    $normalStyle = array(
        'name' => 'Arial',
        'size' => 11
    );
    
    $questionStyle = array(
        'name' => 'Arial',
        'size' => 12,
        'bold' => true
    );
    
    $choiceStyle = array(
        'name' => 'Arial',
        'size' => 11,
        'bold' => false
    );
    
    $correctAnswerStyle = array(
        'name' => 'Arial',
        'size' => 11,
        'bold' => true,
        'color' => '198754',  // Success color green
        'italic' => true
    );
    
    // Créer la section
    $section = $phpWord->addSection($sectionStyle);
    
    // Ajouter le titre du document
    $section->addText($exam_title, $titleStyle, ['align' => 'center']);
    $section->addText('Document d\'examen', ['size' => 12, 'italic' => true], ['align' => 'center']);
    $section->addTextBreak(2);
    
    // Ajouter une introduction
    $section->addText('Liste des questions', $headingStyle);
    $section->addText('Ce document contient toutes les questions de l\'examen "' . $exam_title . '".', $normalStyle);
    $section->addTextBreak(1);
    
    // Parcourir toutes les questions
    $questionNumber = 1;
    foreach ($questions as $question) {
        // Ajouter le numéro et le texte de la question
        $section->addText('Question ' . $questionNumber . ' (' . $question['point_attribue'] . ' points) :', $headingStyle);
        $section->addText($question['texte'], $questionStyle);
        
        // Ajouter l'image si elle existe
        if (!empty($question['image_path'])) {
            $imagePath = $question['image_path'];
            if (file_exists($imagePath)) {
                $section->addImage($imagePath, ['width' => 300, 'height' => 200, 'alignment' => 'center']);
            } else {
                $section->addText('(Image non disponible: ' . $imagePath . ')', ['italic' => true, 'color' => 'red']);
            }
        }
        
        $section->addTextBreak(1);
        
        // Si c'est une question à choix multiples, ajouter les choix
        if ($question['type'] === 'QCM') {
            $stmt = $pdo->prepare("SELECT texte FROM choix WHERE question_id = ?");
            $stmt->execute([$question['id']]);
            $choix = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $section->addText('Choix:', ['bold' => true]);
            
            $choiceCounter = 1;
            foreach ($choix as $index => $c) {
                $listItemText = $choiceCounter . '. ' . $c;
                
                // Si c'est la bonne réponse, le mettre en évidence (pour la version enseignant)
                if (!empty($question['reponseCorrecte'])) {
                    if ((is_numeric($question['reponseCorrecte']) && (int)$question['reponseCorrecte'] === $index) || 
                        $question['reponseCorrecte'] === $c) {
                        $section->addText($listItemText . ' ✓', $correctAnswerStyle);
                    } else {
                        $section->addText($listItemText, $choiceStyle);
                    }
                } else {
                    $section->addText($listItemText, $choiceStyle);
                }
                $choiceCounter++;
            }
        } else {
            // Question ouverte
            $section->addText('Type: Question ouverte (réponse libre)', ['italic' => true]);
            
            // Ajouter des lignes pour la réponse
            $section->addText('Réponse:', ['bold' => true]);
            for ($i = 0; $i < 3; $i++) {
                $section->addText('', ['size' => 11, 'color' => 'AAAAAA']);
            }
        }
        
        $section->addTextBreak(2);
        $questionNumber++;
    }
    
    // Créer une version "étudiant" sans les réponses correctes
    $sectionStudent = $phpWord->addSection(['breakType' => 'nextPage'] + $sectionStyle);
    
    $sectionStudent->addText($exam_title, $titleStyle, ['align' => 'center']);
    $sectionStudent->addText('Document d\'examen - Version Étudiant', ['size' => 12, 'italic' => true], ['align' => 'center']);
    $sectionStudent->addTextBreak(2);
    
    $sectionStudent->addText('Liste des questions', $headingStyle);
    $sectionStudent->addText('Ce document contient toutes les questions de l\'examen "' . $exam_title . '".', $normalStyle);
    $sectionStudent->addTextBreak(1);
    
    // Parcourir toutes les questions (version étudiant)
    $questionNumber = 1;
    foreach ($questions as $question) {
        $sectionStudent->addText('Question ' . $questionNumber . ' (' . $question['point_attribue'] . ' points) :', $headingStyle);
        $sectionStudent->addText($question['texte'], $questionStyle);
        
        // Ajouter l'image si elle existe
        if (!empty($question['image_path'])) {
            $imagePath = $question['image_path'];
            if (file_exists($imagePath)) {
                $sectionStudent->addImage($imagePath, ['width' => 300, 'height' => 200, 'alignment' => 'center']);
            } else {
                $sectionStudent->addText('(Image non disponible)', ['italic' => true, 'color' => 'red']);
            }
        }
        
        $sectionStudent->addTextBreak(1);
        
        // Si c'est une question à choix multiples, ajouter les choix
        if ($question['type'] === 'QCM') {
            $stmt = $pdo->prepare("SELECT texte FROM choix WHERE question_id = ?");
            $stmt->execute([$question['id']]);
            $choix = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $sectionStudent->addText('Choix:', ['bold' => true]);
            
            $choiceCounter = 1;
            foreach ($choix as $c) {
                $sectionStudent->addText($choiceCounter . '. ' . $c, $choiceStyle);
                $choiceCounter++;
            }
        } else {
            // Question ouverte
            $sectionStudent->addText('Type: Question ouverte (réponse libre)', ['italic' => true]);
            
            // Ajouter des lignes pour la réponse
            $sectionStudent->addText('Réponse:', ['bold' => true]);
            for ($i = 0; $i < 5; $i++) {
                $sectionStudent->addText('', ['size' => 11, 'color' => 'AAAAAA']);
            }
        }
        
        $sectionStudent->addTextBreak(2);
        $questionNumber++;
    }
    
    // Ajouter informations en pied de page
    $footer = $section->addFooter();
    $footer->addText(
        'Document généré le ' . date('d/m/Y à H:i') . ' par ExamPro - ' . $exam_title, 
        ['size' => 8, 'color' => '666666'], 
        ['alignment' => 'center']
    );
    
    $footerStudent = $sectionStudent->addFooter();
    $footerStudent->addText(
        'Document généré le ' . date('d/m/Y à H:i') . ' par ExamPro - ' . $exam_title . ' (Version Étudiant)', 
        ['size' => 8, 'color' => '666666'], 
        ['alignment' => 'center']
    );
    
    // Sauvegarder le document
    $filename = 'Examen_' . preg_replace('/[^a-zA-Z0-9]/', '', $exam_title) . '' . date('Ymd') . '.docx';
    
    // Définir les en-têtes pour que le fichier soit téléchargé au lieu d'être affiché
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Enregistrer le fichier dans le flux de sortie
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;
    
} catch (Exception $e) {
    die("Erreur lors de la génération du fichier Word: " . $e->getMessage());
}
?>
