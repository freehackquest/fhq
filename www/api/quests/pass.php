<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

$curdir = dirname(__FILE__);
include_once ($curdir."/../api.lib/api.base.php");
include_once ($curdir."/../api.lib/api.game.php");
include_once ($curdir."/../api.lib/api.answerlist.php");
include_once ($curdir."/../api.lib/api.quest.php");
include_once ($curdir."/../../config/config.php");
include_once ($curdir."/../api.lib/loadtoken.php");

APIHelpers::checkAuth();

$message = '';

if (!APIGame::checkGameDates($message))
	APIHelpers::showerror(3101, $message);

if (!APIHelpers::issetParam('questid'))
	APIHelpers::showerror(3102, 'Not found parameter "questid"');

if (!APIHelpers::issetParam('answer'))
	APIHelpers::showerror(3103, 'Not found parameter "answer"');
	
$questid = APIHelpers::getParam('questid', 0);
$answer = APIHelpers::getParam('answer', '');

if ($answer == "")
  APIHelpers::showerror(3104, 'Parameter "answer" must be not empty');

if (!is_numeric($questid))
	APIHelpers::showerror(3105, 'Parameter "questid" must be numeric');

$result = array(
	'result' => 'fail',
	'data' => array(),
);

$result['result'] = 'ok';

// TODO: must be added filters
$conn = APIHelpers::createConnection($config);

$result['gameid'] = APIGame::id(); 
$result['userid'] = APISecurity::userid();

$filter_by_state = APISecurity::isAdmin() ? '' : ' AND quest.state = "open" ';
$filter_by_score = APISecurity::isAdmin() ? '' : ' AND quest.min_score <= '.APISecurity::score().' ';

$params[] = APISecurity::userid();
$params[] = APIGame::id();
$params[] = intval($questid);

$questname = '';

$query = '
			SELECT 
				quest.idquest,
				quest.name,
				quest.answer,
				userquest.startdate,
				userquest.stopdate
			FROM 
				quest
			LEFT JOIN 
				userquest ON userquest.idquest = quest.idquest AND userquest.iduser = ?
			WHERE
				quest.gameid = ?
				AND quest.idquest = ?
				'.$filter_by_state.'
				'.$filter_by_score.'
		';

try {
	$stmt = $conn->prepare($query);
	$stmt->execute($params);
	if($row = $stmt->fetch())
	{
		$questname = $row['name'];
		$status = '';
		if ($row['stopdate'] == null)
			$status = 'open';
		else if ($row['stopdate'] == '0000-00-00 00:00:00')
			$status = 'in_progress';
		else
			$status = 'completed';

		$result['data'] = array(
			'questid' => $row['idquest'],
			'date_start' => $row['startdate'],
			'date_stop' => $row['stopdate'],
		);
		$result['quest'] = $row['idquest'];
		$real_answer = $row['answer'];
		if ($status == 'in_progress') {
			// check answer
			if (md5(strtoupper($real_answer)) == md5(strtoupper($answer))) {
				
				$result['result'] = 'ok';
				
				$nowdate = date('Y-m-d H:i:s');
				$query1 = 'UPDATE userquest SET stopdate = NOW() WHERE idquest = ? AND iduser = ?;';
				$stmt1 = $conn->prepare($query1);
				$stmt1->execute(array(intval($questid), APISecurity::userid()));
				$new_user_score = APIHelpers::calculateScore($conn);			
				$result['new_user_score'] = $new_user_score;
				if ($_SESSION['user']['score'] != $result['new_user_score'])
				{
					$_SESSION['user']['score'] = $result['new_user_score'];
					$query2 = 'UPDATE users_games SET date_change = NOW(), score = ? WHERE userid = ? AND gameid = ?;';
					$stmt2 = $conn->prepare($query2);
					$stmt2->execute(array(intval($new_user_score), APISecurity::userid(), APIGame::id()));
				}
				APIQuest::updateCountUserSolved($conn, $questid);

				APIAnswerList::addTryAnswer($conn, $questid, $answer, $real_answer, 'Yes');
				APIAnswerList::movedToBackup($conn, $questid);

				// add to public events
				if (!APISecurity::isAdmin())
					APIEvents::addPublicEvents($conn, "users", 'User {'.APISecurity::nick().'} passed quest #'.$questid.' {'.$questname.'} from game #'.APIGame::id().' {'.APIGame::title().'} (new user score: '.$new_user_score.')');
			} else {
				APIAnswerList::addTryAnswer($conn, $questid, $answer, $real_answer, 'No');
				APIHelpers::showerror(3106, 'Answer incorrect');
			};
		}
		else
		{
			APIHelpers::showerror(3107, 'Quest already passed');
		}

		/*if ($status == 'current' || $status == 'completed')
			$result['data']['text'] = $row['text'];*/
	}
	else
	{
		APIHelpers::showerror(3108, 'Not found quest');
	}
	
} catch(PDOException $e) {
	APIHelpers::showerror(3109, $e->getMessage());
}

include_once ($curdir."/../api.lib/savetoken.php");
echo json_encode($result);