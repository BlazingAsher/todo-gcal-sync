<?php
require_once 'vendor/autoload.php';
require 'config.php';
require 'utils.php';

$conn = fetchPDOConnection($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);

// Refresh all refresh tokens
if($conn != null){
    $stmt = $conn->prepare('SELECT * FROM tokens');

    if ($stmt->execute()) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => $OAUTH_APP_ID,
                'clientSecret'            => $OAUTH_APP_PASSWORD,
                'redirectUri'             => $OAUTH_REDIRECT_URI,
                'urlAuthorize'            => $OAUTH_AUTHORITY.$OAUTH_AUTHORIZE_ENDPOINT,
                'urlAccessToken'          => $OAUTH_AUTHORITY.$OAUTH_TOKEN_ENDPOINT,
                'urlResourceOwnerDetails' => '',
                'scopes'                  => $OAUTH_SCOPES
            ]);

            $newAccessToken = $oauthClient->getAccessToken('refresh_token', [
                'refresh_token' => $row["ms_refreshtoken"]
            ]);

            echo $newAccessToken->getToken();
            echo "<br><br>";
            echo $newAccessToken->getRefreshToken();

            //TODO: Refresh Google token here as well

            $uStmt = $conn->prepare('UPDATE tokens SET ms_accesstoken=:ms_at, ms_accesstoken_expiry=:ms_at_exp, ms_refreshtoken = :ms_rt, ms_refreshtoken_issued=:ms_rt_iss WHERE ms_id=:ms_id');

            $uStmt->bindValue(':ms_id', $row['ms_id'], PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at', $newAccessToken->getToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_at_exp', $newAccessToken->getExpires(), PDO::PARAM_INT);
            $uStmt->bindValue(':ms_rt', $newAccessToken->getRefreshToken(), PDO::PARAM_STR);
            $uStmt->bindValue(':ms_rt_iss', time(), PDO::PARAM_INT);

            $uStmt->execute();
            echo '<br><br></br>updated';
        }
    }


}