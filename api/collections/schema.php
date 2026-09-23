<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/collections.php';
requireAuth();

sendJson(['success' => true, 'schemas' => collectionSchemas()]);
