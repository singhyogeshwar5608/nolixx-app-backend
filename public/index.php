<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

/* manually include core classes */
require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../core/Router.php';

use Dotenv\Dotenv;
use App\Core\Cors;
use App\Core\Router;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$appTimezone = trim((string)($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?? ''));
if ($appTimezone === '') {
    $appTimezone = 'UTC';
}

date_default_timezone_set($appTimezone);

// Handle CORS
Cors::handle();

// Initialize router
$router = new Router();

// Load routes
require_once __DIR__ . '/../routes/api.php';

// Dispatch request
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);