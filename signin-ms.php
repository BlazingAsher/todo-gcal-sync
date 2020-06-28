<?php
session_start();
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize the OAuth client
$oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $_ENV['OAUTH_APP_ID'],
    'clientSecret'            => $_ENV['OAUTH_APP_PASSWORD'],
    'redirectUri'             => $_ENV['OAUTH_REDIRECT_URI'],
    'urlAuthorize'            => $_ENV['OAUTH_AUTHORITY'].$_ENV['OAUTH_AUTHORIZE_ENDPOINT'],
    'urlAccessToken'          => $_ENV['OAUTH_AUTHORITY'].$_ENV['OAUTH_TOKEN_ENDPOINT'],
    'urlResourceOwnerDetails' => '',
    'scopes'                  => $_ENV['OAUTH_SCOPES']
]);

$authUrl = $oauthClient->getAuthorizationUrl();

$_SESSION["oauthState"] = $oauthClient->getState();

echo $authUrl;