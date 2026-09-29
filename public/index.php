<?php
/**
 * Front controller. Apache: Alias /autocaller /opt/autocaller/public + mod_rewrite to index.php
 */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

if (!Config::isInstalled()) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "AutoCaller is not installed yet. Run install.sh on the server.\n";
    exit;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$r = new Router();

// auth
$r->get('/login', 'AuthController@loginForm');
$r->post('/login', 'AuthController@login');
$r->post('/logout', 'AuthController@logout');
$r->get('/lang/{lang}', 'AuthController@lang');

// dashboard
$r->get('/', 'DashboardController@index');
$r->get('/dashboard/live.json', 'DashboardController@live');

// campaigns
$r->get('/campaigns', 'CampaignController@index');
$r->get('/campaigns/new', 'CampaignController@create');
$r->post('/campaigns', 'CampaignController@store');
$r->get('/campaigns/{id}', 'CampaignController@show');
$r->get('/campaigns/{id}/edit', 'CampaignController@edit');
$r->post('/campaigns/{id}', 'CampaignController@update');
$r->post('/campaigns/{id}/action', 'CampaignController@action');
$r->get('/campaigns/{id}/import', 'CampaignController@importForm');
$r->post('/campaigns/{id}/import', 'CampaignController@importUpload');
$r->post('/campaigns/{id}/import/confirm', 'CampaignController@importConfirm');
$r->post('/campaigns/{id}/contacts/add', 'CampaignController@addContacts');
$r->get('/campaigns/{id}/contacts.json', 'CampaignController@contactsJson');
$r->get('/campaigns/{id}/stats.json', 'CampaignController@statsJson');
$r->get('/campaigns/{id}/export', 'CampaignController@export');
$r->post('/campaigns/{id}/contacts/{cid}/action', 'CampaignController@contactAction');

// quick call
$r->get('/quick', 'QuickCallController@form');
$r->post('/quick', 'QuickCallController@call');

// audio
$r->get('/audio', 'AudioController@index');
$r->post('/audio/upload', 'AudioController@upload');
$r->get('/audio/{id}/play', 'AudioController@play');
$r->post('/audio/{id}/delete', 'AudioController@delete');
$r->post('/audio/{id}/rename', 'AudioController@rename');

// dnc
$r->get('/dnc', 'DncController@index');
$r->post('/dnc/add', 'DncController@add');
$r->post('/dnc/import', 'DncController@import');
$r->get('/dnc/export', 'DncController@export');
$r->post('/dnc/{id}/delete', 'DncController@delete');

// reports
$r->get('/reports', 'ReportController@index');
$r->get('/reports/export', 'ReportController@export');

// settings
$r->get('/settings', 'SettingsController@index');
$r->post('/settings', 'SettingsController@save');
$r->post('/settings/test-ami', 'SettingsController@testAmi');
$r->post('/settings/holidays/add', 'SettingsController@holidayAdd');
$r->post('/settings/holidays/iran', 'SettingsController@holidayImportIran');
$r->post('/settings/holidays/{id}/delete', 'SettingsController@holidayDelete');

// users & api keys
$r->get('/users', 'UserController@index');
$r->post('/users', 'UserController@store');
$r->post('/users/issabel', 'UserController@issabel');
$r->post('/users/{id}', 'UserController@update');
$r->post('/users/{id}/delete', 'UserController@delete');
$r->get('/profile', 'UserController@profile');
$r->post('/profile', 'UserController@profileSave');
$r->get('/apikeys', 'ApiKeyController@index');
$r->post('/apikeys', 'ApiKeyController@store');
$r->post('/apikeys/{id}/delete', 'ApiKeyController@delete');

// system
$r->get('/system', 'SystemController@index');
$r->get('/system/log/{name}', 'SystemController@log');
$r->get('/audit', 'SystemController@audit');

// REST API
$r->any('/api/v1/(?P<path>.*)', 'ApiController@handle');

try {
    $r->dispatch(Request::method(), Request::path());
} catch (Exception $e) {
    Logger::error('unhandled: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (Request::wantsJson() || strpos(Request::path(), '/api/') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo Util::json(array('ok' => false, 'error' => 'server_error', 'message' => Config::get('app', 'debug') ? $e->getMessage() : 'internal error'));
    } else {
        View::render('error', array('code' => 500, 'message' => Config::get('app', 'debug') ? $e->getMessage() : I18n::t('server_error')));
    }
}
