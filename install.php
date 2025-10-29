<?php
// Unified installer: Always use the dark-themed setup wizard
// If setup already complete, go to the app
if (file_exists('config/config.php') && file_exists('config/setup_complete.txt')) {
    header('Location: index.php');
    exit;
}

// Otherwise, proceed to the single setup wizard
header('Location: setup/index.php');
exit;
?>