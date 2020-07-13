<?php
require_once 'vendor/autoload.php';
require 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

try{
    $conn = fetchPDOConnection();

    $tokenCache = array();

    // Refresh all refresh tokens
    $stmt = $conn->prepare('SELECT * FROM tokens');

    if ($stmt->execute()) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try{
                $token = refreshMSToken($conn, $row['ms_id'], $row['ms_refreshtoken']);

                // store this for sub renewal
                $tokenCache[$row['ms_id']] = $token;

                $gToken = refreshGoogleToken($conn, $row['ms_id'], $row['google_refreshtoken']);
            }
            catch(\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e){
                $logger->error('Error refreshing MS token for MS ID ' . $row['ms_id']);
                $logger->error($e);

                try {
                    sendPushoverMessage('Error refreshing MS token for MS ID ' . $row['ms_id']);
                } catch (ToDoCalSyncException $e) {
                    $logger->error('Error sending message to Pushover');
                }
            }
            catch(Google_Service_Exception $e){
                $logger->error('Error refreshing Google token for MS ID ' . $row['ms_id']);
                $logger->error($e);

                try {
                    sendPushoverMessage('Error refreshing Google token for MS ID ' . $row['ms_id']);
                } catch (ToDoCalSyncException $e) {
                    $logger->error('Error sending message to Pushover');
                }
            }

        }
    }


    $stmtGetSub = $conn->prepare('SELECT * FROM ms_subs');
    if($stmtGetSub->execute()){
        while ($row = $stmtGetSub->fetch(PDO::FETCH_ASSOC)) {
            $client = new GuzzleHttp\Client();

            try {
                $res = $client->request('PATCH', 'https://outlook.office.com/api/v2.0/me/subscriptions/' . $row['ms_sub_id'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenCache[$row['ms_id']]
                    ],
                    'json' => [
                        "@odata.type"=>"#Microsoft.OutlookServices.PushSubscription"
                    ]
                ]);

            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $logger->error('Error renewing MS sub ID ' . $row['ms_sub_id']);
                $logger->error($e);

                try {
                    sendPushoverMessage('Error renewing MS sub ID ' . $row['ms_sub_id']);
                } catch (ToDoCalSyncException $e) {
                    $logger->error('Error sending message to Pushover');
                }
            }
        }
    }
}
catch (PDOException $e){
    $logger->error('Error establishing database connection during cron run.');
    $logger->error($e);

    try {
        sendPushoverMessage('Error establishing database connection during cron run.');
    } catch (ToDoCalSyncException $e) {
        $logger->error('Error sending message to Pushover');
    }
}

echo 'OK';

try {
    sendPushoverMessage('ToDoCalSync cron run successfully.');
} catch (ToDoCalSyncException $e) {
    $logger->error('Error sending message to Pushover');
}