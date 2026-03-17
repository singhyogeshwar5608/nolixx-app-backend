<?php
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminAuthController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../controllers/VideoTemplatesController.php';
require_once __DIR__ . '/../controllers/TemplatesController.php';
require_once __DIR__ . '/../controllers/CategoriesController.php';
require_once __DIR__ . '/../controllers/UsersController.php';
require_once __DIR__ . '/../controllers/SubscriptionsController.php';
require_once __DIR__ . '/../controllers/SubscriptionPostersController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/YoutubeAuthController.php';
require_once __DIR__ . '/../controllers/YoutubeController.php';
use App\Controllers\AuthController;
use App\Controllers\AdminAuthController;
use App\Controllers\DashboardController;
use App\Controllers\TemplatesController;
use App\Controllers\CategoriesController;
use App\Controllers\UsersController;
use App\Controllers\SubscriptionsController;
use App\Controllers\SubscriptionPostersController;
use App\Controllers\SettingsController;
use App\Controllers\VideoTemplatesController;
use App\Controllers\YoutubeAuthController;


$router->get('/auth/youtube', function() {
    $controller = new YoutubeAuthController();
    $controller->redirectToGoogle();
});

$router->get('/auth/youtube/callback', function() {
    $controller = new YoutubeAuthController();
    $controller->callback();
});
$router->get('/auth/youtube', function() {
    $controller = new YoutubeAuthController();
    $controller->redirectToGoogle();
});

$router->get('/auth/youtube/callback', function() {
    $controller = new YoutubeAuthController();
    $controller->callback();
});

// User Auth Routes
$router->post('/api/send-otp', function() {
    $controller = new AuthController();
    $controller->sendOtp();
});

$router->post('/api/verify-otp', function() {
    $controller = new AuthController();
    $controller->verifyOtp();
});

$router->post('/api/login/check-user', function() {
    $controller = new AuthController();
    $controller->checkExistingUser();
});

$router->post('/api/login/send-otp', function() {
    $controller = new AuthController();
    $controller->loginSendOtp();
});

$router->post('/api/login/verify-otp', function() {
    $controller = new AuthController();
    $controller->loginVerifyOtp();
});

$router->post('/api/login/google', function() {
    $controller = new AuthController();
    $controller->googleLogin();
});

$router->post('/api/videos/upload', function() {
    $controller = new VideoTemplatesController();
    $controller->upload();
});

$router->post('/api/admin/template-videos', function() {
    $controller = new VideoTemplatesController();
    $controller->createTemplateVideo();
});

$router->get('/api/admin/template-videos', function() {
    $controller = new VideoTemplatesController();
    $controller->listTemplateVideos();
});

$router->put('/api/admin/template-videos/update', function() {
    $controller = new VideoTemplatesController();
    $controller->updateTemplateVideo();
});

$router->delete('/api/admin/template-videos/delete', function() {
    $controller = new VideoTemplatesController();
    $controller->deleteTemplateVideo();
});

$router->post('/api/user/check-email', function() {
    $controller = new UsersController();
    $controller->checkEmailAvailability();
});

$router->post('/api/user/profile', function() {
    $controller = new UsersController();
    $controller->getProfile();
});

$router->post('/api/user/update-avatar', function() {
    $controller = new UsersController();
    $controller->updateAvatar();
});

$router->post('/api/user/save-profile', function() {
    $controller = new UsersController();
    $controller->saveFromApp();
});

$router->get('/api/categories', function() {
    $controller = new CategoriesController();
    $controller->listForApp();
});

$router->get('/api/templates', function() {
    $controller = new TemplatesController();
    $controller->listForApp();
});

$router->get('/api/templates/by-category', function() {
    $controller = new TemplatesController();
    $controller->listForAppByCategory();
});

$router->get('/api/subscription-posters', function() {
    $controller = new SubscriptionPostersController();
    $controller->listForApp();
});

$router->get('/api/template-videos', function() {
    $controller = new VideoTemplatesController();
    $controller->listForApp();
});

$router->get('/api/templates', function() {
    $controller = new TemplatesController();
    $controller->listForApp();
});

// Admin Auth Routes
$router->post('/api/admin/login', function() {
    $controller = new AdminAuthController();
    $controller->login();
});

$router->post('/api/admin/logout', function() {
    $controller = new AdminAuthController();
    $controller->logout();
});

// Dashboard Routes
$router->get('/api/admin/dashboard/stats', function() {
    $controller = new DashboardController();
    $controller->getStats();
});

$router->get('/api/admin/dashboard/recent', function() {
    $controller = new DashboardController();
    $controller->getRecentUploads();
});

// Templates Routes
$router->get('/api/admin/templates', function() {
    $controller = new TemplatesController();
    $controller->getAll();
});

$router->get('/api/admin/templates/view', function() {
    $controller = new TemplatesController();
    $controller->getById();
});

$router->post('/api/admin/templates', function() {
    $controller = new TemplatesController();
    $controller->create();
});

$router->put('/api/admin/templates/update', function() {
    $controller = new TemplatesController();
    $controller->update();
});

$router->delete('/api/admin/templates/delete', function() {
    $controller = new TemplatesController();
    $controller->delete();
});

// Categories Routes
$router->get('/api/admin/categories', function() {
    $controller = new CategoriesController();
    $controller->getAll();
});

$router->get('/api/admin/categories/view', function() {
    $controller = new CategoriesController();
    $controller->getById();
});

$router->post('/api/admin/categories', function() {
    $controller = new CategoriesController();
    $controller->create();
});

$router->put('/api/admin/categories/update', function() {
    $controller = new CategoriesController();
    $controller->update();
});

$router->post('/api/admin/categories/update', function() {
    $controller = new CategoriesController();
    $controller->update();
});

$router->delete('/api/admin/categories/delete', function() {
    $controller = new CategoriesController();
    $controller->delete();
});

$router->patch('/api/admin/categories/toggle', function() {
    $controller = new CategoriesController();
    $controller->toggleStatus();
});

// Users Routes
$router->get('/api/admin/users', function() {
    $controller = new UsersController();
    $controller->getAll();
});

$router->get('/api/admin/users/view', function() {
    $controller = new UsersController();
    $controller->getById();
});

$router->patch('/api/admin/users/ban', function() {
    $controller = new UsersController();
    $controller->ban();
});

$router->patch('/api/admin/users/unban', function() {
    $controller = new UsersController();
    $controller->unban();
});

$router->delete('/api/admin/users/delete', function() {
    $controller = new UsersController();
    $controller->delete();
});

// Subscriptions Routes
$router->get('/api/admin/subscriptions', function() {
    $controller = new SubscriptionsController();
    $controller->getAll();
});

$router->get('/api/admin/subscriptions/view', function() {
    $controller = new SubscriptionsController();
    $controller->getById();
});

$router->post('/api/admin/subscriptions', function() {
    $controller = new SubscriptionsController();
    $controller->create();
});

$router->put('/api/admin/subscriptions/update', function() {
    $controller = new SubscriptionsController();
    $controller->update();
});

$router->delete('/api/admin/subscriptions/delete', function() {
    $controller = new SubscriptionsController();
    $controller->delete();
});

$router->patch('/api/admin/subscriptions/toggle', function() {
    $controller = new SubscriptionsController();
    $controller->toggleStatus();
});

// Subscription Posters Routes
$router->get('/api/admin/subscription-posters', function() {
    $controller = new SubscriptionPostersController();
    $controller->listAdmin();
});

$router->post('/api/admin/subscription-posters', function() {
    $controller = new SubscriptionPostersController();
    $controller->create();
});

$router->put('/api/admin/subscription-posters/update', function() {
    $controller = new SubscriptionPostersController();
    $controller->update();
});

$router->delete('/api/admin/subscription-posters/delete', function() {
    $controller = new SubscriptionPostersController();
    $controller->delete();
});

$router->patch('/api/admin/subscription-posters/toggle', function() {
    $controller = new SubscriptionPostersController();
    $controller->toggle();
});

// Settings Routes
$router->get('/api/admin/settings', function() {
    $controller = new SettingsController();
    $controller->get();
});

$router->put('/api/admin/settings', function() {
    $controller = new SettingsController();
    $controller->update();
});
$router->post('/upload-video', function() {

    $title = $_POST['title'];
    $file = $_FILES['video'];

    $path = "../uploads/videos/".$file['name'];
    move_uploaded_file($file['tmp_name'], $path);

    $youtube = new YoutubeController();
    $url = $youtube->upload($path, $title);

    echo json_encode([
        "status" => true,
        "youtube_url" => $url
    ]);
});
