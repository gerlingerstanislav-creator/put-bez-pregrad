<?php
declare(strict_types=1);

// Retired public login/administration entry. No redirect to the private URL.
http_response_code(404);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
exit;
