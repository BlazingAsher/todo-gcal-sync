<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate state
$expectedState = $_SESSION['oauthState'];
unset($_SESSION["oauthState"]);
$providedState = $_GET['state'];

if (!isset($expectedState)) {
    // If there is no expected state in the session,
    // do nothing and redirect to the home page.
    echo 'no expected state';
    die();
}

if (!isset($providedState) || $expectedState != $providedState) {
    echo 'invalid auth state';
    die();
}

// Authorization code should be in the "code" query param
$authCode = $_GET['code'];
if (isset($authCode)) {
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

    try {
        // Make the token request
        $accessToken = $oauthClient->getAccessToken('authorization_code', [
            'code' => $authCode
        ]);

        // TEMPORARY FOR TESTING!
        echo 'access token received<br>';
        echo $accessToken->getToken();
        echo '<br><br>';
        echo $accessToken->getRefreshToken();
        echo '<br><br>';

        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', 'https://outlook.office.com/api/v2.0/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken->getToken()
            ]
        ]);

        echo $res->getBody();
        $user = json_decode($res->getBody(), true);

        $user_ms_id = $user['Id'];

        // store access tokens in DB
        $conn = fetchPDOConnection();
        if($conn != null){
            // Check if user already exists
            $stmt = $conn->prepare('SELECT EXISTS(SELECT * FROM tokens WHERE ms_id=? LIMIT 1) as ex');
            $stmt->bindParam(1, $user_ms_id, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $uStmt = null;
            if($row['ex'] != "0"){
                // Found a user, will update token
                $uStmt = $conn->prepare('UPDATE tokens SET ms_accesstoken=:ms_at, ms_accesstoken_expiry=:ms_at_exp, ms_refreshtoken = :ms_rt, ms_refreshtoken_issued=:ms_rt_iss WHERE ms_id=:ms_id');

                echo 'user found';
            } else {
                // New user
                $uStmt = $conn->prepare('INSERT INTO tokens (ms_id, ms_accesstoken, ms_accesstoken_expiry, ms_refreshtoken, ms_refreshtoken_issued) VALUES (:ms_id, :ms_at, :ms_at_exp, :ms_rt, :ms_rt_iss)');

                echo 'record created';
            }

            $uStmt->bindValue(':ms_id', $user_ms_id, PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at', $accessToken->getToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at_exp', $accessToken->getExpires(), PDO::PARAM_INT);
            $uStmt->bindValue(':ms_rt', $accessToken->getRefreshToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_rt_iss', time(), PDO::PARAM_INT);

            $uStmt->execute();
            echo 'executed';

            $_SESSION["ms_id"] = $user_ms_id;
            $_SESSION['ms_token'] = $accessToken->getToken();

            //TODO: Subscribe to event notifications

        }
        else {
            echo 'no connection';
        }

        die();
    }
    catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        echo 'error retrieving access token';
        die();
    }
}

echo 'no auth code';