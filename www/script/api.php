<?php

include('tl_budget.php');

session_start();

if (!$_SESSION['db_is_logged_in'])
{
	//echo 'Must be logged in!';
	//exit(0);
}

$uri = $_SERVER['REQUEST_URI'];
$api_dir = strtolower(strstr($uri, '/api'));
$method = $_SERVER['REQUEST_METHOD'];
if (!$api_dir)
{
	echo 'Invalid parameters';
	exit(0);
}

$tokens = explode('/', $api_dir);
$count = count($tokens);

if (count($tokens) < 3)
{
	echo 'Invalid parameters';
	exit(0);
}

if ($tokens[2] == 'rebuildall')
{
	$store->rebuildAllIndices();
	echo 'Indices rebuilt.';
	exit(0);
}
if ($tokens[2] == 'triggerall')
{
	$store->triggerAllTriggers();
	echo 'Triggered all triggers.';
	exit(0);
}

if (count($tokens) < 4)
{
	echo 'Invalid parameters';
	exit(0);
}

function handleObject($store, $type, $key, $method) {
	if ($method == 'GET') {
		$data = $store->fetchObject($key);
		echo $data;
		exit(0);
	} else if ($method == 'POST') {
		$data = file_get_contents("php://input");
		if ($data == '')
		{
			echo 'Invalid data.';
			exit(0);
		}

		if ($store->storeObject($type, $key, $data))
			echo $data;
		else
			echo 'Error storing object.';
		exit(0);
	} else if ($method == 'PUT') {
		$data = file_get_contents("php://input");
		if ($data == '')
		{
			echo 'Invalid data.';
			exit(0);
		}

		if ($store->updateObject($type, $key, $data))
			echo $data;
		else
			echo 'Error storing object.';
		exit(0);
	} else if ($method == 'DELETE') {
		if ($store->deleteObject($type, $key))
			echo '{}';
		else
			echo 'Error deleting object.';
		exit(0);
	}
}

if ($tokens[2] == 'type')
{
	$type = $tokens[3];
	if ($type == '')
	{
		echo 'Invalid type.';
		exit(0);
	}
	if (count($tokens) > 4)
	{
		$key = $tokens[4];
		if ($key == '')
		{
			echo 'Invalid key.';
			exit(0);
		}

		handleObject($store, $type, $key, $method);
	} else {
		if ($method == 'GET')
		{
			$data = $store->fetchAll($type);
			echo json_encode($data);
			exit(0);
		}
	}
}
if ($tokens[2] == 'obj')
{
	$key = $tokens[3];
	if ($key == '')
	{
		echo 'Invalid key.';
		exit(0);
	}

	$type = $store->getObjectType($key);
	if ($type == '')
	{
		echo 'Could not read type.';
		exit(0);
	}

	handleObject($store, $type, $key, $method);
}
if ($tokens[2] == 'index')
{
	$index = $tokens[3];
	if ($index == '')
	{
		echo 'Invalid index.';
		exit(0);
	}

	if (count($tokens) > 4)
	{
		$value = $tokens[4];
		if ($value == '')
		{
			echo 'Invalid value.';
			exit(0);
		}

		$data = $store->fetchObjectsFromIndex($index, $value, 0);
		echo json_encode($data);
		exit(0);
	}

	$data = $store->fetchObjectsFromIndex($index, '*', 0);
	echo json_encode($data);
	exit(0);
}

echo 'Invalid parameters';
exit(0);

?>
