<?php
declare(strict_types=1);

$path = request_path();

if (!is_installed()) {
    require APP_DIR . '/routes/install.php';
    route_install($path);
    exit;
}

if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require APP_DIR . '/routes/admin.php';
    route_admin($path);
    exit;
}

require APP_DIR . '/routes/public.php';
route_public($path);
