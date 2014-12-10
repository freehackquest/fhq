<?php
$curdir = dirname(__FILE__);
include ($curdir."/../api.lib/api.helpers.php");
include ($curdir."/../../config/config.php");
include ($curdir."/../../engine/fhq.php");

$security = new fhq_security();
checkAuth($security);

$result = array(
	'result' => 'fail',
	'data' => array(),
);

$conn = createConnection($config);

try {
  // TODO paging
	$query = 'SELECT 
				games.id,
				games.title,
				games.type_game,
				games.date_start,
				games.date_stop,
				games.date_restart,
				games.description,
				games.logo,
				games.owner,
				user.nick
			FROM
				games
			INNER JOIN user ON games.owner = user.iduser
			ORDER BY games.date_start
			DESC LIMIT 0,10;';

	$columns = array('id', 'title', 'type_game', 'date_start', 'date_stop', 'date_restart', 'description', 'logo', 'owner', 'nick');

	$stmt = $conn->prepare($query);
	$stmt->execute();
	$i = 0;
	while($row = $stmt->fetch())
	{
		$id = $row['date_start'];
		$result['data'][$id] = array();
		foreach ( $columns as $k) {
			$result['data'][$id][$k] = $row[$k];
		}

		$bAllows = $security->isAdmin();
		$result['data'][$id]['permissions']['delete'] = $bAllows;
		$result['data'][$id]['permissions']['update'] = $bAllows;
	}
	$result['current_game'] = isset($_SESSION['game']) ? $_SESSION['game']['id'] : 0;
	
	$result['permissions']['insert'] = $security->isAdmin();
	$result['result'] = 'ok';
} catch(PDOException $e) {
	showerror(702, 'Error 702: ' + $e->getMessage());
}

echo json_encode($result);
