<?php
require_once 'vendor/autoload.php';
require_once 'utils.php';

session_start();

if(!isset($_SESSION["ms_id"])){
    echo 'Please authorize with MS first at <a href="signin-ms.php">signin-ms.php</a>';
    die();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

$client = new Google_Client();
$client->setApplicationName("To Do and Google Calendar Syncer");
$client->setAuthConfig('./google_client_credentials.json');
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('consent');
$client->setIncludeGrantedScopes(true);

$client->setRedirectUri($_ENV['GOOGLE_OAUTH_REDIRECT_URI']);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    var_dump($token);
    try{
        $conn = fetchPDOConnection();

        $stmt = $conn->prepare('UPDATE tokens SET google_accesstoken=:g_at, google_accesstoken_expiry=:g_at_exp, google_refreshtoken=:g_rt, google_refreshtoken_issued=:g_rt_iss WHERE ms_id=:ms_id');
        $stmt->bindValue(":g_at", $token['access_token'], PDO::PARAM_STR);
        $stmt->bindValue(":g_at_exp", $token['created'] + $token['expires_in'], PDO::PARAM_INT);
        $stmt->bindValue(":g_rt", $token['refresh_token'], PDO::PARAM_STR);
        $stmt->bindValue(":g_rt_iss", $token['created'], PDO::PARAM_INT);
        $stmt->bindValue(":ms_id", $_SESSION['ms_id'], PDO::PARAM_STR);

        $stmt->execute();

        echo 'Associated bearer with MSID ' . $_SESSION['ms_id'];
    }
    catch(PDOException $e){
        echo 'Error establishing database connection.';
        $logger->error('Error establishing database connection.');
        $logger->error($e);
    }

}
else{
    $authUrl = $client->createAuthUrl();
    echo '<a href="' . $authUrl . '">Click here to sign in to Google</a>';
}