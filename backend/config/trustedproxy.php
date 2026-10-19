<?php

$trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => $trustedProxies === ''
        ? null
        : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
];
