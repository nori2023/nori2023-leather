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
if ($path === '/checkout' || str_starts_with($path, '/checkout/')) {
    require APP_DIR . '/routes/checkout.php';
    route_checkout($path);
    exit;
}
if ($path === '/contact' || str_starts_with($path, '/contact/')) {
    require APP_DIR . '/routes/contact.php';
    route_contact($path);
    exit;
}
route_public($path);
