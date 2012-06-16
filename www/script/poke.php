<?php

include('tl_budget.php');

if (!$_SESSION['db_is_logged_in'])
{
	echo 'Must be logged in!';
	exit(0);
}

$data_json = json_decode($_REQUEST['data'], true);

$ret = array();

# Apply changes

$store->startChangeset();

foreach ($data_json['puts'] as $put)
{
    $key = $put['key'];
    $type = $put['type'];
    $data = $put['data'];
    if ($put['isUpdate']) {
        if (!$store->updateObject($type, $key, $data))
        {
            echo 'Error updating object $key';
            $store->abortChangeset();
            exit(0);
        }
    } else {
        if (!$store->storeObject($type, $key, $data))
        {
            echo 'Error updating object $key';
            $store->abortChangeset();
            exit(0);
        }
    }
}

$store->endChangeset();

# Get current timestamp

$results = mysql_query('SELECT UNIX_TIMESTAMP(NOW())');
$row = mysql_fetch_row($results);
$ret['ts'] = $row[0];

# Return all changes in subscribed indices since timestamp given

$ret['objects'] = array();
$ret['indices'] = array();
$ret['sums'] = array();
foreach ($data_json['indices'] as $index)
{
	$index_name = $index['index'];
	$index_value = $index['value'];
	$index_key = "$index_name=$index_value";

	$res = $store->fetchObjectsFromIndex($index_name, $index_value, $index['ts']);

	$ret['indices'][$index_key] = array();

	foreach ($res as $type => $list)
	{
        if (!isset($ret[$type]))
            $ret['objects'][$type] = array();
		foreach ($list as $key => $value_pair)
		{
            $data = $value_pair[0];
            $value = $value_pair[1];
			if (!isset($ret['objects'][$type][$key]))
				$ret['objects'][$type][$key] = json_decode($data);
            $ret_obj = array();
            $ret_obj['key'] = $key;
            $ret_obj['value'] = $value;
			$ret['indices'][$index_key][] = $ret_obj;
		}
	}
}
foreach ($data_json['sums'] as $sum_index)
{
	$sum_name = $sum_index['sum'];
	$sum_value = $sum_index['value'];
	$sum_key = "$sum_name=$sum_value";

	$res = $store->fetchSumsFromIndex($sum_name, $sum_value, $sum_index['ts']);

	$ret['sums'][$sum_key] = $res;
}

echo json_encode($ret);

?>
