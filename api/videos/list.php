<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$data = readData();
$projects = array_map(function ($p) {
    $p['clipCount'] = count($p['clips'] ?? []);
    unset($p['clips']); // los detalles de clips se piden en get.php si hace falta
    unset($p['outputPath']); // no exponer rutas absolutas del servidor
    return $p;
}, $data['videoProjects']);

usort($projects, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

sendJson([
    'success' => true,
    'projects' => $projects,
    'assets' => [
        'logoSet' => $data['videoAssets']['logoPath'] !== '',
        'musicSet' => $data['videoAssets']['musicPath'] !== '',
    ],
]);
