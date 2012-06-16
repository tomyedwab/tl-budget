<?php

include('tl_budget.php');

$data_upload = file_get_contents("php://input");
$data_json = json_decode($data_upload, true);

$errors = 0;

$type = $data_json['type'];

foreach ($store->indices as $index)
{
	if ($index->type == $type)
		$index->clearIndex($store->con);
}
$store->clearObjects($type);

foreach ($data_json['data'] as $key => $data) {
	$data_str = json_encode($data);
	error_log("Found key $key, data $data_str");

	if (!$store->storeObject($type, $key, $data_str))
	{
		$errors++;
	}
}

echo "$errors errors during bulk upload.";

?>
