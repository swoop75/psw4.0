<?php
/**
 * File: new_companies_management.php
 * Description: Redirect to buylist_management.php - consolidated interface
 */

// Redirect to the main buylist management page
header('Location: /buylist_management.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
?>