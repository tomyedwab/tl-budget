<?php

include('simplestore.php');

// This unit test requires a database set up with the following username/password and database:

$store = new SimpleStore('unittest', 'unittest1234', 'unittestdb', 'unittest');

mysql_query('DROP TABLE IF EXISTS `indices`');
$error = mysql_error($store->con);
if ($error != "")
{
	print "Failed to clear indices table";
	die;
}

mysql_query('DROP TABLE IF EXISTS `objects`');
$error = mysql_error($store->con);
if ($error != "")
{
	print "Failed to clear indices table";
	die;
}

function doTest($value, $data, $test_name)
{
	if ($value) {
		print "<span style=\"color: #080\">Test succeeded: $test_name</span><BR>";
	} else { 
		print "<span style=\"color: #800\">Test failed: $test_name, data:";
		var_dump($data);
		print "</span>";
		die;
	}
}

function sortedKeys($array)
{
    $keys = array_keys($array);
    sort($keys);
    return $keys;
}

$testData1 = '{"food":"bacon","breakfast":"true"}';
$testData2 = '{"food":"waffles","breakfast":"true","optional":{"review":"great!"}}';
$testData3 = '{"food":"pancakes","breakfast":"true","also_optional":{"review":"great!"}}';
$testData4 = '{"user":"moe","comments":[{"comment":"wow","score":5},{"comment":"dude","score":6}]}';
$testData5 = '{"user":"larry","comments":[{"comment":"dude","score":4},{"comment":"awesome","score":1}]}';
$testData6 = '{"user":"curly","comments":[{"comment":"awesome","score":8}]}';

$store->addObjectIndex(new SimpleStoreObjectIndex('simple_property1', array('TypeA'), '.food'));
$store->addObjectIndex(new SimpleStoreObjectIndex('simple_property2', array('TypeA'), '.breakfast'));
$store->addObjectIndex(new SimpleStoreObjectIndex('simple_property3', array('TypeA','TypeB'), '.food'));

$store->addObjectIndex(new SimpleStoreObjectIndex('complex_property1', array('TypeA'), '.optional.review'));
$store->addObjectIndex(new SimpleStoreObjectIndex('complex_property2', array('TypeA','TypeB'), '[optional|also_optional].review'));
$store->addObjectIndex(new SimpleStoreObjectIndex('complex_property3', array('TypeC'), '.comments[1].comment'));
$store->addObjectIndex($complex_property4 = new SimpleStoreObjectIndex('complex_property4', array('TypeC'), '.comments[*].comment'));
$store->addObjectIndex($simple_property4 = new SimpleStoreObjectIndex('simple_property4', array('TypeC'), '.user'));
$store->addObjectIndex($sum_property1 = new SimpleStoreObjectIndex('sum_property1', array('TypeC'), '.comments[*].score'));

$store->addSumIndex(new SimpleStoreSumIndex('auto_sum1', $complex_property4, null, $sum_property1));
$store->addSumIndex(new SimpleStoreSumIndex('auto_sum2', $complex_property4, $simple_property4, $sum_property1));

// Test fetching a non-existant object from the database

$data = $store->fetchObject('mytestkey');
doTest(($data == ''), $data, "Fetching non-existent object");

// Test storing an object

$store->startChangeset();

$store->storeObject('TypeA', 'A:0001', $testData1);
$data = $store->fetchObject('A:0001');
doTest(($data == $testData1), $data, "Store an object in the database (A)");

$store->storeObject('TypeB', 'B:0001', $testData3);
$data = $store->fetchObject('B:0001');
doTest(($data == $testData3), $data, "Store an object in the database (B)");

$store->endChangeset();

// Test updating an object

$store->startChangeset();

$store->updateObject('TypeA', 'A:0001', $testData2);
$data = $store->fetchObject('A:0001');
doTest(($data == $testData2), $data, "Update an object in the database");

$store->endChangeset();

// Verify overwriting an object doesn't work

$store->startChangeset();

$store->storeObject('TypeB', 'A:0001', $testData3);
$data = $store->fetchObject('A:0001');
doTest(($data == $testData2), $data, "Overwriting an object fails");

$store->endChangeset();

// Clearing objects by type

$store->startChangeset();

$store->clearObjects('TypeA');
$data = $store->fetchObject('A:0001');
doTest(($data == ''), $data, "Clearing objects by type (A)");

$data = $store->fetchObject('B:0001');
doTest(($data == $testData3), $data, "Clearing objects by type (B)");

$store->endChangeset();

// Test indices

$store->startChangeset();

$store->storeObject('TypeA', 'A:0001', $testData1);
$store->storeObject('TypeA', 'A:0002', $testData2);

$store->endChangeset();

$objects = $store->fetchObjectsFromIndex('simple_property1', 'grapes');
doTest((array_keys($objects) == array()), $objects, "Simple index property for nonexistent value");

$objects = $store->fetchObjectsFromIndex('simple_property1', 'bacon');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Simple index property (A, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0001')), $objects, "Simple index property (A, KEYS)");
doTest(($objects['TypeA']['A:0001'][0] == $testData1), $objects, "Simple index property (A, DATA)");

$objects = $store->fetchObjectsFromIndex('simple_property1', 'waffles');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Simple index property (B, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0002')), $objects, "Simple index property (B, KEYS)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Simple index property (B, DATA)");

$objects = $store->fetchObjectsFromIndex('simple_property2', 'true');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Simple index property (C, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0001','A:0002')), $objects, "Simple index property (C, KEYS)");
doTest(($objects['TypeA']['A:0001'][0] == $testData1), $objects, "Simple index property (C, DATA 1)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Simple index property (C, DATA 2)");

$store->startChangeset();

$store->storeObject('TypeB', 'B:0002', $testData2);

$store->endChangeset();

$objects = $store->fetchObjectsFromIndex('simple_property3', 'bacon');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Multiple type index property (A, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0001')), $objects, "Multiple type property (A, KEYS)");
doTest(($objects['TypeA']['A:0001'][0] == $testData1), $objects, "Multiple type property (A, DATA)");

$objects = $store->fetchObjectsFromIndex('simple_property3', 'waffles');
doTest((sortedKeys($objects) == array('TypeA','TypeB')), $objects, "Multiple type index property (B, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0002')), $objects, "Multiple type property (B, KEYS 1)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Multiple type property (B, DATA 1)");
doTest((sortedKeys($objects['TypeB']) == array('B:0002')), $objects, "Multiple type property (B, KEYS 2)");
doTest(($objects['TypeB']['B:0002'][0] == $testData2), $objects, "Multiple type property (B, DATA 2)");

$store->startChangeset();

$store->updateObject('TypeB', 'B:0002', $testData1);

$store->endChangeset();

$objects = $store->fetchObjectsFromIndex('simple_property3', 'waffles');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Multiple type index property (C, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0002')), $objects, "Multiple type property (C, KEYS)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Multiple type property (C, DATA)");

$objects = $store->fetchObjectsFromIndex('simple_property3', 'bacon');
doTest((sortedKeys($objects) == array('TypeA','TypeB')), $objects, "Multiple type index property (D, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0001')), $objects, "Multiple type property (D, KEYS 1)");
doTest(($objects['TypeA']['A:0001'][0] == $testData1), $objects, "Multiple type property (D, DATA 1)");
doTest((sortedKeys($objects['TypeB']) == array('B:0002')), $objects, "Multiple type property (D, KEYS 2)");
doTest(($objects['TypeB']['B:0002'][0] == $testData1), $objects, "Multiple type property (D, DATA 2)");

$objects = $store->fetchObjectsFromIndex('complex_property1', 'great!');
doTest((sortedKeys($objects) == array('TypeA')), $objects, "Complex index property (A, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0002')), $objects, "Complex index property (A, KEYS)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Complex index property (A, DATA)");

$objects = $store->fetchObjectsFromIndex('complex_property2', 'great!');
doTest((sortedKeys($objects) == array('TypeA','TypeB')), $objects, "Complex index property (B, TYPE)");
doTest((sortedKeys($objects['TypeA']) == array('A:0002')), $objects, "Complex index property (B, KEYS 1)");
doTest(($objects['TypeA']['A:0002'][0] == $testData2), $objects, "Complex index property (B, DATA 1)");
doTest((sortedKeys($objects['TypeB']) == array('B:0001')), $objects, "Complex index property (B, KEYS 2)");
doTest(($objects['TypeB']['B:0001'][0] == $testData3), $objects, "Complex index property (B, DATA 2)");

$store->startChangeset();

$store->storeObject('TypeC', 'C:0001', $testData4);
$store->storeObject('TypeC', 'C:0002', $testData5);
$store->storeObject('TypeC', 'C:0003', $testData6);

$store->endChangeset();

$objects = $store->fetchObjectsFromIndex('complex_property3', 'awesome');
doTest((sortedKeys($objects) == array('TypeC')), $objects, "Subscript index property (A, TYPE)");
doTest((sortedKeys($objects['TypeC']) == array('C:0002')), $objects, "Subscript index property (A, KEYS)");
doTest(($objects['TypeC']['C:0002'][0] == $testData5), $objects, "Subscript index property (A, DATA)");

$objects = $store->fetchObjectsFromIndex('complex_property4', 'awesome');
doTest((sortedKeys($objects) == array('TypeC')), $objects, "Subscript index property (B, TYPE)");
doTest((sortedKeys($objects['TypeC']) == array('C:0002','C:0003')), $objects, "Subscript index property (B, KEYS)");
doTest(($objects['TypeC']['C:0002'][0] == $testData5), $objects, "Subscript index property (B, DATA 1)");
doTest(($objects['TypeC']['C:0003'][0] == $testData6), $objects, "Subscript index property (B, DATA 2)");

$sums = $store->fetchSumsFromIndex('auto_sum1', '*');
doTest((sortedKeys($sums) == array('awesome','dude','wow')), $sums, "Sum index (A, KEYS)");
doTest($sums['awesome'] == 13, $sums['awesome'], "Sum index (A, awesome)");
doTest($sums['dude'] == 16, $sums['dude'], "Sum index (A, dude)");
doTest($sums['wow'] == 11, $sums['wow'], "Sum index (A, wow)");

$sums = $store->fetchSumsFromIndex('auto_sum2', 'dude');
doTest((sortedKeys($sums) == array('dude')), $sums, "Sum index (B, KEYS)");
doTest((sortedKeys($sums['dude']) == array('larry','moe')), $sums['dude'], "Sum index (B, KEYS 2)");
doTest($sums['dude']['larry'] == 5, $sums['dude']['larry'], "Sum index (B, Larry)");
doTest($sums['dude']['moe'] == 11, $sums['dude']['moe'], "Sum index (B, Moe)");

print "All tests succeeded!";

?>
