<?php
declare(strict_types=1);
require_once __DIR__ . '/spam.php';
only_method('GET');
response_json(['settings' => current_state()['settings'], 'challenge' => issue_form_challenge()]);
