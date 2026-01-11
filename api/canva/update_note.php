<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ο χρήστης δεν είναι συνδεδεμένος.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['note_id'])) {
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρα δεδομένα.']);
    exit;
}

$note_id = (int)$input['note_id'];
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

try {
    // 1. Φέρνουμε τη σημείωση ΚΑΙ τον owner του καμβά
    $checkSql = "SELECT n.*, c.owner_id as canvas_owner_id 
                 FROM notes n 
                 JOIN canvases c ON n.canva_id = c.canva_id 
                 WHERE n.note_id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$note_id]);
    $note = $checkStmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Η σημείωση δεν βρέθηκε.']);
        exit;
    }

    // 2. ΕΛΕΓΧΟΣ ΔΙΚΑΙΩΜΑΤΩΝ (Admin ή Owner Καμβά ή Owner Σημείωσης)
    $isAdmin = ($user_role === 'admin');
    $isCanvasOwner = ((int)$note['canvas_owner_id'] === $user_id);
    $isNoteOwner = ((int)$note['user_id'] === $user_id);

    if (!$isAdmin && !$isCanvasOwner && !$isNoteOwner) {
        echo json_encode(['success' => false, 'error' => 'Δεν έχετε δικαίωμα επεξεργασίας.']);
        exit;
    }

    // 3. ΕΛΕΓΧΟΣ LOCK (Μόνο αν δεν είναι Admin ή Canvas Owner)
    if (!$isAdmin && !$isCanvasOwner) {
        if (!empty($note['locked_by']) && (int)$note['locked_by'] !== $user_id) {
            echo json_encode(['success' => false, 'error' => 'Η σημείωση είναι κλειδωμένη από άλλον.']);
            exit;
        }
    }

    // 4. ΕΝΗΜΕΡΩΣΗ
    $allowedFields = ['content', 'color', 'position_x', 'position_y', 'tag', 'icon', 'due_date', 'font'];
    $updates = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = ($field === 'due_date' && empty($input[$field])) ? null : $input[$field];
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'Καμία αλλαγή.']);
        exit;
    }

    // Ξεκλειδώνουμε αυτόματα τη σημείωση μετά την αποθήκευση
    $sql = "UPDATE notes SET " . implode(', ', $updates) . ", locked_by = NULL, locked_at = NULL WHERE note_id = ?";
    $params[] = $note_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}