<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function fetchPDOConnection(){
    $DB_HOST = $_ENV['DB_HOST'];
    $DB_NAME = $_ENV['DB_NAME'];
    $DB_USER = $_ENV['DB_USER'];
    $DB_PASSWORD = $_ENV['DB_PASSWORD'];
    try{
        $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;

    } catch(PDOException $e){
        echo 'Connection failed: ' . $e->getMessage();
        return null;
    }
}

function refreshMSToken($conn, $ms_id, $ms_refreshtoken){
    $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => $_ENV['OAUTH_APP_ID'],
        'clientSecret'            => $_ENV['OAUTH_APP_PASSWORD'],
        'redirectUri'             => $_ENV['OAUTH_REDIRECT_URI'],
        'urlAuthorize'            => $_ENV['OAUTH_AUTHORITY'].$_ENV['OAUTH_AUTHORIZE_ENDPOINT'],
        'urlAccessToken'          => $_ENV['OAUTH_AUTHORITY'].$_ENV['OAUTH_TOKEN_ENDPOINT'],
        'urlResourceOwnerDetails' => '',
        'scopes'                  => $_ENV['OAUTH_SCOPES']
    ]);

    $newAccessToken = $oauthClient->getAccessToken('refresh_token', [
        'refresh_token' => $ms_refreshtoken
    ]);

    $uStmt = $conn->prepare('UPDATE tokens SET ms_accesstoken=:ms_at, ms_accesstoken_expiry=:ms_at_exp, ms_refreshtoken = :ms_rt, ms_refreshtoken_issued=:ms_rt_iss WHERE ms_id=:ms_id');

    $uStmt->bindValue(':ms_id', $ms_id, PDO::PARAM_STR);
    $uStmt->bindValue(':ms_at', $newAccessToken->getToken(), PDO::PARAM_STR);
    $uStmt->bindValue(':ms_at_exp', $newAccessToken->getExpires(), PDO::PARAM_INT);
    $uStmt->bindValue(':ms_rt', $newAccessToken->getRefreshToken(), PDO::PARAM_STR);
    $uStmt->bindValue(':ms_rt_iss', time(), PDO::PARAM_INT);

    $uStmt->execute();

    return $newAccessToken->getToken();
}

function fetchMSUserToken($conn, $ms_id) {
    $stmt = $conn->prepare('SELECT ms_accesstoken,ms_accesstoken_expiry,ms_refreshtoken FROM tokens WHERE ms_id=? LIMIT 1');
    $stmt->bindParam(1, $ms_id, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row['ms_accesstoken'] != null){
        if(time() > $row['ms_accesstoken_expiry']) {
            // need to refresh the token
            echo 'refreshing token';
            return refreshMSToken($conn, $ms_id, $row['ms_refreshtoken']);
        }
        else {
            return $row['ms_accesstoken'];
        }
    }
    else{
        return null;
    }

}