<?php
class APISecurity {
	static function isLogged() {
		if (APIHelpers::$FHQSESSION != NULL) {
			return isset(APIHelpers::$FHQSESSION['user']);
		}
		return isset($_SESSION['user']);
	}

	static function login($conn, $email, $hash_password) {
		// try {
			$query = 'SELECT * FROM users WHERE email = ? AND pass = ?';
			$email = strtolower($email);
			$params = array(
				$email,
				$hash_password,
			);
			$stmt = $conn->prepare($query);
			$stmt->execute($params);
			if ($row = $stmt->fetch())
			{
				$_SESSION['user'] = array();
				$_SESSION['user']['id'] = $row['id'];
				$_SESSION['user']['email'] = $row['email'];
				$_SESSION['user']['nick'] = $row['nick'];
				$_SESSION['user']['role'] = $row['role'];
				APIHelpers::$FHQSESSION = array(
					'user' => array(
						'id' => $row['id'],
						'email' => $row['email'],
						'nick' => $row['nick'],
						'role' => $row['role'],
					),
				);
				return true;
			}
		// } catch(PDOException $e) {
			// APIHelpers::showerror(1201, $e->getMessage());
		// }
		return false;
	}
	
	static function login_by_google($conn, $email) {
		$query = 'SELECT * FROM users WHERE email = ?';
		$email = strtolower($email);
		$params = array(
			$email
		);
		$stmt = $conn->prepare($query);
		$stmt->execute($params);
		if ($row = $stmt->fetch())
		{
			$_SESSION['user'] = array();
			$_SESSION['user']['id'] = $row['id'];
			$_SESSION['user']['email'] = $row['email'];
			$_SESSION['user']['nick'] = $row['nick'];
			$_SESSION['user']['role'] = $row['role'];
			APIHelpers::$FHQSESSION = array(
				'user' => array(
					'id' => $row['id'],
					'email' => $row['email'],
					'nick' => $row['nick'],
					'role' => $row['role'],
				),
			);
			return true;
		}
		return false;
	}

	static function logout() {
		if(APISecurity::isLogged()) {
			unset($_SESSION['user']);
			unset($_SESSION['game']);
			
			if (APIHelpers::$FHQSESSION != NULL) {
				unset(APIHelpers::$FHQSESSION['user']);
				unset(APIHelpers::$FHQSESSION['game']);
			}
		}
	}

	static function generatePassword2($email, $password) {
		return sha1(strtoupper($email).$password);
	}
	
	static function role() { 
		if (APIHelpers::$FHQSESSION != NULL)
			return (APISecurity::isLogged() ? APIHelpers::$FHQSESSION['user']['role'] : '' ); 
		return (APISecurity::isLogged()) ? $_SESSION['user']['role'] : '';
	}
	
	static function isAdmin() {
		return (APISecurity::role() == 'admin');
	}
	
	static function isUser() {
		return (APISecurity::role() == 'user');
	}
	
	static function isTester() {
		return (APISecurity::role() == 'tester');
	}
	
	static function isGod() {
		return (APISecurity::role() == 'god');
	}
	
	static function score() { 
		if (APIHelpers::$FHQSESSION != NULL && APISecurity::isLogged() && isset(APIHelpers::$FHQSESSION['user']['score'])) {
			return is_numeric(APIHelpers::$FHQSESSION['user']['score']) ? intval(APIHelpers::$FHQSESSION['user']['score']) : 0;
		}
		return (APISecurity::isLogged() && is_numeric($_SESSION['user']['score'])) ? $_SESSION['user']['score'] : 0; 
	}
	
	static function setUserScore($newScore) {
		if (isset($_SESSION['user']['score']))
			$_SESSION['user']['score'] = $newScore;
		if (APIHelpers::$FHQSESSION != NULL && APISecurity::isLogged() ) {
			APIHelpers::$FHQSESSION['user']['score'] = $newScore;
		}
	}	
	
	static function nick() { 
		if (APIHelpers::$FHQSESSION != NULL && APISecurity::isLogged()) {
			return isset(APIHelpers::$FHQSESSION['user']['nick']) ? APIHelpers::$FHQSESSION['user']['nick'] : '';
		}
		return (APISecurity::isLogged()) ? $_SESSION['user']['nick'] : ''; 
	}
	
	static function email() {
		if (APIHelpers::$FHQSESSION != NULL && APISecurity::isLogged()) {
			return isset(APIHelpers::$FHQSESSION['user']['email']) ? $FHQSESSION['user']['email'] : '';
		}
		return (APISecurity::isLogged()) ? strtolower($_SESSION['user']['email']) : '';
	}
  
	static function setNick($nick) {
		// TODO $FHQSESSION
		if(APISecurity::isLogged())
			$_SESSION['user']['nick'] = $nick;
	}

	static function userid() {
		$userid = 0;
		if (APIHelpers::$FHQSESSION != NULL && APISecurity::isLogged() && isset(APIHelpers::$FHQSESSION['user']['id'])) {
			$userid = intval(APIHelpers::$FHQSESSION['user']['id']);
		} else {
			$userid = (APISecurity::isLogged() && isset($_SESSION['user']['id'])) ? $_SESSION['user']['id'] : intval('');
		}
		if (intval($userid) == 0) {
			return 0;
		}
	}

	static function updateLastDTLogin($conn) {
		try {
			$stmt_dls = $conn->prepare('UPDATE users SET dt_last_login = NOW() WHERE id = ?');
			$stmt_dls->execute(array(APISecurity::userid()));
		} catch(PDOException $e) {
			APIHelpers::showerror(1198, $e->getMessage());
		}
	}
	
	static function saveByToken() { 
		try {
			$query = 'INSERT INTO users_tokens (userid, token, status, data, start_date, end_date) VALUES(?, ?, ?, ?, NOW(), NOW() + INTERVAL 1 DAY)';
			$params = array(
				APISecurity::userid(),
				APIHelpers::$TOKEN,
				'active',
				json_encode(APIHelpers::$FHQSESSION)
			);
			$stmt = APIHelpers::$CONN->prepare($query);
			$stmt->execute($params);
		} catch(PDOException $e) {
			APIHelpers::showerror(1196, $e->getMessage());
		}
	}

	static function loadByToken() { 
		try {
			$query = 'SELECT data FROM users_tokens WHERE token = ? AND status = ?'; // AND end_date > NOW()
			$params = array(
				APIHelpers::$TOKEN,
				'active'
			);
			$stmt = $conn->prepare($query);
			$stmt->execute($params);
			if ($row = $stmt->fetch())
				APIHelpers::$FHQSESSION = json_decode($row['data'],true);
		} catch(PDOException $e) {
			APIHelpers::showerror(1197, $e->getMessage());
		}
	}
	
	static function updateByToken() { 
		if (APIHelpers::$TOKEN == null || APIHelpers::$FHQSESSION == null || APIHelpers::$CONN == null)
			return;
		
		$query = 'UPDATE users_tokens SET data = ?, end_date = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE token = ?';
		$params = array(
			json_encode(APIHelpers::$FHQSESSION),
			APIHelpers::$TOKEN,
		);
		$stmt = APIHelpers::$CONN->prepare($query);
		$stmt->execute($params);
	}
	
	static function removeByToken($conn, $token) { 
		try {
			$query = 'DELETE FROM users_tokens WHERE token = ?';
			$params = array(
				$token,
			);
			$stmt = $conn->prepare($query);
			$stmt->execute($params);
		} catch(PDOException $e) {
			APIHelpers::showerror(1199, $e->getMessage());
		}
	}
}
