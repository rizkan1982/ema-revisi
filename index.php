<?php
// EMA Camp Management System - Main Entry Point with PWA Support
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('modules/auth/login.php');
}

// Redirect to dashboard
redirect('modules/dashboard/');
?>