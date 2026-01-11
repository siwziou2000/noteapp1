<?php
session_start();
header('Content-Type: application/json');

require $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
require $_SERVER['DOCUMENT_ROOT'] . '/noteapp/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CSRF Protection
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
if (!isset($headers['x-csrf-token']) || $headers['x-csrf-token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$note_id = (int)($data['note_id'] ?? 0);
$share_with = trim($data['share_with'] ?? '');
$permission = in_array($data['permission'] ?? '', ['view', 'edit']) ? $data['permission'] : 'view';
$notify = !empty($data['notify']);
$owner_id = (int)$_SESSION['user_id'];

try {
    // Verify note ownership
    $stmt = $pdo->prepare("SELECT note_id, content, canva_id FROM notes WHERE note_id = ? AND owner_id = ?");
    $stmt->execute([$note_id, $owner_id]);
    $note = $stmt->fetch();
    
    if (!$note) {
        throw new Exception('Note not found or permission denied');
    }

    // Find user to share with
    $stmt = $pdo->prepare("SELECT user_id, email, username FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$share_with, $share_with]);
    $shared_user = $stmt->fetch();

    if (!$shared_user) {
        throw new Exception('User not found');
    }

    $shared_user_id = $shared_user['user_id'];
    $shared_user_email = $shared_user['email'];
    $shared_user_name = $shared_user['username'];

    // Check if already shared
    $stmt = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND shared_with = ?");
    $stmt->execute([$note_id, $shared_user_id]);
    if ($stmt->fetch()) {
        throw new Exception('ΕΧΕΙ ΗΔΗ ΚΟΙΝΟΠΟΗΘΕΙ Η ΣΗΜΕΙΩΣΗ ΣΕ ΑΥΤΟ ΤΟ ΧΡΗΣΤΗ ');
    }

    // Insert share record
    $stmt = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, shared_with, permission, created_at)
                          VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$note_id, $owner_id, $shared_user_id, $permission]);

    // Send notification email if requested
    if ($notify && !empty($shared_user_email)) {
        $owner_stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = ?");
        $owner_stmt->execute([$owner_id]);
        $owner = $owner_stmt->fetch();
        
        $owner_name = $owner['username'];
        $owner_email = $owner['email'];
        $note_content = substr(strip_tags($note['content']), 0, 200);
        $canva_id = $note['canva_id'];
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // Your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'swtiriasiwziou26@gmail.com';
            $mail->Password = 'kueg psfx jzhd nljc';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            // Recipients
            $mail->setFrom('swtiriasiwziou26@gmail.com', 'NoteApp');
            $mail->addAddress($shared_user_email, $shared_user_name);
            $mail->addReplyTo($owner_email, $owner_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "Νέα κοινόχρηστη σημείωση από $owner_name";
            
            $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                    <h2 style='color: #2c3e50;'>Έχετε μια νέα κοινόχρηστη σημείωση</h2>
                    <p>Γεια σας $shared_user_name,</p>
                    <p>Ο χρήστης <strong>$owner_name</strong> μοιράστηκε μαζί σας μια σημείωση:</p>
                    <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #3498db; margin: 15px 0;'>
                        $note_content...
                    </div>
                    <p><strong>Δικαιώματα:</strong> " . ($permission == 'view' ? 'Προβολή' : 'Επεξεργασία') . "</p>
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='https://localhost/noteapp/api/canva.notes/view_note.php?id=$note_id&canva_id=$canva_id'
                           style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                           Προβολή Σημείωσης
                        </a>
                    </div>
                    <p style='font-size: 0.9em; color: #7f8c8d;'>
                        Αυτό είναι ένα αυτόματο μήνυμα. Παρακαλώ μην απαντάτε σε αυτό το email.
                    </p>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "Ο χρήστης $owner_name μοιράστηκε μια σημείωση μαζί σας.\n\nΠεριεχόμενο: $note_content...\n\nΔικαιώματα: " . ($permission == 'view' ? 'Προβολή' : 'Επεξεργασία') . "\n\nΠροβολή: https://yourdomain.com/noteapp/view_note.php?id=$note_id&canva_id=$canva_id";
            
            $mail->send();
            error_log("Email sent successfully to $shared_user_email");
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            // Continue even if email fails
        }
    }

    echo json_encode(['success' => true, 'message' => 'Note shared successfully']);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>