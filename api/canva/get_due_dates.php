<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

session_start();

try {
    // Έλεγχος αν ο χρήστης είναι συνδεδεμένος
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Δεν είστε συνδεδεμένοι']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : null;

    // Βασικό SQL query με φίλτρα
    $sql = "SELECT n.note_id, n.content, n.due_date, c.name as canva_name 
            FROM notes n
            JOIN canvases c ON n.canva_id = c.canva_id
            WHERE n.due_date IS NOT NULL 
            AND n.owner_id = :user_id";

    $params = [':user_id' => $user_id];

    // Προσθήκη φίλτρου για συγκεκριμένο πίνακα αν υπάρχει
    if ($canva_id) {
        $sql .= " AND n.canva_id = :canva_id";
        $params[':canva_id'] = $canva_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($notes as $note) {
        $events[] = [
            'id' => $note['note_id'],
            'title' => mb_substr(strip_tags($note['content']), 0, 30) . '...',
            'start' => $note['due_date'],
            'allDay' => true,
            'extendedProps' => [
                'canva_name' => $note['canva_name']
            ]
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα ανάκτησης σημειώσεων: ' . $e->getMessage()]);
}
?>