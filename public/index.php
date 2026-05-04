<?php
/**
 * Front controller. All HTTP traffic enters here.
 * On XAMPP: http://localhost/SSDACMIS/public/  (or /schoolreg/public/ for
 * older clones — the runtime auto-detects the install path).
 * In production: point the domain's document root to this /public folder.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

/** @var \App\Core\Router $router */
$router = require dirname(__DIR__) . '/app/routes.php';
$router->dispatch();
