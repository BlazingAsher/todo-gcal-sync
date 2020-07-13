<?php
require_once 'vendor/autoload.php';
require_once 'ToDoCalSyncException.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function fetchPDOConnection(){
    $DB_HOST = $_ENV['DB_HOST'];
    $DB_NAME = $_ENV['DB_NAME'];
    $DB_USER = $_ENV['DB_USER'];
    $DB_PASSWORD = $_ENV['DB_PASSWORD'];
    $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $conn;

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

function refreshGoogleToken($conn, $ms_id, $google_refreshtoken){
    $client = new Google_Client();
    $client->setApplicationName("To Do and Google Calendar Syncer");
    $client->setAuthConfig('./google_client_credentials.json');
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setIncludeGrantedScopes(true);

    $client->setRedirectUri($_ENV['GOOGLE_OAUTH_REDIRECT_URI']);

    $client->refreshToken($google_refreshtoken);
    $gToken = $client->getAccessToken();

    $uStmt = $conn->prepare('UPDATE tokens SET google_accesstoken=:g_at, google_accesstoken_expiry=:g_at_exp WHERE ms_id=:ms_id');

    $uStmt->bindValue(':ms_id', $ms_id, PDO::PARAM_STR);
    $uStmt->bindValue(':g_at', json_encode($gToken), PDO::PARAM_STR);
    $uStmt->bindValue(':g_at_exp', $gToken['created'] + $gToken['expires_in'], PDO::PARAM_INT);

    $uStmt->execute();

    return $gToken;
}

function fetchMSUserToken($conn, $ms_id) {
    $stmt = $conn->prepare('SELECT ms_accesstoken,ms_accesstoken_expiry,ms_refreshtoken FROM tokens WHERE ms_id=? LIMIT 1');
    $stmt->bindParam(1, $ms_id, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row['ms_accesstoken'] != null){
        if(time() > $row['ms_accesstoken_expiry'] - 60) { // give a bit of leeway so that the token will not expire as we are performing an operation
            // need to refresh the token
            echo 'refreshing token';
            return refreshMSToken($conn, $ms_id, $row['ms_refreshtoken']);
        }
        else {
            return $row['ms_accesstoken'];
        }
    }
    else{
        throw new ToDoCalSyncException('No Microsoft access token found in the database with MS ID: ' . $ms_id);
    }

}

function fetchGoogleUserToken($conn, $ms_id){
    $stmt = $conn->prepare('SELECT google_accesstoken,google_accesstoken_expiry,google_refreshtoken FROM tokens WHERE ms_id=? LIMIT 1');
    $stmt->bindParam(1, $ms_id, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row['google_accesstoken'] != null){
        if(time() > $row['google_accesstoken_expiry'] - 60) { // give a bit of leeway so that the token will not expire as we are performing an operation
            // need to refresh the token
            return refreshGoogleToken($conn, $ms_id, $row['google_refreshtoken']);
        }
        else {
            return json_decode($row['google_accesstoken'], true);
        }
    }
    else{
        throw new ToDoCalSyncException('No Google access token found in the database with MS ID: ' . $ms_id);
    }
}

function prepareEventFromTodo($toDo){
    $logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');
    // set event start and end to due date, otherwise the day it was created
    $eventStartEnd = new Google_Service_Calendar_EventDateTime();
    $startEndDateTime = new DateTime($toDo['CreatedDateTime']);

    if($toDo['DueDateTime'] != null){
        try {
            $startEndDateTime = new DateTime($toDo['DueDateTime']['DateTime']);
            $eventStartEnd->setTimeZone($toDo['DueDateTime']['TimeZone']);
        } catch (Exception $e) {
            // No need to do anything, we have default set already
        }
    }
    else{
        // Microsoft sends DateTimes as UTC, PHP is unable to get UTC from the Z at the end for some reason
        $eventStartEnd->setTimeZone("UTC");
    }

    $startEndDate = $startEndDateTime->format('Y-m-d');

//    $logger->info($startEndDate);
//    $logger->info($eventStartEnd->getTimeZone());
//    $logger->debug(json_encode($toDo));

    $eventStartEnd->setDate($startEndDate);

    $event = new Google_Service_Calendar_Event();
    $event->setSummary($toDo['Subject']);
    $event->setDescription($toDo['Body']['Content']);
    
    $event->setStart($eventStartEnd);
    $event->setEnd($eventStartEnd);
    
    return $event;
        
}

function sendPushoverMessage($message) {
    curl_setopt_array($ch = curl_init(), array(
        CURLOPT_URL => "https://api.pushover.net/1/messages.json",
        CURLOPT_POSTFIELDS => array(
            "token" => $_ENV['PUSHOVER_API_KEY'],
            "user" => $_ENV['PUSHOVER_USER_KEY'],
            "message" => $message,
        ),
        CURLOPT_SAFE_UPLOAD => true,
        CURLOPT_RETURNTRANSFER => true,
    ));

    curl_exec($ch);
    if(curl_errno($ch))
    {
        throw new ToDoCalSyncException('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
}