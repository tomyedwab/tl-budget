<?php

include('tl_budget.php');

session_start();

if (!$_SESSION['db_is_logged_in'])
{
	echo 'Must be logged in!';
	exit(0);
}

$q = $_REQUEST['q'];

error_log("Query: $q");

if ($q == 'store') {
	$type = $_REQUEST['type'];
	$key = $_REQUEST['key'];
	$data = $_REQUEST['data'];
	if ($type == '')
	{
		echo 'Invalid type.';
		exit(0);
	}
	if ($key == '')
	{
		echo 'Invalid key.';
		exit(0);
	}
	if ($data == '')
	{
		echo 'Invalid data.';
		exit(0);
	}

	if ($store->storeObject($type, $key, $data))
		echo 'Successfully stored object.';
	else
		echo 'Error storing object.';
}
if ($q == 'update') {
	$type = $_REQUEST['type'];
	$key = $_REQUEST['key'];
	$data = $_REQUEST['data'];
	if ($type == '')
	{
		echo 'Invalid type.';
		exit(0);
	}
	if ($key == '')
	{
		echo 'Invalid key.';
		exit(0);
	}
	if ($data == '')
	{
		echo 'Invalid data.';
		exit(0);
	}

	if ($store->updateObject($type, $key, $data))
		echo 'Successfully updated object.';
	else
		echo 'Error updating object.';
}
if ($q == 'fetch') {
	$key = $_REQUEST['key'];
	if ($key == '')
	{
		echo 'Invalid key.';
		exit(0);
	}

	$data = $store->fetchObject($key);
	echo $data;
}
if ($q == 'fetch_all') {
	$type = $_REQUEST['type'];
	if ($type == '')
	{
		echo 'Invalid type.';
		exit(0);
	}

	$data = $store->fetchAll($type);
	echo json_encode($data);
}
if ($q == 'clear_all') {
	$type = $_REQUEST['type'];
	if ($type == '')
	{
		echo 'Invalid type.';
		exit(0);
	}

	$store->clearObjects($type);
	echo "{}";
}
if ($q == 'fetch_index') {
	$index = $_REQUEST['index'];
	$value = $_REQUEST['value'];
	if ($index == '')
	{
		echo 'Invalid index.';
		exit(0);
	}
	if ($value == '')
	{
		echo 'Invalid value.';
		exit(0);
	}

	$data = $store->fetchObjectsFromIndex($index, $value, 0);
	echo json_encode($data);
}
if ($q == 'rebuildall') {
	$store->rebuildAllIndices();
	echo 'Indices rebuilt.';
}
if ($q == 'rebuildsingle') {
	$key = $_REQUEST['key'];
	if ($key == '')
	{
		echo 'Invalid key.';
		exit(0);
	}

	if (!$store->updateIndicesForObject($key))
	{
		echo 'Object not found.';
		exit(0);
	}
	echo "Indices rebuilt for object $key.";
}
if ($q == 'triggerall') {
	$store->triggerAllTriggers();
	echo 'Triggered all triggers.';
}

?>
