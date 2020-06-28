<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';

// Initialize the OAuth client
$oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $OAUTH_APP_ID,
    'clientSecret'            => $OAUTH_APP_PASSWORD,
    'redirectUri'             => $OAUTH_REDIRECT_URI,
    'urlAuthorize'            => $OAUTH_AUTHORITY.$OAUTH_AUTHORIZE_ENDPOINT,
    'urlAccessToken'          => $OAUTH_AUTHORITY.$OAUTH_TOKEN_ENDPOINT,
    'urlResourceOwnerDetails' => '',
    'scopes'                  => $OAUTH_SCOPES
]);

$authUrl = $oauthClient->getAuthorizationUrl();

$_SESSION["oauthState"] = $oauthClient->getState();

echo $authUrl;