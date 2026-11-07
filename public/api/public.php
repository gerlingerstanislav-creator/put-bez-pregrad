<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
only_method('GET');
response_json(['settings' => current_state()['settings']]);
