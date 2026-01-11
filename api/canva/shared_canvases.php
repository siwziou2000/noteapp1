<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Ορισμός μεταβλητών για avatar
$avatarPath = '/noteapp/uploads/avatars/';
$defaultAvatar = '/noteapp/images/default-avatar.png';

// Αν δεν υπάρχει username στο session, πάρτο από τη βάση
if (!isset($_SESSION['username']) && isset($_SESSION['user_id'])) {
    $user_stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE user_id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch();
    
    if ($user_data) {
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['avatar'] = $user_data['avatar'] ?? '';
    } else {
        $_SESSION['username'] = 'Χρήστης';
        $_SESSION['avatar'] = '';
    }
}

// Toast Notifications System
if (isset($_SESSION['success'])) {
    echo '
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    ' . $_SESSION['success'] . '
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    ';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ' . $_SESSION['error'] . '
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    ';
    unset($_SESSION['error']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$currentUsername = $_SESSION['username'] ?? 'Χρήστης';
$currentUserAvatar = !empty($_SESSION['avatar']) ? $avatarPath . $_SESSION['avatar'] : $defaultAvatar;

// 1. Πίνακες που σας έχουν προσκαλέσει (PENDING & ACCEPTED)
// 1. Πίνακες που σας έχουν προσκαλέσει (PENDING & ACCEPTED)
// 1. Πίνακες που σας έχουν προσκαλέσει (PENDING & ACCEPTED)
try {
    $stmt_shared = $pdo->prepare("
        SELECT c.*, 
               u.username as owner_name, 
               u.avatar as owner_avatar,
               uc.username as collaborator_username,
               uc.avatar as collaborator_avatar,
               cc.permission, 
               cc.status, 
               cc.invited_at, 
               cc.accepted_at
        FROM canvas_collaborators cc 
        JOIN canvases c ON cc.canva_id = c.canva_id 
        JOIN users u ON c.owner_id = u.user_id
        JOIN users uc ON cc.user_id = uc.user_id
        WHERE cc.user_id = ?
        AND cc.status IN ('pending', 'accepted')
        ORDER BY 
            CASE WHEN cc.status = 'pending' THEN 0 ELSE 1 END,
            cc.invited_at DESC
    ");
    $stmt_shared->execute([$user_id]);
    $shared_canvases = $stmt_shared->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shared_canvases = [];
    error_log("Database error in shared canvases: " . $e->getMessage());
}
// 2. ΔΗΜΟΣΙΟΙ πίνακες που ΔΕΝ είστε ήδη collaborator
try {
    $stmt_public = $pdo->prepare("
        SELECT c.*, u.username as owner_name, 'view' as permission
        FROM canvases c 
        JOIN users u ON c.owner_id = u.user_id
        WHERE c.access_type = 'public'
        AND c.canva_id NOT IN (
            SELECT canva_id FROM canvas_collaborators WHERE user_id = ? AND status = 'accepted'
        )
        AND c.owner_id != ?
        ORDER BY c.created_at DESC
    ");
    $stmt_public->execute([$user_id, $user_id]);
    $public_canvases = $stmt_public->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $public_canvases = [];
    error_log("Database error in public canvases: " . $e->getMessage());
}

// Υπολογισμός pending invitations
$pending_invitations = 0;
$pending_canvases = [];
$accepted_canvases = [];

foreach ($shared_canvases as $canvas) {
    if ($canvas['status'] == 'pending') {
        $pending_invitations++;
        $pending_canvases[] = $canvas;
    } else {
        $accepted_canvases[] = $canvas;
    }
}


?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κοινοχρηστοι Πίνακες - Έξυπνες Σημειώσεις</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/darkmode1.css">
    <style>
        .canvas-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #dee2e6;
        }
        .canvas-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .pending-card {
            border-left: 4px solid #dc3545;
            background: linear-gradient(45deg, #fff, #fff5f5);
        }
        .accepted-card {
            border-left: 4px solid #ffc107;
        }
        .public-card {
            border-left: 4px solid #198754;
        }
        .owner-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        .avatar-overlap {
            margin-left: -10px;
            border: 2px solid white;
        }
        .section-title {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .access-badge {
            font-size: 0.7em;
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .empty-state {
            opacity: 0.7;
        }
        .toast-container {
            z-index: 1090;
        }
        .toast {
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header με Refresh Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Κοινοχρηστοι Πίνακες 
                    <span class="badge bg-primary" id="totalCanvasesBadge">
                        <?= count($shared_canvases) + count($public_canvases) ?>
                    </span>
                    <?php if ($pending_invitations > 0): ?>
                        <span class="badge bg-danger pulse-animation" id="pendingBadge">
                            <?= $pending_invitations ?> Νέα
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted">Πίνακες που σας προσκάλεσαν & Δημόσιοι πίνακες</p>
            </div>
            <div>
                <button id="refreshBtn" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-clockwise"></i> Ανανέωση
                </button>
                <a href="home.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Πίσω
                </a>
            </div>
        </div>

        <!-- Notification Area -->
        <div id="notificationArea"></div>

        <!-- Pending Invitations -->
      <!-- Pending Invitations -->
<!-- Pending Invitations -->
<?php if (count($pending_canvases) > 0): ?>
    <div class="mb-5">
        <h3 class="section-title">
            <i class="bi bi-bell-fill text-danger"></i>
            Νέες Προσκλήσεις
            <span class="badge bg-danger"><?= count($pending_canvases) ?></span>
        </h3>
        <div class="row">
            <?php foreach ($pending_canvases as $canvas): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card canvas-card pending-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title text-dark">
                                    <?= htmlspecialchars($canvas['name']) ?>
                                </h5>
                                <div class="d-flex flex-column align-items-end">
                                    <span class="badge bg-<?= $canvas['permission'] === 'edit' ? 'warning' : 'secondary' ?> mb-1">
                                        <?= $canvas['permission'] === 'edit' ? 'Επεξεργασία' : 'Προβολή' ?>
                                    </span>
                                    <span class="badge access-badge bg-danger">
                                        Νέα Πρόσκληση
                                    </span>
                                </div>
                            </div>
                            
                            <p class="card-text text-muted small">
                                <i class="bi bi-folder"></i>
                                <?= htmlspecialchars($canvas['canva_category'] ?? 'Γενική') ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <?php
                                    $ownerAvatar = !empty($canvas['owner_avatar']) ? $avatarPath . $canvas['owner_avatar'] : $defaultAvatar;
                                    $collaboratorAvatar = !empty($canvas['collaborator_avatar']) ? $avatarPath . $canvas['collaborator_avatar'] : $defaultAvatar;
                                    ?>
                                    <div class="d-flex align-items-center">
                                        <!-- Owner Avatar -->
                                        <img src="<?= $ownerAvatar ?>" 
                                             class="owner-avatar" 
                                             alt="<?= htmlspecialchars($canvas['owner_name']) ?>"
                                             onerror="this.src='<?= $defaultAvatar ?>'"
                                             title="Δημιουργός: <?= htmlspecialchars($canvas['owner_name']) ?>">
                                        
                                        <!-- Collaborator Avatar -->
                                        <img src="<?= $collaboratorAvatar ?>" 
                                             class="owner-avatar avatar-overlap" 
                                             alt="<?= htmlspecialchars($canvas['collaborator_username']) ?>"
                                             onerror="this.src='<?= $defaultAvatar ?>'"
                                             title="Συνεργάτης: <?= htmlspecialchars($canvas['collaborator_username']) ?>">
                                    </div>
                                    <small class="text-muted ms-2">
                                        <strong><?= htmlspecialchars($canvas['collaborator_username']) ?></strong>
                                        <span class="text-muted"> & <?= htmlspecialchars($canvas['owner_name']) ?></span>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($canvas['invited_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-2">
                                    <a href="/noteapp/api/canva/accept_invitation.php?canva_id=<?= $canvas['canva_id'] ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-check-circle"></i> Αποδοχή
                                    </a>
                                    <a href="/noteapp/api/canva/reject_invitation.php?canva_id=<?= $canvas['canva_id'] ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Είστε σίγουρος ότι θέλετε να απορρίψετε αυτή την πρόσκληση;')">
                                        <i class="bi bi-x-circle"></i> Απόρριψη
                                    </a>
                                </div>
                                <span class="badge bg-danger">
                                    <i class="bi bi-clock-history"></i> Αναμονή
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

        <!-- Accepted Invitations -->
        <?php if (count($accepted_canvases) > 0): ?>
            <div class="mb-5">
                <h3 class="section-title">
                    <i class="bi bi-people-fill text-warning"></i>
                    Πινακες που εχετε αποδεκτει
                </h3>
                <div class="row">
                    <?php foreach ($accepted_canvases as $canvas): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card canvas-card accepted-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title">
                                            <a href="11.php?id=<?= $canvas['canva_id'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($canvas['name']) ?>
                                            </a>
                                        </h5>
                                        <div class="d-flex flex-column aligh-items-end">
                                            <span class="badge bg-<?=$canvas['permission'] === 'edit' ? 'warning' : 'secondary' ?> mb-1">
                                                 <?= $canvas['permission'] === 'edit' ? 'Επεξεργασία' : 'Προβολή' ?> 
                                            </span>
                                            <span class="badge access-badge bg-info text-dark">
                                                <i class="bi bi-people-fill me-1"></i>
                                                Κοινοχρηστος
                                            </span>
                            
                                        </div>
                                         


                                    <p class="card-text text-muted small">
                                        <i class="bi bi-folder"></i>
                                        <?= htmlspecialchars($canvas['canva_category'] ?? 'Γενική') ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $ownerAvatar = !empty($canvas['owner_avatar']) ? $avatarPath . $canvas['owner_avatar'] : $defaultAvatar;
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <!-- Owner Avatar -->
                                                <img src="<?= $ownerAvatar ?>" 
                                                     class="owner-avatar" 
                                                     alt="<?= htmlspecialchars($canvas['owner_name']) ?>"
                                                     onerror="this.src='<?= $defaultAvatar ?>'"
                                                     title="Δημιουργός: <?= htmlspecialchars($canvas['owner_name']) ?>">
                                                
                                                <!-- Collaborator Avatar (YOU) -->
                                                <img src="<?= $currentUserAvatar ?>" 
                                                     class="owner-avatar avatar-overlap" 
                                                     alt="<?= htmlspecialchars($canvas['collaborator_username']) ?>"  
                                                     title="Συνεργάτης: <?= htmlspecialchars($canvas['collaborator_username']) ?>">
                                            </div>
                                            <small class="text-muted ms-2">
                                               <strong><?= htmlspecialchars($canvas['collaborator_username']) ?></strong>
                                                <span class="text-muted"> & <?= htmlspecialchars($canvas['owner_name']) ?></span>
                                            </small>
                                        </div>
                                        <small class="text-muted"><br>
                                          Αποδεχομενη: <?= date('d/m/Y', strtotime($canvas['accepted_at'] ?? $canvas['invited_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between">
                                        <a href="11.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> 
                                            <?= $canvas['permission'] === 'edit' ? 'Συνεργασία' : 'Προβολή' ?>
                                        </a>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Εχει γινει αποδεκτος
                                        </span>
                                        <a href="leave_canvas.php?id=<?= $canvas['canva_id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                              onclick="return confirm('Θέλετε σίγουρα να αποχωρήσετε από αυτόν τον πίνακα;')">
                                                  <i class="bi bi-box-arrow-right"></i> Αποχώρηση
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Δημόσιοι Πίνακες -->
       <?php if (count($public_canvases) > 0): ?>
    <div class="mb-5">
        <h3 class="section-title text-success">
            <i class="bi bi-globe me-2"></i>Δημόσιοι Πίνακες
        </h3>
        <div class="row">
            <?php foreach ($public_canvases as $canvas): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card canvas-card public-card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <a href="11.php?id=<?= $canvas['canva_id'] ?>" class="text-decoration-none text-dark fw-bold">
                                        <?= htmlspecialchars($canvas['name']) ?>
                                    </a>
                                </h5>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    Δημόσιο
                                </span>
                            </div>
                            
                            <p class="card-text text-muted small mb-3">
                                <i class="bi bi-tag me-1"></i>
                                <?= htmlspecialchars($canvas['canva_category'] ?? 'Γενική') ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <?php $ownerAvatar = !empty($canvas['owner_avatar']) ? $avatarPath . $canvas['owner_avatar'] : $defaultAvatar; ?>
                                    <img src="<?= $ownerAvatar ?>" 
                                         class="owner-avatar me-2" 
                                         alt="<?= htmlspecialchars($canvas['owner_name']) ?>"
                                         onerror="this.src='<?= $defaultAvatar ?>'"
                                         style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                    <small class="text-muted"><?= htmlspecialchars($canvas['owner_name']) ?></small>
                                </div>
                                <small class="text-muted">
                                    
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($canvas['created_at'])) ?>
                                </small>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-top-0 pb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    
                                    <a href="11.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                                        <i class="bi bi-eye"></i> Προβολή
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="join_public.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-plus-circle"></i> Προσθήκη στην Συνεργασια
                                    </a>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
       

        <!-- Empty State -->
        <?php if (count($shared_canvases) === 0 && count($public_canvases) === 0): ?>
            <div class="text-center py-5 empty-state">
                <div class="mb-4">
                    <i class="bi bi-people display-1 text-muted"></i>
                </div>
                <h3 class="text-muted">Δεν έχετε κοινοχρηστους πίνακες</h3>
                <p class="text-muted mb-4">Όταν κάποιος χρήστης σας προσκαλέσει σε πίνακα ή δημιουργηθεί δημόσιος πίνακας, θα εμφανίζεται εδώ.</p>
                <a href="home.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Πίσω στην Αρχική
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        let lastCheck = '<?= date('Y-m-d H:i:s') ?>';
        
        // Auto initialize toasts
        $('.toast').toast('show');
        
        // Auto-check για νέες προσκλήσεις
        function checkForNewInvitations() {
            $.ajax({
                url: '/canva/checknewinvitations.php',
                type: 'GET',
                data: {
                    last_check: lastCheck
                },
                dataType: 'json',
                success: function(response) {
                    console.log('API Response:', response);
                    
                    if (response.error) {
                        console.error('Σφάλμα API:', response.error);
                        return;
                    }
                    
                    if (response.has_new_invitations && response.new_count > 0) {
                        showNotification('🎉 Έχετε ' + response.new_count + ' νέα προσκλήσεις!', 'success');
                        updateBadgeCount(response.new_count);
                    }
                    lastCheck = response.last_check;
                },
                error: function(xhr, status, error) {
                    console.error('Σφάλμα ελέγχου προσκλήσεων:', error);
                }
            });
        }

        // Βελτιωμένη ειδοποίηση με toast
        function showNotification(message, type = 'info') {
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-bell-fill me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#notificationArea').html(toastHtml);
            $('.toast').toast('show');
            
            setTimeout(() => {
                $('.toast').toast('hide');
            }, 5000);
        }

        // Update badge counter
        function updateBadgeCount(newCount) {
            let pendingBadge = $('#pendingBadge');
            let totalBadge = $('#totalCanvasesBadge');
            
            if (pendingBadge.length) {
                let currentPending = parseInt(pendingBadge.text()) || 0;
                pendingBadge.text(currentPending + newCount);
                pendingBadge.addClass('pulse-animation');
            } else {
                $('h1').append(`<span class="badge bg-danger pulse-animation ms-2" id="pendingBadge">${newCount} Νέα</span>`);
            }
            
            if (totalBadge.length) {
                let currentTotal = parseInt(totalBadge.text()) || 0;
                totalBadge.text(currentTotal + newCount);
            }
            
            setTimeout(() => {
                $('.pulse-animation').removeClass('pulse-animation');
            }, 2000);
        }

        // Check every 30 seconds
        setInterval(checkForNewInvitations, 30000);
        
        // Manual refresh button
        $('#refreshBtn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="bi bi-arrow-clockwise"></i> Ανανέωση...');
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        });

        // Initial check after page load
        setTimeout(checkForNewInvitations, 2000);
    });
    </script>
</body>
</html>