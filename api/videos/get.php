<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$id = $_GET['id'] ?? '';
$data = readData();
foreach ($data['videoProjects'] as $p) {
    if ($p['id'] === $id) {
        unset($p['outputPath']);
        foreach ($p['clips'] as &$c) {
            unset($c['path']);
        }
        sendJson(['success' => true, 'project' => $p]);
    }
}
sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
