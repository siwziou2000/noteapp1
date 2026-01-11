<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση']);
    exit;
}

$canva_id = $_POST['canva_id'] ?? null;
$new_access_type = $_POST['new_access_type'] ?? null;
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$canva_id || !$new_access_type || $action !== 'change_access_type') {
    echo json_encode(['success' => false, 'message' => 'Λείπουν απαραίτητα στοιχεία']);
    exit;
}

try {
    // 1. Φέρνουμε τον ρόλο του χρήστη από τη βάση (όπως το κάνεις στον κώδικά σου)
    $stmtUserRole = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmtUserRole->execute([$user_id]);
    $currentUserRole = $stmtUserRole->fetchColumn();

    // 2. Ελέγχουμε αν είναι Owner ή Admin
    $check_stmt = $pdo->prepare("
        SELECT owner_id 
        FROM canvases 
        WHERE canva_id = ?
    ");
    $check_stmt->execute([$canva_id]);
    $canvas = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$canvas) {
        echo json_encode(['success' => false, 'message' => 'Ο πίνακας δεν βρέθηκε']);
        exit;
    }

    // Η ΛΟΓΙΚΗ ΣΟΥ: Αν είναι admin Ή αν το owner_id ταιριάζει με το user_id
    $isAdmin = ($currentUserRole === 'admin');
    $isOwner = ($canvas['owner_id'] == $user_id);

    if (!$isAdmin && !$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχετε δικαίωμα αλλαγής (Admin: ' . ($isAdmin ? 'Yes' : 'No') . ')']);
        exit;
    }

    // 3. ΕΝΗΜΕΡΩΣΗ
    $update_stmt = $pdo->prepare("
        UPDATE canvases 
        SET access_type = ? 
        WHERE canva_id = ?
    ");
    
    $result = $update_stmt->execute([$new_access_type, $canva_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Η πρόσβαση ενημερώθηκε επιτυχώς']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Η ενημέρωση απέτυχε']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}