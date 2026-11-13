<?php
declare(strict_types=1);
// Retired public administration endpoint. Never expose session information here.
http_response_code(404);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
exit;
