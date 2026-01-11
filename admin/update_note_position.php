<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

$data = json_decode(file_get_contents("php://input"), true);
$note_id = intval($data['note_id']);
$position_x = intval($data['position_x']);
$position_y = intval($data['position_y']);

if ($note_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid note ID']);
    exit;
}

$stmt = $pdo->prepare("UPDATE notes SET position_x = :x, position_y = :y WHERE note_id = :note_id");
$success = $stmt->execute([
    'x' => $position_x,
    'y' => $position_y,
    'note_id' => $note_id
]);

echo json_encode(['success' => $success]);
