<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

$curdir = dirname(__FILE__);
include_once ($curdir."/../api.lib/api.base.php");
include_once ($curdir."/../../config/config.php");

APIHelpers::checkAuth();

$result = array(
	'result' => 'fail',
	'data' => array(),
);

$result['result'] = 'ok';

$conn = APIHelpers::createConnection($config);

$country = '';
$city = '';

if (!APIHelpers::issetParam('style'))
  APIHelpers::showerror(912, 'Not found parameter "style"');

$style = APIHelpers::getParam('style', '');

try {
	$_SESSION['user']['profile']['template'] = $style;

	$query = 'UPDATE users_profile SET value = ?, date_change = NOW() WHERE name = ? AND userid = ?';
	$stmt = $conn->prepare($query);

	$stmt->execute(array($style, 'template', APISecurity::userid()));

	$result['result'] = 'ok';
} catch(PDOException $e) {
	showerror(911, 'Error 911: ' + $e->getMessage());
}

echo json_encode($result);