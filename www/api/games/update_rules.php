<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

/*
 * API_NAME: Update Game Rules
 * API_DESCRIPTION: Method for update game rules
 * API_ACCESS: admin only
 * API_INPUT: id - string, Identificator of the game
 * API_INPUT: rules - string, some rules
 */

$curdir_games_update_rules = dirname(__FILE__);
include_once ($curdir_games_update_rules."/../api.lib/api.base.php");
include_once ($curdir_games_update_rules."/../../config/config.php");

$response = APIHelpers::startpage($config);

APIHelpers::checkAuth();

if(!APISecurity::isAdmin())
  APIHelpers::showerror(1319, 'access denie. you must be admin.');
  
$conn = APIHelpers::createConnection($config);

if (!APIHelpers::issetParam('id'))
  APIHelpers::showerror(1320, 'not found parameter "id"');

$gameid = getParam('id', 0);

if (!is_numeric($gameid))
	APIHelpers::showerror(1321, '"id" must be numeric');

$gameid = intval($gameid);

if (!APIHelpers::issetParam('rules'))
  APIHelpers::showerror(1322, 'not found parameter "rules"');

$rules = APIHelpers::getParam('rules', '');

try {
	$stmt = $conn->prepare('UPDATE games SET rules = ?, date_change = NOW() WHERE id = ?');
	$stmt->execute(array($rules, $gameid));
	$response['result'] = 'ok';
} catch(PDOException $e) {
	APIHelpers::showerror(1323, $e->getMessage());
}

APIHelpers::endpage($response);