<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['note_id'])) {
    echo json_encode(['error' => 'Λείπει το ID']);
    exit;
}

$noteId = (int)$_GET['note_id'];
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

try {
    // Φέρνουμε τη σημείωση ΚΑΙ τον owner του καμβά για να ξέρουμε αν ο αιτών είναι ο Teacher
    $stmt = $pdo->prepare("
        SELECT n.*, c.owner_id as canvas_owner_id 
        FROM notes n 
        JOIN canvases c ON n.canva_id = c.canva_id 
        WHERE n.note_id = ?
    ");
    $stmt->execute([$noteId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        echo json_encode(['error' => 'Η σημείωση δεν βρέθηκε']);
        exit;
    }

    // ΕΛΕΓΧΟΣ ΔΙΚΑΙΩΜΑΤΩΝ:
    // Επιτρέπουμε αν: 
    // 1. Είναι Admin
    // 2. Είναι ο ιδιοκτήτης του καμβά (Teacher)
    // 3. Είναι ο ιδιοκτήτης της σημείωσης
    $canAccess = ($userRole === 'admin' || $userId === (int)$note['canvas_owner_id'] || $userId === (int)$note['owner_id']);

    if ($canAccess) {
        echo json_encode($note);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Δεν έχετε δικαίωμα προβολής αυτής της σημείωσης']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}