<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Secure Logout Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash.php';

// Log out user
logoutUser();

// Start a fresh session to deliver the flash message
initSession();
setFlash('info', 'You have been successfully logged out.');

// Redirect to login page
redirect('auth/login.php');
