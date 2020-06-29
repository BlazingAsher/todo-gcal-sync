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

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

if (!isset($expectedState)) {
    // If there is no expected state in the session,
    // do nothing and redirect to the home page.
    echo 'No expected state! Go to <a href="signin-ms.php">signin-ms.php</a> to start the sign in process.';
    die();
}

if (!isset($providedState) || $expectedState != $providedState) {
    echo 'Invalid auth state! Go to <a href="signin-ms.php">signin-ms.php</a> to start the sign in process.';
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

        // Get user info from Outlook API
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', 'https://outlook.office.coom/api/v2.0/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken->getToken()
            ]
        ]);

        $user = json_decode($res->getBody(), true);

        $user_ms_id = $user['Id'];

        // Get a DB connection so that we can add/update user
        try{
            $conn = fetchPDOConnection();
            // Check if user already exists
            $stmt = $conn->prepare('SELECT EXISTS(SELECT * FROM tokens WHERE ms_id=? LIMIT 1) as ex');
            $stmt->bindParam(1, $user_ms_id, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $uStmt = null;
            if($row['ex'] != "0"){
                // Found a user, will update token
                $uStmt = $conn->prepare('UPDATE tokens SET ms_accesstoken=:ms_at, ms_accesstoken_expiry=:ms_at_exp, ms_refreshtoken = :ms_rt, ms_refreshtoken_issued=:ms_rt_iss WHERE ms_id=:ms_id');

                echo 'Updated user tokens. ';
            } else {
                // New user, add an entry
                $uStmt = $conn->prepare('INSERT INTO tokens (ms_id, ms_accesstoken, ms_accesstoken_expiry, ms_refreshtoken, ms_refreshtoken_issued) VALUES (:ms_id, :ms_at, :ms_at_exp, :ms_rt, :ms_rt_iss)');

                echo 'Created user. ';
            }

            $uStmt->bindValue(':ms_id', $user_ms_id, PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at', $accessToken->getToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at_exp', $accessToken->getExpires(), PDO::PARAM_INT);
            $uStmt->bindValue(':ms_rt', $accessToken->getRefreshToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_rt_iss', time(), PDO::PARAM_INT);

            $uStmt->execute();
            echo 'Successfully signed in.';

            // Add auth details to session
            $_SESSION["ms_id"] = $user_ms_id;
            $_SESSION['ms_token'] = $accessToken->getToken();

        }
        catch(PDOException $e){
            echo 'Error establishing database connection.';
            $logger->error('Error establishing database connection.');
            $logger->error($e);
        }

        die();
    }
    catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        echo 'Error retrieving access token!';
        $logger->error('Error retrieving access token!');
        $logger->error($e);
        die();
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        echo 'Error retrieving user information!';
        $logger->error('Error retrieving user information!');
        $logger->error($e);
        die();
    }
}

echo 'No auth code! Go to <a href="signin-ms.php">signin-ms.php</a> to start the sign in process.';