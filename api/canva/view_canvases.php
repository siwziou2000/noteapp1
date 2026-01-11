<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_GET['id'])) {
    header('Location: public_canvases.php');
    exit;
}

$canva_id = (int)$_GET['id'];

// Έλεγχος αν ο πίνακας είναι δημόσιος
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar 
        FROM canvases c
        JOIN users u ON c.owner_id = u.user_id
        WHERE c.canva_id = ? AND c.access_type = 'public'
    ");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch();
    
    if (!$canvas) {
        header('Location: public_canvases.php');
        exit;
    }
} catch (PDOException $e) {
    die("Σφάλμα βάσης δεδομένων: " . $e->getMessage());
}

// Ανάκτηση σημειώσεων
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notes 
        WHERE canva_id = ?
        ORDER BY position_x ASC
    ");
    $stmt->execute([$canva_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα φόρτωσης σημειώσεων: " . $e->getMessage());
}

// Ανάκτηση πολυμέσων
try {
    $stmt = $pdo->prepare("
        SELECT * FROM media 
        WHERE canva_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$canva_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $media = [];
}

// Ορισμός διαδρομών για avatar
$avatarPath = '/noteapp/uploads/avatars/';
$defaultAvatar = '/noteapp/images/default-avatar.png';
$userAvatar = !empty($canvas['avatar']) ? $avatarPath . htmlspecialchars($canvas['avatar']) : $defaultAvatar;
if (!empty($canvas['avatar']) && !file_exists($_SERVER['DOCUMENT_ROOT'] . $avatarPath . $canvas['avatar'])) {
    $userAvatar = $defaultAvatar;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    


    <title><?= htmlspecialchars($canvas['name']) ?> - Έξυπνες Σημειώσεις</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .note-container {
            position: absolute;
            width: 300px;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: #fff9c4;
            cursor: move !important;
            pointer-events: auto !important;
            z-index: 1000;
        }
        .note-container.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        .rich-note {
            position: absolute;
            width: 350px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: move;
            z-index: 1000;
        }
        .rich-note.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        .media-item {
            position: absolute;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: move;
            max-width: 300px;
            z-index: 1000;
        }
        .media-item.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        /* ΕΝΕΡΓΟΠΟΙΗΣΗ LINKS ΜΟΝΟ ΓΙΑ ΚΑΤΕΒΑΣΜΑ */
        .media-item a,
        .rich-note a,
        .note-container a {
            pointer-events: auto;
        }
        .owner-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .avatar-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }
        .canvas-container {
            width: 100%;
            height: 70vh;
            position: relative;
            overflow: auto;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            margin: 20px 0;
            min-height: 500px;
        }
        .media-item img,
        .media-item video {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
        .media-item .card,
        .rich-note .card,
        .note-container .card {
            border: none;
            margin: 0;
        }
        .rich-note-content {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        .rich-note-content h1,
        .rich-note-content h2,
        .rich-note-content h3 {
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .rich-note-content ul,
        .rich-note-content ol {
            padding-left: 1.5rem;
        }
        .rich-note-content blockquote {
            border-left: 4px solid #dee2e6;
            padding-left: 1rem;
            margin-left: 0;
            color: #6c757d;
        }
        .rich-note-content table {
            width: 100%;
            border-collapse: collapse;
        }
        .rich-note-content table, 
        .rich-note-content th, 
        .rich-note-content td {
            border: 1px solid #dee2e6;
        }
        .rich-note-content th,
        .rich-note-content td {
            padding: 0.5rem;
        }
        .save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 10000;
            display: none;
        }
    </style>
</head>
<body>
    <div class="save-indicator" id="saveIndicator">
        <i class="bi bi-check-circle"></i> Η θέση αποθηκεύτηκε!
    </div>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($canvas['name']) ?></h1>
                <div class="owner-info">
                    <img src="<?= $userAvatar ?>" 
                         class="avatar-img" 
                         alt="<?= htmlspecialchars($canvas['username']) ?>">
                    <span>Δημιουργός: <?= htmlspecialchars($canvas['username']) ?></span>
                </div>
            </div>
            <a href="public_canvases.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Πίσω
            </a>
        </div>

        <div class="canvas-container" id="notesBoard">
            <!-- Canvas content will be loaded here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>



        document.addEventListener('DOMContentLoaded', function() {
            displayCanvasContent();
            initDragAndDrop();
        });

        function displayCanvasContent() {
            const canvas = document.getElementById('notesBoard');
            if (!canvas) {
                console.error('Canvas container not found');
                return;
            }

            // Clear canvas
            canvas.innerHTML = '';

            // Add notes from PHP
            <?php foreach($notes as $note): ?>
                try {
                    const noteElement = createNoteElement(<?= json_encode($note) ?>);
                    canvas.appendChild(noteElement);
                } catch (error) {
                    console.error('Error creating note element:', error);
                }
            <?php endforeach; ?>

            // Add media from PHP
            <?php foreach($media as $mediaItem): ?>
                try {
                    const mediaElement = createMediaElement(<?= json_encode($mediaItem) ?>);
                    canvas.appendChild(mediaElement);
                } catch (error) {
                    console.error('Error creating media element:', error);
                }
            <?php endforeach; ?>

            // Show message if no content
            if (canvas.children.length === 0) {
                const message = document.createElement('div');
                message.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #6c757d;';
                message.innerHTML = 'Δεν υπάρχουν σημειώσεις ή πολυμέσα σε αυτόν τον πίνακα';
                canvas.appendChild(message);
            }
        }

        function createNoteElement(note) {
    const div = document.createElement('div');
    div.className = 'note-container draggable';
    div.style.left = (note.position_x || 50) + 'px';
    div.style.top = (note.position_y || 50) + 'px';
    div.style.backgroundColor = note.color || '#fff9c4';
    div.setAttribute('data-note-id', note.note_id || note.id);
    div.setAttribute('data-type', 'note');
    
    // Έλεγχος αν η σημείωση έχει HTML (Rich Note)
    const hasHTML = note.content && /<[a-z][\s\S]*>/i.test(note.content);
    
    // Διαμόρφωση ημερομηνίας (αν υπάρχει στη βάση)
    const dateStr = note.created_at ? new Date(note.created_at).toLocaleString('el-GR') : 'Άγνωστη ημ/νία';
    
    // Διαμόρφωση Σχολίων (αν το πεδίο στη βάση σου λέγεται 'comments')
    const commentsSection = note.comments ? `
        <div class="mt-2 pt-2 border-top border-dark-subtle" style="font-size: 0.85rem;">
            <i class="bi bi-chat-left-text-fill me-1"></i> <strong>Σχόλια:</strong>
            <p class="mb-0 fst-italic">${escapeHtml(note.comments)}</p>
        </div>` : '';

    if (hasHTML) {
        div.innerHTML = `
            <div class="note-content">
                <div class="rich-note-content">
                    ${note.content}
                </div>
                ${commentsSection}
                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <i class="bi bi-clock"></i> ${dateStr}
                    </small>
                    <span class="badge bg-primary" style="font-size: 0.65rem;">Rich Note</span>
                </div>
            </div>
        `;
    } else {
        div.innerHTML = `
            <div class="note-content">
                <p class="mb-2" style="white-space: pre-wrap;">${escapeHtml(note.content || '')}</p>
                ${commentsSection}
                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <i class="bi bi-clock"></i> ${dateStr}
                    </small>
                    <span class="badge bg-secondary" style="font-size: 0.65rem;">Simple Note</span>
                </div>
            </div>
        `;
    }
    
    return div;
}
        function createMediaElement(media) {
            const div = document.createElement('div');
            
            // Check if this is a rich note
            const isRichNote = media.type === 'rich_note' || 
                              (media.data && media.data.includes('<') && 
                               (media.data.includes('<p>') || 
                                media.data.includes('<div') || 
                                media.data.includes('<h1') || 
                                media.data.includes('<ul>')));

            if (isRichNote) {
                div.className = 'rich-note draggable';
                div.style.left = (media.position_x || 100) + 'px';
                div.style.top = (media.position_y || 100) + 'px';
                div.setAttribute('data-media-id', media.id);
                div.setAttribute('data-type', 'rich_note');

                div.innerHTML = `
                    <div class="card shadow border-0">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-sticky-fill text-primary"></i> Πλούσια Σημείωση</h6>
                            <span class="badge bg-primary">Rich</span>
                        </div>
                        <div class="rich-note-content">
                            ${media.data || 'Δεν υπάρχει περιεχόμενο'}
                        </div>
                        <div class="card-footer py-2">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> 
                                ${new Date(media.created_at).toLocaleString('el-GR')}
                            </small>
                        </div>
                    </div>
                `;
            } else {
                div.className = 'media-item draggable';
                div.style.left = (media.position_x || 100) + 'px';
                div.style.top = (media.position_y || 100) + 'px';
                div.setAttribute('data-media-id', media.id);
                div.setAttribute('data-type', media.type);

                let content = '';
                const mediaId = media.id;
                
                switch (media.type) {
                    case 'image':
                        content = `
                            <div class="card shadow border-0">
                                <img src="${escapeHtml(media.data || '')}" 
                                     alt="${escapeHtml(media.original_filename || 'Εικόνα')}" 
                                     class="card-img-top img-fluid rounded"
                                     style="max-height: 200px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <p class="card-text small text-truncate mb-1">${escapeHtml(media.original_filename || 'Εικόνα')}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Εικόνα</small>
                                        <a href="/noteapp/api/canva/download.php?id=${mediaId}" 
                                           class="btn btn-sm btn-outline-primary" title="Κατέβασμα">
                                           <i class="bi bi-download me-1"></i>Κατέβασμα
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'video':
                        // Check if it's YouTube URL
                        if (media.data.includes('youtube.com') || media.data.includes('youtu.be')) {
                            const videoId = extractYouTubeId(media.data);
                            if (videoId) {
                                content = `
                                    <div class="card shadow border-0">
                                        <div class="ratio ratio-16x9">
                                            <iframe src="https://www.youtube.com/embed/${videoId}" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen
                                                    class="rounded-top"></iframe>
                                        </div>
                                        <div class="card-body p-2">
                                            <p class="card-text small text-truncate mb-1">
                                                <i class="bi bi-youtube text-danger me-1"></i>
                                                ${escapeHtml(media.original_filename || 'YouTube Video')}
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">YouTube</small>
                                                <a href="${escapeHtml(media.data)}" target="_blank" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-box-arrow-up-right"></i> YouTube
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            } else {
                                content = createFallbackMediaContent(media, 'YouTube Video');
                            }
                        } else {
                            // Local video file - FIXED PATH
                            const videoPath = media.data.startsWith('/') ? media.data : '/noteapp' + media.data;
                            content = `
                                <div class="card shadow border-0">
                                    <video controls class="card-img-top w-100" style="max-height: 200px; border-radius: 0.375rem 0.375rem 0 0;">
                                        <source src="${escapeHtml(videoPath)}" type="video/mp4">
                                        Το βίντεο δεν υποστηρίζεται από τον browser.
                                    </video>
                                    <div class="card-body p-2">
                                        <p class="card-text small text-truncate mb-1">${escapeHtml(media.original_filename || 'Βίντεο')}</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Βίντεο</small>
                                            <a href="/noteapp/api/canva/download.php?id=${mediaId}" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download me-1"></i>Κατέβασμα
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        break;
                        
                    case 'file':
                        const icon = getFileIcon(media.original_filename);
                        content = `
                            <div class="card shadow border-0 text-center">
                                <div class="card-body p-3">
                                    <i class="bi ${icon} fs-1 text-primary"></i>
                                    <p class="card-text small text-truncate mt-2">${escapeHtml(media.original_filename || 'Αρχείο')}</p>
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="/noteapp/api/canva/download.php?id=${mediaId}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-download me-1"></i>Κατέβασμα
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'text':
                        content = `
                            <div class="card shadow border-0">
                                <div class="card-body p-3">
                                    <p class="card-text small" style="white-space: pre-wrap;">${escapeHtml(media.data || '')}</p>
                                    <small class="text-muted">Κείμενο</small>
                                </div>
                            </div>
                        `;
                        break;
                        
                    default:
                        content = createFallbackMediaContent(media, 'Άγνωστος τύπος');
                }

                div.innerHTML = content;
            }
            
            return div;
        }

        function createFallbackMediaContent(media, typeText) {
            const mediaId = media.id;
            return `
                <div class="card shadow border-0">
                    <div class="card-body">
                        <div class="alert alert-warning mb-2">
                            ${escapeHtml(typeText)}: ${escapeHtml(media.type || '')}
                        </div>
                        <p class="small mb-1">${escapeHtml(media.original_filename || 'Χωρίς όνομα')}</p>
                        <a href="/noteapp/api/canva/download.php?id=${mediaId}" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Κατέβασμα
                        </a>
                    </div>
                </div>
            `;
        }

        function initDragAndDrop() {
            const canvas = document.getElementById('notesBoard');
            let draggedElement = null;
            let offsetX = 0;
            let offsetY = 0;

            canvas.addEventListener('mousedown', function(e) {
                const draggableItem = e.target.closest('.media-item, .rich-note, .note-container');
                if (draggableItem) {
                    draggedElement = draggableItem;
                    const rect = draggableItem.getBoundingClientRect();
                    offsetX = e.clientX - rect.left;
                    offsetY = e.clientY - rect.top;
                    
                    draggedElement.classList.add('dragging');
                    e.preventDefault();
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (draggedElement) {
                    const canvasRect = canvas.getBoundingClientRect();
                    const x = e.clientX - canvasRect.left - offsetX;
                    const y = e.clientY - canvasRect.top - offsetY;
                    
                    // Keep within canvas bounds
                    const maxX = canvasRect.width - draggedElement.offsetWidth;
                    const maxY = canvasRect.height - draggedElement.offsetHeight;
                    
                    draggedElement.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
                    draggedElement.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
                }
            });

            document.addEventListener('mouseup', function() {
                if (draggedElement) {
                    draggedElement.classList.remove('dragging');
                    
                    // Αποθήκευση της νέας θέσης
                    savePosition(draggedElement);
                    
                    draggedElement = null;
                }
            });

            // Touch events for mobile
            canvas.addEventListener('touchstart', function(e) {
                const draggableItem = e.target.closest('.media-item, .rich-note, .note-container');
                if (draggableItem) {
                    draggedElement = draggableItem;
                    const touch = e.touches[0];
                    const rect = draggableItem.getBoundingClientRect();
                    offsetX = touch.clientX - rect.left;
                    offsetY = touch.clientY - rect.top;
                    
                    draggedElement.classList.add('dragging');
                    e.preventDefault();
                }
            });

            document.addEventListener('touchmove', function(e) {
                if (draggedElement) {
                    const touch = e.touches[0];
                    const canvasRect = canvas.getBoundingClientRect();
                    const x = touch.clientX - canvasRect.left - offsetX;
                    const y = touch.clientY - canvasRect.top - offsetY;
                    
                    const maxX = canvasRect.width - draggedElement.offsetWidth;
                    const maxY = canvasRect.height - draggedElement.offsetHeight;
                    
                    draggedElement.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
                    draggedElement.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
                    
                    e.preventDefault();
                }
            });

            document.addEventListener('touchend', function() {
                if (draggedElement) {
                    draggedElement.classList.remove('dragging');
                    
                    // Αποθήκευση της νέας θέσης
                    savePosition(draggedElement);
                    
                    draggedElement = null;
                }
            });
        }

        // Συνάρτηση αποθήκευσης θέσης
        async function savePosition(element) {
            try {
                const type = element.getAttribute('data-type');
                const id = element.getAttribute('data-note-id') || element.getAttribute('data-media-id');
                const positionX = parseInt(element.style.left);
                const positionY = parseInt(element.style.top);

                if (!id || !type) {
                    console.error('Missing ID or type for element:', element);
                    return;
                }

                const formData = new FormData();
                formData.append('id', id);
                formData.append('type', type);
                formData.append('position_x', positionX);
                formData.append('position_y', positionY);
                formData.append('canva_id', <?= $canva_id ?>);

                const response = await fetch('public/save_position.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showSaveIndicator();
                } else {
                    console.error('Error saving position:', result.error);
                }
            } catch (error) {
                console.error('Error saving position:', error);
            }
        }

        // Εμφάνιση indicator για αποθήκευση
        function showSaveIndicator() {
            const indicator = document.getElementById('saveIndicator');
            indicator.style.display = 'block';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }

        function extractYouTubeId(url) {
            const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[7].length === 11) ? match[7] : false;
        }

        function getFileIcon(filename) {
            if (!filename) return 'bi-file-earmark';
            
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'bi-file-earmark-pdf text-danger',
                'doc': 'bi-file-earmark-word text-primary',
                'docx': 'bi-file-earmark-word text-primary',
                'xls': 'bi-file-earmark-excel text-success',
                'xlsx': 'bi-file-earmark-excel text-success',
                'ppt': 'bi-file-earmark-ppt text-warning',
                'pptx': 'bi-file-earmark-ppt text-warning',
                'zip': 'bi-file-earmark-zip text-secondary',
                'rar': 'bi-file-earmark-zip text-secondary',
                'jpg': 'bi-file-earmark-image text-info',
                'jpeg': 'bi-file-earmark-image text-info',
                'png': 'bi-file-earmark-image text-info',
                'gif': 'bi-file-earmark-image text-info',
                'txt': 'bi-file-earmark-text text-dark',
                'html': 'bi-file-earmark-code text-warning',
                'htm': 'bi-file-earmark-code text-warning'
            };
            
            return iconMap[ext] || 'bi-file-earmark text-secondary';
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>