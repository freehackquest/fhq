<?php
/*
 * API_NAME: Delete Feedback Submessage
 * API_DESCRIPTION: delete submesage for feedback 
 * API_ACCESS: admin only
 * API_INPUT: id - string, Identificator of the feedback message
 * API_INPUT: token - string, token
 */

$curdir_feedback_deletemessage = dirname(__FILE__);
include_once ($curdir_feedback_deletemessage."/../api.lib/api.helpers.php");
include_once ($curdir_feedback_deletemessage."/../../config/config.php");
include_once ($curdir_feedback_deletemessage."/../api.lib/api.base.php");

$response = APIHelpers::startpage($config);
APIHelpers::checkAuth();

if(!APISecurity::isAdmin())
  APIHelpers::error(403, 'access denie. you must be admin.');

if (!APIHelpers::issetParam('id'))
  APIHelpers::error(400, 'not found parameter id');

$id = APIHelpers::getParam('id', 0);

if (!is_numeric($id))
  APIHelpers::error(400, 'incorrect id');

$conn = APIHelpers::createConnection($config);

try {
 	$conn->prepare('DELETE FROM feedback_msg WHERE id = ?')->execute(array(intval($id)));
 	$response['result'] = 'ok';
} catch(PDOException $e) {
 	APIHelpers::error(500, $e->getMessage());
}

APIHelpers::endpage($response);
