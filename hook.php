<?php
require_once 'logging.php';
require_once 'utils.php';
$json = file_get_contents('php://input');
$data = json_decode($json, true);
ob_start();
var_dump($data);
$result = ob_get_clean();

$log = new Logging();
$log->lfile('log.txt');
$log->lwrite($result);

if(isset($_GET['validationtoken'])){
    echo $_GET['validationtoken'];
    die();
}

if($data != null){
    $conn = fetchPDOConnection();
    if($conn != null){
        foreach($data['value'] as $noti){
            $resource = $noti['Resource'];

            // fetch tokens from the DB
            $stmt = $conn->prepare('SELECT ms_id,google_calendar_id FROM tokens WHERE id IN (SELECT user_id FROM ms_subs WHERE ms_sub_id=?)');
            $stmt->bindParam(1, $noti['SubscriptionId']);

            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $msToken = fetchMSUserToken($conn, $row['ms_id']);
            $gToken = fetchGoogleUserToken($conn, $row['ms_id']);

            // Set up the Google Client
            $clientGoogle = new Google_Client();
            $clientGoogle->setAuthConfig('./google_client_credentials.json');
            $clientGoogle->setAccessToken($gToken);

            $service = new Google_Service_Calendar($clientGoogle);

            // Get the ID from the notification
            $toDoId = $noti['ResourceData']['Id'];

            // See if we have it registered in the DB
            $stmtGetTask = $conn->prepare('SELECT * FROM tasks WHERE ms_task_id=? LIMIT 1');
            $stmtGetTask->bindParam(1, $toDoId, PDO::PARAM_STR);

            $stmtGetTask->execute();

            $task_registered = false;
            if($stmtGetTask->rowCount() > 0){
                // Tasks exists in DB
                $task_registered = true;
            }

            $rowGetTask = $stmtGetTask->fetch(PDO::FETCH_ASSOC);

            // If the task was not deleted, get information about it
            $toDo = null;
            if($noti['ChangeType'] != 'Deleted'){
                $clientHTTP = new GuzzleHttp\Client();
                $res = null;
                try{
                    $res = $clientHTTP->request('GET', $resource, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $msToken
                        ]
                    ]);
                }
                catch(\GuzzleHttp\Exception\ClientException $e){
                    // log and die here
                    echo 'error with request bad';
                }
                catch(\GuzzleHttp\Exception\ServerException $e){
                    // log and die here
                    echo 'error getting task info from microsfot, deal with it';
                }

                $toDo = json_decode($res->getBody(), true);
            }

            // Process the notification
            $stmtUpdateTaskDB = null;
            $createEvent = false;

            if($noti['ChangeType'] == "Updated" && $task_registered){
                // Look for task entry in DB, if not found create it in Google Calendar
                $event = prepareEventFromTodo($toDo);
                try{
                    $service->events->update($row['google_calendar_id'], $rowGetTask['google_event_id'], $event);
                }
                catch(Google_Service_Exception $e){
                    $createEvent = true;
                }
            }
            else if($noti['ChangeType'] == 'Deleted' && $task_registered){
                // Look for task entry in DB, if found, delete from Google Calendar
                try{
                    $service->events->delete($row['google_calendar_id'], $rowGetTask['google_event_id']);
                }
                catch(Google_Service_Exception $e){
                    $log->lwrite("deleted on Google end");
                    echo 'already deleted on Google end';
                }

                // Drop it from the DB
                $stmtRemoveTask = $conn->prepare('DELETE FROM tasks WHERE ms_task_id=? LIMIT 1');
                $stmtRemoveTask->bindParam(1, $toDoId, PDO::PARAM_STR);
                $stmtRemoveTask->execute();

            }
            else if(($noti['ChangeType'] == 'Created' &&!$task_registered) || ($noti['ChangeType'] == "Updated" && !$task_registered)){
                $createEvent = true;
            }
            else{
                echo 'somthign is weird: ';
                echo $task_registered ? "true": "false";
            }

            if($createEvent){
                // Create task if it doesn't already exist OR is it is supposed to be updated but we don't have an entry for it
                $event = prepareEventFromTodo($toDo);
                $createdEvent = $service->events->insert($row['google_calendar_id'], $event);
                echo $createdEvent->getId();

                $stmtUpdateTaskDB = $conn->prepare('INSERT INTO tasks (ms_task_id, google_event_id) VALUES (:m_t_id, :g_e_id)');

                $stmtUpdateTaskDB->bindValue(":m_t_id", $toDoId);
                $stmtUpdateTaskDB->bindValue(":g_e_id", $createdEvent->getId());

                $stmtUpdateTaskDB->execute();
            }
        }
    }

}

$log->lclose();