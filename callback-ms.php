<?php

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'utils.php';

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
        'clientId'                => $OAUTH_APP_ID,
        'clientSecret'            => $OAUTH_APP_PASSWORD,
        'redirectUri'             => $OAUTH_REDIRECT_URI,
        'urlAuthorize'            => $OAUTH_AUTHORITY.$OAUTH_AUTHORIZE_ENDPOINT,
        'urlAccessToken'          => $OAUTH_AUTHORITY.$OAUTH_TOKEN_ENDPOINT,
        'urlResourceOwnerDetails' => '',
        'scopes'                  => $OAUTH_SCOPES
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

        $graph = new Graph();
        $graph->setAccessToken($accessToken->getToken());

        $user = $graph->createRequest("GET", "/me")
                ->setReturnType(Model\User::class)
                ->execute();

        echo 'Authenticated user ' . $user->getGivenName() . ' ID ' . $user->getId();

        // store access tokens in DB
        $conn = fetchPDOConnection($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);
        if($conn != null){
            // Check if user already exists
            $stmt = $conn->prepare('SELECT EXISTS(SELECT * FROM tokens WHERE ms_id=?) as ex');
            $stmt->bindParam(1, $sql, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $uStmt = null;
            if($row['ex'] == "0"){
                // Found a user, will update token
                $uStmt = $conn->prepare('UPDATE tokens SET ms_accesstoken=:ms_at, ms_accesstoken_expiry=:ms_at_exp, ms_refreshtoken = :ms_rt, ms_refreshtoken_issued=:ms_rt_iss WHERE ms_id=:ms_id');

                echo 'user found';
            } else {
                // New user
                $stmt = $conn->prepare('INSERT INTO tokens (ms_id, ms_accesstoken, ms_accesstoken_expiry, ms_refreshtoken, ms_refreshtoken_issued) VALUES (:ms_id, :ms_at, :ms_at_exp, :ms_rt, :ms_rt_iss)');

                echo 'record created';
            }

            $uStmt->bindValue(':ms_id', $row['ms_id'], PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at', $accessToken->getToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at_exp', $accessToken->getExpires(), PDO::PARAM_INT);
            $uStmt->bindValue(':ms_rt', $accessToken->getRefreshToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_rt_iss', time(), PDO::PARAM_INT);

            $uStmt->execute();
            echo 'executed';

            $_SESSION["ms_id"] = $user->getId();

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