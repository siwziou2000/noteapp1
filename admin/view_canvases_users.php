<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/auth.php');

// STRICT ADMIN CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('🔒 Μόνο διαχειριστές έχουν πρόσβαση.');
}

// Παράμετροι GET
$action = $_GET['action'] ?? 'list_users';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$canva_id = isset($_GET['canva_id']) ? intval($_GET['canva_id']) : 0;
$delete_id = isset($_GET['delete']) ? intval($_GET['delete']) : 0;

// Επεξεργασία actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleAdminPostActions();
}

if ($delete_id > 0) {
    handleDeleteAction();
}

// Καθορισμός τι θα εμφανιστεί
switch ($action) {
    case 'view_user_canvases':
        displayUserCanvases($user_id);
        break;
    case 'view_canvas':
        redirectToCanvas($canva_id);
        break;
    case 'system_stats':
        displaySystemStats();
        break;
    default:
        displayAllUsers();
        break;
}

// ============ ΣΥΝΑΡΤΗΣΕΙΣ ============

function handleAdminPostActions() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['error' => 'Μη έγκυρο αίτημα!']));
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            deleteUser($user_id);
            break;
        case 'delete_canvas':
            $canva_id = intval($_POST['canva_id']);
            deleteCanvas($canva_id);
            break;
        case 'change_user_role':
            $user_id = intval($_POST['user_id']);
            $new_role = $_POST['new_role'];
            changeUserRole($user_id, $new_role);
            break;
    }
}

function handleDeleteAction() {
    global $pdo, $delete_id;
    
    $type = $_GET['type'] ?? '';
    
    if ($type === 'user') {
        deleteUser($delete_id);
    } elseif ($type === 'canvas') {
        deleteCanvas($delete_id);
    }
    
    header("Location: view_canvases_users.php");
    exit;
}

function deleteUser($user_id) {
    global $pdo;
    
    try {
        // Αρχικά διαγράφουμε τα δεδομένα που εξαρτώνται από τον χρήστη
        $pdo->beginTransaction();
        
        // . Διαγραφή σημειώσεων του χρήστη
        $stmt = $pdo->prepare("DELETE FROM notes WHERE owner_id = ?");
        $stmt->execute([$user_id]);
        
        //  Διαγραφή media του χρήστη
        $stmt = $pdo->prepare("DELETE FROM media WHERE owner_id = ?");
        $stmt->execute([$user_id]);
        
        //. Διαγραφή canvases του χρήστη
        $stmt = $pdo->prepare("DELETE FROM canvases WHERE owner_id = ?");
        $stmt->execute([$user_id]);
        
        //  Διαγραφή συνεργασιών
        $stmt = $pdo->prepare("DELETE FROM canvas_collaborators WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        //  Διαγραφή χρήστη
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        $_SESSION['admin_message'] = '✅ Ο χρήστης διαγράφηκε επιτυχώς!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = '❌ Σφάλμα διαγραφής: ' . $e->getMessage();
    }
}

function deleteCanvas($canva_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        //  Διαγραφή σημειώσεων του καμβά
        $stmt = $pdo->prepare("DELETE FROM notes WHERE canva_id = ?");
        $stmt->execute([$canva_id]);
        
        //  Διαγραφή media του καμβά
        $stmt = $pdo->prepare("DELETE FROM media WHERE canva_id = ?");
        $stmt->execute([$canva_id]);
        
        //  Διαγραφή συνεργατών
        $stmt = $pdo->prepare("DELETE FROM canvas_collaborators WHERE canva_id = ?");
        $stmt->execute([$canva_id]);
        
        //  Διαγραφή καμβά
        $stmt = $pdo->prepare("DELETE FROM canvases WHERE canva_id = ?");
        $stmt->execute([$canva_id]);
        
        $pdo->commit();
        
        $_SESSION['admin_message'] = '✅ Ο καμβάς διαγράφηκε επιτυχώς!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = '❌ Σφάλμα διαγραφής καμβά: ' . $e->getMessage();
    }
}
//allagi rolon
function changeUserRole($user_id, $new_role) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->execute([$new_role, $user_id]);
    
    $_SESSION['admin_message'] = "Ο ρόλος του χρήστη άλλαξε σε $new_role!";
}
//emfnizetai to xriston

function displayAllUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Σφάλμα βάσης: " . $e->getMessage());
    }
    
    ?>

    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <title>Διαχείριση Χρηστών - Admin Panel</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
        <style>
            .admin-header {
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                color: white;
                padding: 1.5rem;
                border-radius: 10px;
                margin-bottom: 2rem;
            }
            .stats-card {
                transition: transform 0.3s;
                border: none;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .stats-card:hover {
                transform: translateY(-5px);
            }
            .user-role-badge {
                font-size: 0.8em;
                padding: 0.25em 0.6em;
            }
            .action-buttons .btn {
                margin: 2px;
            }
        </style>
    </head>
    <body>
        <div class="container-fluid mt-4">
            <!-- Admin Header -->
            <div class="admin-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-shield-check"></i> Admin Panel</h1>
                        <p class="mb-0">Διαχείριση χρηστών και καμβά</p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                        </span>
                        
                    </div>
                </div>
            </div>
            
            <!-- System Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-people"></i> Σύνολο Χρηστών</h5>
                            <h2 class="display-4"><?= count($users) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-file-text"></i> Καμβά</h5>
                            <h2 class="display-4">
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) FROM canvases");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-sticky"></i> Σημειώσεις</h5>
                            <h2 class="display-4">
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) FROM notes");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-images"></i> Πολυμέσα</h5>
                            <h2 class="display-4">
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) FROM media");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['admin_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['admin_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['admin_error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['admin_error']); ?>
            <?php endif; ?>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="bi bi-people-fill"></i> Χρήστες Συστήματος</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                  
                                    <th>Ρόλος</th>
                                    <th>Ημ/νία Εγγραφής</th>
                                    <th>Καμβά</th>
                                    <th>Ενέργειες</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['user_id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">Εσείς</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                   
                                    <td>
                                        <span class="badge user-role-badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'secondary' ?>">
                                            <?= htmlspecialchars($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM canvases WHERE owner_id = ?");
                                        $stmt->execute([$user['user_id']]);
                                        $canvas_count = $stmt->fetchColumn();
                                        ?>
                                        <span class="badge bg-primary"><?= $canvas_count ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="view_canvases_users.php?action=view_user_canvases&user_id=<?= $user['user_id'] ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Καμβά
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editRoleModal"
                                                data-user-id="<?= $user['user_id'] ?>"
                                                data-current-role="<?= htmlspecialchars($user['role']) ?>">
                                            <i class="bi bi-pencil"></i> Ρόλος
                                        </button>
                                        
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="view_canvases_users.php?delete=<?= $user['user_id'] ?>&type=user" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Διαγραφή χρήστη <?= htmlspecialchars($user['username']) ?> και ΟΛΩΝ των δεδομένων του;')">
                                            <i class="bi bi-trash"></i> Διαγραφή
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Role Modal -->
        <div class="modal fade" id="editRoleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Αλλαγή Ρόλου Χρήστη</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="change_user_role">
                        <input type="hidden" name="user_id" id="modalUserId">
                        
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Νέος Ρόλος:</label>
                                <select class="form-select" name="new_role" id="modalUserRole">
                                    <option value="student">Φοιτητής</option>
                                    <option value="teacher">Καθηγητής</option>
                                    <option value="admin">Διαχειριστής</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
                            <button type="submit" class="btn btn-primary">Αποθήκευση</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Modal initialization
            const editRoleModal = document.getElementById('editRoleModal');
            if (editRoleModal) {
                editRoleModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-user-id');
                    const currentRole = button.getAttribute('data-current-role');
                    
                    document.getElementById('modalUserId').value = userId;
                    document.getElementById('modalUserRole').value = currentRole;
                });
            }
        </script>
    </body>
    </html>
    <?php
}
//pinaakes xrisotns
function displayUserCanvases($user_id) {
    global $pdo;
    
    try {
        // Get user info
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            die("Ο χρήστης δεν βρέθηκε.");
        }
        
        // Get user's canvases
        $stmt = $pdo->prepare("SELECT * FROM canvases WHERE owner_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $canvases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        die("Σφάλμα βάσης: " . $e->getMessage());
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <title>Καμβά Χρήστη - Admin Panel</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    </head>
    <body>
        <div class="container mt-4">
            <!-- Back Button -->
            <a href="view_canvases_users.php" class="btn btn-secondary mb-3">
                <i class="bi bi-arrow-left"></i> Πίσω στους Χρήστες
            </a>
            
            <!-- User Info -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-person-circle"></i> 
                        Χρήστης: <?= htmlspecialchars($user['username']) ?>
                    </h4>
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Συνολικοί Καμβά:</strong> <?= count($canvases) ?></p>
                </div>
            </div>
            
            <!-- Canvases Table -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-file-text"></i> Καμβά του Χρήστη</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($canvases)): ?>
                        <div class="alert alert-info">Ο χρήστης δεν έχει δημιουργήσει καμβά.</div>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Όνομα Καμβά</th>
                                    <th>ID</th>
                                    <th>Τύπος Πρόσβασης</th>
                                    <th> Μοναδικο αναγνωριστικο</th>
                                    <th>Ημ/νία Δημιουργίας</th>
                                    <th>Σημειώσεις</th>
                                    <th>Ενέργειες</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($canvases as $canvas): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($canvas['name']) ?></strong>
                                        <?php if (!empty($canvas['canva_category'])): ?>
                                            <br><small class="text-muted">Κατηγορία: <?= htmlspecialchars($canvas['canva_category']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $canvas['canva_id'] ?></td>
                                      
                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        if ($canvas['access_type'] === 'public') $badge_class = 'bg-success';
                                        if ($canvas['access_type'] === 'private') $badge_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= htmlspecialchars($canvas['access_type']) ?>
                                        </span>
                                    </td>
                                      <td><?= $canvas['unique_canva_id'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($canvas['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE canva_id = ?");
                                        $stmt->execute([$canvas['canva_id']]);
                                        $notes_count = $stmt->fetchColumn();
                                        ?>
                                        <span class="badge bg-primary"><?= $notes_count ?></span>
                                    </td>
                                    <td>
                                        
                                        
                                       <a href="../api/canva/11.php?action=view_canvas&id=<?= $canvas['canva_id'] ?>&admin=1" 
                                          class="btn btn-sm btn-info">    <i class="bi bi-shield-check"></i> Προβολή ως Admin
                                        </a>
                                        
                                        <a href="view_canvases_users.php?delete=<?= $canvas['canva_id'] ?>&type=canvas" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Διαγραφή καμβά \"<?= htmlspecialchars($canvas['name']) ?>\" και ΟΛΩΝ των δεδομένων του;')">
                                            <i class="bi bi-trash"></i> Διαγραφή
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function redirectToCanvas($canva_id) {
    header("Location: ../.. 11.php?id=" . $canva_id . "&admin=1");
    exit;
}

function displaySystemStats() {
    global $pdo;
    
    // Θα μπορούσες να προσθέσεις περισσότερες στατιστικές
    echo "System Statistics - Coming Soon";
}
?>