<?php
// Ο χρήστης ανακατευθύνεται εδώ όταν δεν έχει τα κατάλληλα δικαιώματα
session_start();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Χωρίς Πρόσβαση</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light text-center">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4">Πρόσβαση Αρνηθείσα</h2>
                <div class="alert alert-danger" role="alert">
                    Δεν έχετε τα απαιτούμενα δικαιώματα για να αποκτήσετε πρόσβαση σε αυτή τη σελίδα.
                </div>
                <p class="lead">Για να δείτε όλο το περιεχόμενο θα πρέπει να έχετε λογαριασμό </p>
                <p class="lead mb-4">Αν έχετε ήδη λογαριασμό συνδεθείτε με τα στοιχεία σας, εναλλακτικά δημιουργήστε έναν δωρεάν λογαριασμό, μόνο με το email σας.</p>
                
                <div class="d-grid gap-3 d-md-block mb-4">
                    <a href="index.php" class="btn btn-outline-secondary">Επιστροφή</a>
                </div>

                <div class="d-flex flex-column gap-3">
                    <a href="login.php" class="btn btn-dark btn-lg">
                        Σύνδεση
                        <i class="fas fa-sign-in-alt ms-2"></i>
                    </a>
                    <a href="signup.php" class="btn btn-outline-dark btn-lg">
                        Δημιουργία λογαριασμού
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>