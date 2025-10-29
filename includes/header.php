<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>FinEase</title>
    <link rel="stylesheet" href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../assets/css/style.css' : 'assets/css/style.css'; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../index.php' : 'index.php'; ?>" class="brand-link" title="Go to Dashboard">
                <h2>💼 FinEase</h2>
            </a>
        </div>
        
        <div class="hamburger" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <ul class="nav-menu" id="navMenu">
            <?php if (checkUserPermission('accountant')): ?>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../index.php' : 'index.php'; ?>" class="nav-link">Dashboard</a></li>
            <?php endif; ?>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'clients.php' : 'pages/clients.php'; ?>" class="nav-link">Clients</a></li>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'invoices.php' : 'pages/invoices.php'; ?>" class="nav-link">Job Orders</a></li>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'transactions.php' : 'pages/transactions.php'; ?>" class="nav-link">Transactions</a></li>
            <?php if (checkUserPermission('accountant')): ?>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'tithes.php' : 'pages/tithes.php'; ?>" class="nav-link">Tithes</a></li>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'reports.php' : 'pages/reports.php'; ?>" class="nav-link">Reports</a></li>
            <?php endif; ?>
            <?php if (checkUserPermission('admin')): ?>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'users.php' : 'pages/users.php'; ?>" class="nav-link">Users</a></li>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'settings.php' : 'pages/settings.php'; ?>" class="nav-link">Settings</a></li>
            <?php endif; ?>
            <li><a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? 'about.php' : 'pages/about.php'; ?>" class="nav-link">About</a></li>
        </ul>
        
        <div class="nav-user">
            <span>Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../auth/logout.php' : 'auth/logout.php'; ?>" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <main class="main-content">