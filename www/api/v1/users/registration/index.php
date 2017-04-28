<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

/*
 * API_NAME: Registration
 * API_DESCRIPTION: Method for registration in the system.
 * API_ACCESS: all
 * API_INPUT: email - string, user's email
 * API_INPUT: client - string, indentifier of frontend
 * API_INPUT: captcha - string, here -> api/captcha.php
 */

$curdir = dirname(__FILE__);
include_once ($curdir."/../../../api.lib/api.base.php");
include_once ($curdir."/../../../api.lib/api.helpers.php");
include_once ($curdir."/../../../api.lib/api.security.php");
include_once ($curdir."/../../../api.lib/api.user.php");
include_once ($curdir."/../../../../config/config.php");
include_once ($curdir."/../../../api.lib/api.mail.php");

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT);

$result = array(
	'result' => 'fail',
	'data' => array(),
);

if (!APIHelpers::issetParam('email'))
	APIHelpers::showerror(1013, 'Parameter email was not found');

if (!APIHelpers::issetParam('captcha'))
	APIHelpers::showerror(1043, 'Parameter captcha was not found');

$conn = APIHelpers::createConnection($config);

$email = APIHelpers::getParam('email', '');
$captcha = APIHelpers::getParam('captcha', '');
$captcha_uuid = APIHelpers::getParam('captcha_uuid', '');
$orig_captcha = APIHelpers::find_captcha($conn, $captcha_uuid);

if (strtoupper($captcha) != strtoupper($orig_captcha))
	APIHelpers::showerror(1012, '[Registration] Captcha is not correct, please "Refresh captcha" and try again');

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
	APIHelpers::showerror(1011, '[Registration] Invalid e-mail address.');


$stmt = $conn->prepare('select count(*) as cnt from users where email = ?');
$stmt->execute(array($email));
if ($row = $stmt->fetch())
{
	if (intval($row['cnt']) >= 1)
		APIHelpers::showerror(1192, '[Registration] This e-mail was already registered.');	
}

$nick = "hacker-".substr(md5(rand().rand()), 0, 7);
$email = strtolower($email);
$uuid = APIHelpers::gen_guid();

$password = substr(md5(rand().rand()), 0, 8);
$password_hash = APISecurity::generatePassword2($email, $password);

// same code exists in api/users/insert.php
// same code exists in google_auth.php
$query = '
        INSERT INTO users(
                uuid,
                pass,
                status,
                email,
                nick,
                role,
                logo,
		last_ip,
                dt_last_login,
                dt_create
        )
        VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, NOW(),NOW());
';

$stmt_insert = $conn->prepare($query);

$new_user = array(
        $uuid,
        $password_hash, // pass
        'activated',
        $email,
        $nick,
        'user',
        'files/users/0.png',
	''
);

$r = $stmt_insert->execute($new_user);
$error = print_r($conn->errorInfo(),true);

if( !APISecurity::login($conn, $email, $password_hash)) {
	APIEvents::addPublicEvents($conn, 'errors', 'Alert! Admin, registration is broken!');
	error_log("1287: ".$error);
	APIHelpers::showerror(1287, '[Registration] Sorry registration is broken. Please send report to the admin about this.');
} else {
	APISecurity::updateLastDTLogin($conn);
	APIUser::loadUserProfile($conn);
	APISecurity::logout();
}

$email_subject = "Registration on FreeHackQuest.";

$email_message = '
	Registration:

	If you was not tried registering on '.$config['hostname'].' just remove this email.

	Welcome to FreeHackQuest!

	Your login: '.$email.'
	Your password: '.$password.' (You must change it)
	Link: '.$config['hostname'].'
';

$stmt_insert2 = $conn->prepare('
	INSERT INTO email_delivery(
		to_email,
		subject,
		message,
		priority,
		status,
		dt
	)
	VALUES ( ?, ?, ?, ?, ?, NOW());
');

$stmt_insert2->execute(array(
	$email,
	$email_subject,
	$email_message,
	'high',
	'sending',
));


// $nick
APIEvents::addPublicEvents($conn, 'users', 'New player {'.htmlspecialchars($nick).'}. Welcome!');

$error = '';

// this option must be moved to db
if (isset($config['mail']) && isset($config['mail']['allow']) && $config['mail']['allow'] == 'yes') {
	APIMail::send($config, $email, '', '', $email_subject, $email_message, $error);
}

$result['result'] = 'ok';
$result['data']['message'] = 'Check your your e-mail (also please check spam).';

echo json_encode($result);