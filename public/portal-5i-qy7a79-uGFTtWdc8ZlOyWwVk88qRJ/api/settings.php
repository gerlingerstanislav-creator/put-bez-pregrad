<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/common.php';
require_https();
start_admin_session();
require_admin();
only_method('GET');
$state = current_state();
response_json(['settings' => $state['settings'], 'smtp' => smtp_public_status($state), 'csrf' => $_SESSION['csrf']]);
