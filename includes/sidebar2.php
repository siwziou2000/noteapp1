<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="users.css">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <span class="me-2"></span>My Workspace
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <div class="navbar-nav me-auto">
                    <a class="nav-link active" href="users_dashboard.php">Home</a>
                    <a class="nav-link" href="#">mygroup</a>
                    <a class="nav-link" href="#">Ρυθμισεις</a>
                    <a class="nav-link" href="logout.php">Αποσυνδεση</a>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <form class="d-flex">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search">
                            <button type="submit" class="btn btn-outline-dark">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </nav>

    <!-- Πλαϊνό Μενού -->
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="users_dashboard.php" class="sidebar-link active">My Page</a>
            <a href="canvaspreferences.html" class="sidebar-link">My Canvases</a>
            <a href="newgroup.html" class="sidebar-link">My Groups</a>
            <a href="favorite.html" class="sidebar-link">Favorites</a>
            <a href="tasks.html" class="sidebar-link">Tasks</a>
        </nav>
    </aside>





    </body>
</html>