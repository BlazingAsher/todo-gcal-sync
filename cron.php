<?php
require_once 'vendor/autoload.php';
require 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$conn = fetchPDOConnection();

$tokenCache = array();

// Refresh all refresh tokens
if($conn != null){
    $stmt = $conn->prepare('SELECT * FROM tokens');

    if ($stmt->execute()) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $token = refreshMSToken($conn, $row['ms_id'], $row['ms_refreshtoken']);
            echo $token;

            // store this for sub renewal
            $tokenCache[$row['id']] = $token;

            $gToken = refreshGoogleToken($conn, $row['ms_id'], $row['google_refreshtoken']);
            var_dump($gToken);

            echo '<br><br></br>updated';
        }
    }


    $stmtGetSub = $conn->prepare('SELECT * FROM ms_subs');
    if($stmtGetSub->execute()){
        while ($row = $stmtGetSub->fetch(PDO::FETCH_ASSOC)) {
            $client = new GuzzleHttp\Client();

            try {
                $res = $client->request('PATCH', 'https://outlook.office.com/api/v2.0/me/subscriptions/' . $row['ms_sub_id'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenCache[$row['user_id']]
                    ],
                    'json' => [
                        "@odata.type"=>"#Microsoft.OutlookServices.PushSubscription"
                    ]
                ]);
                echo $res->getBody();
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                echo 'error renewing sub';
            }

            echo $res->getBody();
        }
    }
}

echo 'cron complete';