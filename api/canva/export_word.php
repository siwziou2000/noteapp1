<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος πρόσβασης
if (!isset($_SESSION['user_id'])) {
    die('Πρέπει να συνδεθείτε!');
}

$user_id = (int)$_SESSION['user_id'];
$canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : null;

if (!$canva_id) {
    die('Λείπει το ID του πίνακα');
}

// Έλεγχος πρόσβασης στον πίνακα
try {
    $stmt = $pdo->prepare("
        SELECT * FROM canvases 
        WHERE canva_id = ? 
        AND (owner_id = ? OR canva_id IN (SELECT canva_id FROM canvas_collaborators WHERE user_id = ?))
    ");
    $stmt->execute([$canva_id, $user_id, $user_id]);
    
    if (!$stmt->fetch()) {
        die('Δεν έχετε δικαίωμα σε αυτόν τον πίνακα.');
    }
} catch (PDOException $e) {
    die('Σφάλμα βάσης δεδομένων: ' . $e->getMessage());
}

// Βοηθητική συνάρτηση για μορφοποίηση μεγέθους αρχείου
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
}

// Συνάρτηση για απόκτηση εικονιδίου αρχείου
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📕',
        'doc' => '📝', 'docx' => '📝',
        'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📽️', 'pptx' => '📽️',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️', 'webp' => '🖼️',
        'mp4' => '🎥', 'avi' => '🎥', 'mov' => '🎥', 'wmv' => '🎥', 'flv' => '🎥', 'webm' => '🎥', 'mkv' => '🎥',
        'mp3' => '🎵', 'wav' => '🎵', 'ogg' => '🎵',
        'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'tar' => '📦', 'gz' => '📦',
        'txt' => '📄', 'md' => '📄',
        'html' => '🌐', 'htm' => '🌐',
        'css' => '🎨',
        'js' => '⚡',
        'php' => '🐘'
    ];
    return $icons[$ext] ?? '📁';
}

// ΒΕΛΤΙΩΜΕΝΗ ΣΥΝΑΡΤΗΣΗ PATH RESOLUTION
function findActualFilePath($filePath) {
    // Βασικά directories που μπορεί να βρίσκονται τα αρχεία
    $possiblePaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp' . $filePath,
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva' . $filePath,
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp/uploads' . $filePath,
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/uploads' . $filePath,
    ];
    
    // Αν το filePath είναι ήδη πλήρες path
    if (file_exists($filePath)) {
        return $filePath;
    }
    
    // Έλεγχος όλων των πιθανών paths
    foreach ($possiblePaths as $fullPath) {
        if (file_exists($fullPath)) {
            return $fullPath;
        }
    }
    
    // Αν δεν βρέθηκε πουθενά, δοκίμασε να βρεις μόνο το filename
    $filename = basename($filePath);
    $uploadDirs = [
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp/uploads/',
        $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/uploads/',
    ];
    
    foreach ($uploadDirs as $uploadDir) {
        $searchPath = $uploadDir . $filename;
        if (file_exists($searchPath)) {
            return $searchPath;
        }
        
        // Έλεγχος για thumbnails
        $thumbPath = $uploadDir . 'thumb_' . $filename;
        if (file_exists($thumbPath)) {
            return $thumbPath;
        }
    }
    
    return null; // Δεν βρέθηκε πουθενά
}

// ΒΟΗΘΗΤΙΚΗ ΣΥΝΑΡΤΗΣΗ ΓΙΑ OPTIMAL IMAGE DIMENSIONS
function calculateOptimalImageSize($originalWidth, $originalHeight, $maxWidth = 500, $maxHeight = 400) {
    $ratio = $originalWidth / $originalHeight;
    
    if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
        if ($ratio > 1) {
            // Landscape
            $width = $maxWidth;
            $height = $maxWidth / $ratio;
        } else {
            // Portrait
            $height = $maxHeight;
            $width = $maxHeight * $ratio;
        }
        
        // Ensure dimensions don't exceed limits
        if ($width > $maxWidth) {
            $width = $maxWidth;
            $height = $maxWidth / $ratio;
        }
        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width = $maxHeight * $ratio;
        }
        
        return ['width' => round($width), 'height' => round($height)];
    }
    
    return ['width' => $originalWidth, 'height' => $originalHeight];
}

// PREMIUM ΣΥΝΑΡΤΗΣΗ ΜΕ ΟΜΟΙΟΜΟΡΦΗ ΕΜΦΑΝΙΣΗ ΕΙΚΟΝΩΝ
function getMediaPreview($filePath, $mediaType, $originalFilename = '', $previewData = null) {
    // Έλεγχος για κενή διαδρομή
    if (empty($filePath)) {
        return "<div style='border: 2px solid #c0392b; border-radius: 12px; padding: 25px; margin: 20px 0; background: linear-gradient(135deg, #fde8e6, #fadbd8); text-align: center; box-shadow: 0 4px 12px rgba(192, 57, 43, 0.1);'>
                    <div style='font-size: 32px; margin-bottom: 10px;'>❌</div>
                    <strong style='color: #c0392b; font-size: 16px;'>Δεν υπάρχει διαδρομή αρχείου</strong>
                </div>";
    }
    
    // ΒΕΛΤΙΩΜΕΝΟ PATH RESOLUTION
    $fullPath = findActualFilePath($filePath);
    
    if (!$fullPath) {
        return "<div style='border: 2px solid #e67e22; border-radius: 12px; padding: 25px; margin: 20px 0; background: linear-gradient(135deg, #fef5e8, #fdebd0); text-align: center; box-shadow: 0 4px 12px rgba(230, 126, 34, 0.1);'>
                    <div style='font-size: 32px; margin-bottom: 10px;'>⚠️</div>
                    <strong style='color: #e67e22; font-size: 16px;'>Το αρχείο δεν βρέθηκε:</strong><br>
                    <span style='color: #7f8c8d; display: block; margin-top: 8px;'>" . htmlspecialchars($originalFilename ?: basename($filePath)) . "</span>
                    <small style='color: #95a5a6; display: block; margin-top: 5px;'>Ψαχνεται: " . htmlspecialchars($filePath) . "</small>
                </div>";
    }
    
    $fileExists = file_exists($fullPath);
    $fileSize = $fileExists ? filesize($fullPath) : 0;
    $fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $filename = $originalFilename ?: basename($filePath);
    $fileIcon = getFileIcon($filename);
    
    // PREMIUM PREVIEW ΓΙΑ ΕΙΚΟΝΕΣ - ΟΜΟΙΟΜΟΡΦΗ ΚΑΙ ΕΠΑΓΓΕΛΜΑΤΙΚΗ
   
        
        // Fallback αν η εικόνα δεν βρέθηκε
        if ($mediaType === 'image') {
    if ($fileExists) {
        $imageInfo = @getimagesize($fullPath);
        if ($imageInfo) {
            // Έλεγχος μεγέθους αρχείου για memory limits
            if ($fileSize > 8 * 1024 * 1024) {
                return "<div style='border: 2px solid #f39c12; border-radius: 8px; padding: 15px; margin: 15px 0; background: #fef9e7; text-align: center;'>
                            <div style='font-size: 24px; margin-bottom: 8px;'>📸</div>
                            <strong style='color: #f39c12; font-size: 14px;'>Εικόνα (Πολύ μεγάλη για προβολή)</strong><br>
                            <span style='color: #7f8c8d; font-size: 12px;'>" . htmlspecialchars($filename) . "</span>
                            <div style='margin-top: 8px; color: #95a5a6; font-size: 11px;'>
                                📏 {$imageInfo[0]} × {$imageInfo[1]} pixels | 💾 " . formatFileSize($fileSize) . "
                            </div>
                        </div>";
            }
            
            try {
                // Απλοποιημένος υπολογισμός διαστάσεων
                $displayWidth = min(250, $imageInfo[0]);
                $displayHeight = min(200, $imageInfo[1]);
                
                // Δημιουργία base64
                $imageData = base64_encode(file_get_contents($fullPath));
                $mimeType = $imageInfo['mime'];
                $base64 = "data:{$mimeType};base64,{$imageData}";
                
                // 🎯 WORD-COMPATIBLE SIMPLE VERSION
                return "<div style='border: 1px solid #ddd; padding: 10px; margin: 15px 0; background: white;'>
                            <!-- Header -->
                            <div style='background: #667eea; padding: 10px; color: white; margin: -10px -10px 10px -10px;'>
                                <strong>🖼️ ΕΙΚΟΝΑ</strong>
                            </div>
                            
                            <!-- Image -->
                            <div style='text-align: center; margin: 10px 0;'>
                                <img src='{$base64}' 
                                     width='{$displayWidth}'
                                     height='{$displayHeight}'
                                     style='border: 1px solid #ccc;'
                                     alt='" . htmlspecialchars($filename) . "'>
                            </div>
                            
                            <!-- Info -->
                            <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 11px;'>
                                <tr>
                                    <td width='50%' style='border-right: 1px solid #eee;'>
                                        <strong>📄 Πληροφορίες:</strong><br>
                                        Όνομα: " . htmlspecialchars($filename) . "<br>
                                        Μορφή: " . strtoupper($fileExtension) . "<br>
                                        Μέγεθος: " . formatFileSize($fileSize) . "
                                    </td>
                                    <td width='50%'>
                                        <strong>📏 Διαστάσεις:</strong><br>
                                        Πλάτος: {$imageInfo[0]} px<br>
                                        Ύψος: {$imageInfo[1]} px<br>
                                        Αναλογία: " . round($imageInfo[0]/$imageInfo[1], 2) . ":1
                                    </td>
                                </tr>
                            </table>
                        </div>";
            } catch (Exception $e) {
                return "<div style='border: 2px solid #6f42c1; padding: 15px; margin: 15px 0; background: #f8f9fa; text-align: center;'>
                            <div style='font-size: 24px;'>🖼️</div>
                            <strong>" . htmlspecialchars($filename) . "</strong><br>
                            <div style='font-size: 11px; color: #666;'>
                                📏 {$imageInfo[0]} × {$imageInfo[1]} px | 💾 " . formatFileSize($fileSize) . "
                            </div>
                        </div>";
            }
        }
    }
    
    return "<div style='border: 2px solid #dc3545; padding: 15px; margin: 15px 0; background: #f8d7da; text-align: center;'>
                <div style='font-size: 24px;'>❌</div>
                <strong>Η εικόνα δεν βρέθηκε</strong><br>
                " . htmlspecialchars($filename) . "
            </div>";
}
    // PREMIUM STYLING ΓΙΑ ΒΙΝΤΕΟ
    if ($mediaType === 'video') {
        return "<div style='border: 1px solid #e0e0e0; border-radius: 16px; padding: 0; margin: 25px 0; background: white; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; page-break-inside: avoid;'>
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 20px; color: white; display: flex; align-items: center; gap: 12px;'>
                        <span style='font-size: 20px;'>🎥</span>
                        <div>
                            <strong style='font-size: 16px; display: block;'>ΒΙΝΤΕΟ</strong>
                            <small style='opacity: 0.9; font-size: 12px;'>Αρχείο Πολυμέσου</small>
                        </div>
                    </div>
                    
                    <!-- Video Placeholder -->
                    <div style='padding: 40px 25px; background: linear-gradient(135deg, #fdf2f2, #fadbd8); text-align: center; border-bottom: 1px solid #f0f0f0;'>
                        <div style='display: inline-block; padding: 25px; background: white; border-radius: 50%; box-shadow: 0 6px 20px rgba(231, 76, 60, 0.2); margin-bottom: 15px;'>
                            <span style='font-size: 48px;'>🎬</span>
                        </div>
                        <div>
                            <strong style='color: #c0392b; font-size: 16px; display: block;'>" . htmlspecialchars($filename) . "</strong>
                            <em style='color: #7f8c8d; font-size: 13px;'>Η προβολή βίντεο δεν είναι διαθέσιμη σε Word export</em>
                        </div>
                    </div>
                    
                    <!-- Info Panel -->
                    <div style='padding: 20px; background: white;'>
                        <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: start;'>
                            <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 10px; border-left: 4px solid #e74c3c;'>
                                <strong style='color: #495057; font-size: 13px; display: block; margin-bottom: 8px;'>🎬 ΠΛΗΡΟΦΟΡΙΕΣ ΒΙΝΤΕΟ</strong>
                                <div style='font-size: 12px; color: #6c757d; line-height: 1.6;'>
                                    <div><strong>Μέγεθος:</strong> " . formatFileSize($fileSize) . "</div>
                                    <div><strong>Μορφή:</strong> " . strtoupper($fileExtension) . "</div>
                                    <div><strong>Κατάσταση:</strong> <span style='color: #28a745;'>✓ Διαθέσιμο</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>";
    }
    
    // ΓΙΑ ΑΡΧΕΙΑ ΚΕΙΜΕΝΟΥ
    if ($mediaType === 'text' || $mediaType === 'file') {
        if (in_array($fileExtension, ['txt', 'md', 'csv', 'html', 'htm', 'php', 'js', 'css', 'json', 'xml']) && $fileExists) {
            $content = file_get_contents($fullPath);
            $textContent = ($content !== false && strlen($content) > 0) 
                ? htmlspecialchars(substr($content, 0, 2000)) 
                : "<em>Κενό αρχείο ή σφάλμα ανάγνωσης</em>";
            
            return "<div style='border: 1px solid #e0e0e0; border-radius: 16px; padding: 0; margin: 25px 0; background: white; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; page-break-inside: avoid;'>
                        <!-- Header -->
                        <div style='background: linear-gradient(135deg, #27ae60 0%, #219653 100%); padding: 20px; color: white; display: flex; align-items: center; gap: 12px;'>
                            <span style='font-size: 20px;'>📝</span>
                            <div>
                                <strong style='font-size: 16px; display: block;'>ΑΡΧΕΙΟ ΚΕΙΜΕΝΟΥ</strong>
                                <small style='opacity: 0.9; font-size: 12px;'>Προβολή Περιεχομένου</small>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div style='padding: 25px; background: white;'>
                            <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 10px; border-left: 4px solid #27ae60; margin-bottom: 20px;'>
                                <strong style='color: #495057; font-size: 13px; display: block; margin-bottom: 8px;'>📄 ΠΛΗΡΟΦΟΡΙΕΣ ΑΡΧΕΙΟΥ</strong>
                                <div style='font-size: 12px; color: #6c757d; line-height: 1.6;'>
                                    <div><strong>Όνομα:</strong> " . htmlspecialchars($filename) . "</div>
                                    <div><strong>Μορφή:</strong> " . strtoupper($fileExtension) . "</div>
                                    <div><strong>Μέγεθος:</strong> " . formatFileSize($fileSize) . "</div>
                                </div>
                            </div>
                            
                            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;'>
                                <strong style='color: #495057; font-size: 13px; display: block; margin-bottom: 10px;'>Περιεχόμενο:</strong>
                                <div style='font-family: \"Courier New\", monospace; font-size: 11px; background: white; padding: 12px; border: 1px solid #dee2e6; border-radius: 6px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; line-height: 1.4;'>
                                    " . $textContent . "
                                </div>
                            </div>
                        </div>
                    </div>";
        } else {
            // Για άλλα αρχεία (PDF, Word, Excel, κλπ)
            $fileTypeInfo = "";
            switch($fileExtension) {
                case 'pdf': $fileTypeInfo = "📕 Αρχείο PDF (Εγγράφου)"; break;
                case 'doc': case 'docx': $fileTypeInfo = "📝 Αρχείο Word (Εγγράφου)"; break;
                case 'xls': case 'xlsx': $fileTypeInfo = "📊 Αρχείο Excel (Φύλλου Εργασίας)"; break;
                case 'ppt': case 'pptx': $fileTypeInfo = "📽️ Αρχείο PowerPoint (Παρουσίασης)"; break;
                case 'zip': case 'rar': case '7z': $fileTypeInfo = "📦 Συμπιεσμένο Αρχείο"; break;
                default: $fileTypeInfo = "📁 Αρχείο Δεδομένων";
            }
            
            return "<div style='border: 1px solid #e0e0e0; border-radius: 16px; padding: 0; margin: 25px 0; background: white; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; page-break-inside: avoid;'>
                        <!-- Header -->
                        <div style='background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 20px; color: white; display: flex; align-items: center; gap: 12px;'>
                            <span style='font-size: 20px;'>" . $fileIcon . "</span>
                            <div>
                                <strong style='font-size: 16px; display: block;'>ΑΡΧΕΙΟ</strong>
                                <small style='opacity: 0.9; font-size: 12px;'>" . $fileTypeInfo . "</small>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div style='padding: 40px 25px; background: linear-gradient(135deg, #fef5e8, #fdebd0); text-align: center; border-bottom: 1px solid #f0f0f0;'>
                            <div style='font-size: 64px; margin-bottom: 15px;'>" . $fileIcon . "</div>
                            <strong style='color: #e67e22; font-size: 18px; display: block;'>" . htmlspecialchars($filename) . "</strong>
                            <em style='color: #7f8c8d;'>" . $fileTypeInfo . "</em>
                        </div>
                        
                        <!-- Info Panel -->
                        <div style='padding: 20px; background: white;'>
                            <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 10px; border-left: 4px solid #f39c12;'>
                                <strong style='color: #495057; font-size: 13px; display: block; margin-bottom: 8px;'>📋 ΠΛΗΡΟΦΟΡΙΕΣ ΑΡΧΕΙΟΥ</strong>
                                <div style='font-size: 12px; color: #6c757d; line-height: 1.6;'>
                                    <div><strong>Τύπος:</strong> " . $fileTypeInfo . "</div>
                                    <div><strong>Μέγεθος:</strong> " . formatFileSize($fileSize) . "</div>
                                    <div><strong>Μορφή:</strong> " . strtoupper($fileExtension) . "</div>
                                    <div><strong>Κατάσταση:</strong> <span style='color: #28a745;'>✓ Διαθέσιμο</span></div>
                                </div>
                            </div>
                        </div>
                    </div>";
        }
    }
    
    // Άγνωστος τύπος
    return "<div style='border: 2px solid #6c757d; border-radius: 12px; padding: 25px; margin: 20px 0; background: linear-gradient(135deg, #f8f9fa, #e9ecef); text-align: center; box-shadow: 0 4px 12px rgba(108, 117, 125, 0.1);'>
                <div style='font-size: 32px; margin-bottom: 10px;'>❓</div>
                <strong style='color: #6c757d; font-size: 16px;'>Άγνωστος τύπος πολυμέσου</strong><br>
                <span style='color: #adb5bd; display: block; margin-top: 8px;'>" . $mediaType . "</span>
            </div>";
}

// Ανάκτηση δεδομένων
try {
    // Πληροφορίες πίνακα
    $stmt = $pdo->prepare("SELECT name FROM canvases WHERE canva_id = ?");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch();
    $canvas_name = $canvas['name'] ?? 'Unnamed Canvas';

    // Σημειώσεις
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.username as owner_name,
               g.group_name
        FROM notes n 
        LEFT JOIN users u ON n.owner_id = u.user_id 
        LEFT JOIN groups g ON n.group_id = g.group_id 
        WHERE n.canva_id = ? 
        ORDER BY n.position_x ASC, n.created_at DESC
    ");
    $stmt->execute([$canva_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Πολυμέσα
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.username as owner_name,
               g.group_name
        FROM media m 
        LEFT JOIN users u ON m.owner_id = u.user_id 
        LEFT JOIN groups g ON m.group_id = g.group_id 
        WHERE m.canva_id = ? 
        AND (m.data IS NOT NULL AND m.data != '')
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$canva_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Σφάλμα ανάκτησης δεδομένων: ' . $e->getMessage());
}

// Δημιουργία Word document
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"" . $canvas_name . "_export.doc\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($canvas_name); ?> - Εξαγωγή</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 20px; line-height: 1.6; color: #333; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px double #3498db; }
        h1 { color: #2c3e50; margin-bottom: 5px; font-size: 24px; }
        h2 { color: #34495e; margin-top: 40px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #bdc3c7; font-size: 20px; }
        h3 { color: #2c3e50; margin-top: 30px; margin-bottom: 15px; font-size: 16px; }
        .section { margin-bottom: 30px; }
        .note-item { margin-bottom: 25px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #fafafa; page-break-inside: avoid; }
        .note-content { margin: 15px 0; padding: 15px; background: white; border: 1px solid #eee; border-radius: 5px; min-height: 50px; }
        .note-meta, .media-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; padding: 10px; background: #ecf0f1; border-radius: 5px; font-size: 14px; }
        .meta-item { display: flex; justify-content: space-between; border-bottom: 1px dotted #bdc3c7; padding: 2px 0; }
        .meta-label { font-weight: bold; color: #2c3e50; }
        .meta-value { color: #34495e; }
        .tag { display: inline-block; background: #e74c3c; color: white; padding: 3px 10px; border-radius: 15px; font-size: 12px; margin: 2px; }
        .empty-message { text-align: center; color: #7f8c8d; font-style: italic; padding: 40px; border: 2px dashed #bdc3c7; border-radius: 8px; margin: 20px 0; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; color: #7f8c8d; font-size: 12px; }
        .media-content { margin: 15px 0; }
        .media-comment { margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-style: italic; }
        @media print { .note-item { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($canvas_name); ?></h1>
        <p><strong>Εξαγωγή στις:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        <p><strong>Σύνολο Σημειώσεων:</strong> <?php echo count($notes); ?> | <strong>Σύνολο Πολυμέσων:</strong> <?php echo count($media); ?></p>
    </div>

    <div class="section">
        <h2>📝 Σημειώσεις (<?php echo count($notes); ?>)</h2>
        
        <?php if (count($notes) > 0): ?>
            <?php foreach ($notes as $index => $note): ?>
            <div class="note-item">
                <h3>Σημείωση #<?php echo $index + 1; ?></h3>
                
                <div class="note-content">
                    <?php echo $note['content']; ?>
                </div>
                
                <div class="note-meta">
                    <?php if (!empty($note['color'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Χρώμα:</span>
                        <span class="meta-value">
                            <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo $note['color']; ?>; border: 1px solid #ccc; border-radius: 3px; margin-right: 5px; vertical-align: middle;"></span>
                            <?php echo $note['color']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($note['tag'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Ετικέτα:</span>
                        <span class="meta-value">
                            <span style="background: #e74c3c; color: white; padding: 3px 10px; border-radius: 15px; font-size: 12px;"><?php echo htmlspecialchars($note['tag']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <span class="meta-label">Δημιουργήθηκε:</span>
                        <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?></span>
                    </div>
                    
                    <?php if (!empty($note['owner_name'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Δημιουργός:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($note['owner_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-message">
                <p>Δεν υπάρχουν σημειώσεις σε αυτόν τον πίνακα.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🎬 Πολυμέσα (<?php echo count($media); ?>)</h2>
        
        <?php if (count($media) > 0): ?>
            <?php foreach ($media as $index => $item): ?>
            <div class="media-item">
                <h3>Πολυμέσο #<?php echo $index + 1; ?></h3>
                
                <div class="media-meta">
                    <div class="meta-item">
                        <span class="meta-label">Τύπος:</span>
                        <span class="meta-value">
                            <?php 
                            $type_icons = [
                                'image' => '🖼️',
                                'video' => '🎥', 
                                'file' => '📄',
                                'text' => '📝'
                            ];
                            $icon = $type_icons[$item['type']] ?? '📁';
                            echo $icon . ' ' . htmlspecialchars($item['type']);
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($item['original_filename'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Όνομα αρχείου:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($item['original_filename']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <span class="meta-label">Προστέθηκε:</span>
                        <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></span>
                    </div>
                    
                    <?php if (!empty($item['owner_name'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Δημιουργός:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($item['owner_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- PREMIUM PREVIEW ΜΕ ΟΜΟΙΟΜΟΡΦΕΣ ΕΙΚΟΝΕΣ -->
                <div class="media-content">
                    <?php 
                    $preview = getMediaPreview(
                        $item['data'], 
                        $item['type'], 
                        $item['original_filename'] ?? '', 
                        $item['preview_data'] ?? null
                    );
                    echo $preview;
                    ?>
                </div>
                
                <?php if (!empty($item['comment'])): ?>
                <div class="media-comment">
                    <strong>Σχόλιο:</strong> <?php echo htmlspecialchars($item['comment']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-message">
                <p>Δεν υπάρχουν πολυμέσα σε αυτόν τον πίνακα.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Εξαγωγή από το Σύστημα Σημειώσεων | <?php echo date('d/m/Y H:i'); ?></p>
        <p>Σύνολο: <?php echo count($notes); ?> σημειώσεις, <?php echo count($media); ?> πολυμέσα</p>
    </div>
</body>
</html>