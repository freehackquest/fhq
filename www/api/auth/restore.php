<?php
header("Access-Control-Allow-Origin: *");

$httpname = 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname(dirname($_SERVER['PHP_SELF']))).'/';

$curdir_registration = dirname(__FILE__);
include_once ($curdir_registration."/../api.lib/api.base.php");
include_once ($curdir_registration."/../api.lib/api.helpers.php");
include_once ($curdir_registration."/../api.lib/api.security.php");
include_once ($curdir_registration."/../../config/config.php");
include_once ($curdir_registration."/../api.lib/api.mail.php");

$result = array(
	'result' => 'fail',
	'data' => array(),
);

if (!APIHelpers::issetParam('email'))
	APIHelpers::showerror(1013, 'Parameter email was not found');

if (!APIHelpers::issetParam('captcha'))
	APIHelpers::showerror(1013, 'Parameter captcha was not found');


$email = APIHelpers::getParam('email', '');
$captcha = APIHelpers::getParam('captcha', '');
$orig_captcha = $_SESSION['captcha_reg'];

// cleanup captcha
$_SESSION['captcha_reg'] = md5(rand().rand());

if (strtoupper($captcha) != strtoupper($orig_captcha))
	APIHelpers::showerror(1012, '[Restore] Captcha is not correct, please "Refresh captcha" and try again');

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
	APIHelpers::showerror(1011, '[Restore] Invalid e-mail address. ');

$conn = APIHelpers::createConnection($config);
$stmt = $conn->prepare('select count(*) as cnt from user where email = ?');
$stmt->execute(array($email));
if ($row = $stmt->fetch())
{
	if (intval($row['cnt']) == 0)
		APIHelpers::showerror(702, '[Restore] This e-mail was not registered.');
}

$password = substr(md5(rand().rand()), 0, 8);
$password_hash = APISecurity::generatePassword2($email, $password);

$query = "";
$stmt_update = $conn->prepare('
	UPDATE user SET
		pass = ?,
		last_ip = ?
	WHERE email = ?;
');

$stmt_update->execute(array(
	$password_hash, // pass
	$_SERVER['REMOTE_ADDR'],
	$email,
));

$email_subject = "Restore password to your account on FreeHackQuest.";

$email_message = '
	Restore:

	Somebody (may be you) reseted your password on '.$httpname.'
	Your login: '.$email.'
	Your new password: '.$password.' (You must change it)
	Link: '.$httpname.'index.php
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

$error = '';
APIMail::send($config, $email, '', '', $email_subject, $email_message, $error);

$result['result'] = 'ok';
$result['data']['message'] = 'Check your your e-mail (also please check spam).';

echo json_encode($result);
