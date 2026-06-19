<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/template_repo.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/dashboard.php');
    exit;
}

if ($action === 'takedown') {
    takedown_template($id);
} elseif ($action === 'restore') {
    restore_template($id);
}

header('Location: /admin/dashboard.php');
exit;
