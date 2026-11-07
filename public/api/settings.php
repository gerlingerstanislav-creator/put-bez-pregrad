<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
start_admin_session();
require_admin();
only_method('GET');
response_json(['settings' => current_state()['settings'], 'csrf' => $_SESSION['csrf']]);
