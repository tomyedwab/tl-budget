<?php

class SimpleStoreObjectIndex {
	function __construct($name, $types, $path)
	{
		$this->name = $name;
		$this->types = $types;
		$this->path = $path;
		$this->identifier = $name . '_' . substr(hash('crc32',$path,FALSE), 4, 4);
	}

	function clearIndex($con)
	{
		$sql = "DELETE FROM `indices` WHERE `id`='$this->identifier'";
		//error_log("Key collision search: $sql");
		mysql_query($sql);
		if (mysql_error($con) != "")
		{
			error_log("Query failed. Bailing...");
			return false;
		}
	}

    function clearDataFromIndex($con, $key, $domain)
    {
		$sql = "DELETE FROM `indices` WHERE `id`='$this->identifier' AND `ref`='$key' AND `domain`='$domain';";
		mysql_query($sql);
		if (mysql_error($con) != "")
		{
			error_log("Failed to remove element from index '$this->identifier': $error");
			return false;
		}
        return true;
    }

	function insertDataIntoIndex($con, $timestamp, &$index_changeset, $key, $domain, $valueUNSAFE)
	{
        $value = mysql_real_escape_string($valueUNSAFE);
        $sql = "INSERT INTO `indices` (`id`,`value`,`ref`,`domain`,`ts`) VALUES ('$this->identifier','$value','$key','$domain', '$timestamp');";
        mysql_query($sql);
        $error = mysql_error($con);
        if ($error != "")
        {
            error_log("Failed to add element to index '$this->identifier': $error");
            return false;
        }

        if (!isset($index_changeset[$this->name]))
            $index_changeset[$this->name] = array();
        $index_changeset[$this->name][$key] = $valueUNSAFE;

		return true;
	}

	function getDataByPath($json, $path)
	{
		//error_log("getDataByPath $path");
		if ($path[0] == '.')
		{
			$fieldName = strtok(substr($path, 1), ".[");
			//error_log("Looking for field $fieldName");
			if (isset($json[$fieldName]))
			{
				//error_log("Found field $fieldName");
				return $this->getDataByPath($json[$fieldName], substr($path, 1+strlen($fieldName)));
			}
		}
		else if ($path[0] == '[')
		{
			$index = strtok(substr($path, 1), "]");
			$child_path = substr($path, 2+strlen($index));
			if ($index == '*')
			{
				//error_log("Getting all children");
				$ret = array();
				foreach ($json as $child)
				{
					array_splice($ret, 0, 0, $this->getDataByPath($child, $child_path));
				}
				return $ret;
			}
			else
			{
				$ret = array();
				$index_list = preg_split('/\|/', $index);
				foreach ($index_list as $curindex)
				{
					//error_log("Looking for index $curindex");
					if (isset($json[$curindex]))
						array_splice($ret, 0, 0, $this->getDataByPath($json[$curindex], $child_path));
				}
				return $ret;
			}
		}

		$ret = array();
		if (is_string($json))
			$ret[] = strtolower($json);
        else if (is_numeric($json))
            $ret[] = $json;
		return $ret;
	}

    function indexProcessObjectChangeset($con, $domain, $object_changeset, &$index_changeset)
    {
        //error_log("Updating index $this->name...");
		foreach ($this->types as $idxType)
        {
            if (isset($object_changeset[$idxType])) {
                foreach ($object_changeset[$idxType] as $key => $data) {
                    //error_log("Updating index for $key / $data");
                    if (!$this->clearDataFromIndex($con, $key, $domain))
                    {
                        return false;
                    }

                    $data_json = json_decode($data, true);
                    $output_data = $this->getDataByPath($data_json, $this->path);
                    if ($output_data != Null)
                    {
                        foreach ($output_data as $valueUNSAFE)
                        {
                            if (!$this->insertDataIntoIndex($con, $object_changeset["timestamp"], $index_changeset, $key, $domain, $valueUNSAFE))
                            {
                                return false;
                            }
                        }
                    } else {
                        error_log("No data for element in index '$this->identifier'");
                    }
                }
            }
        }

        return true;
    }

	function fetchObjects($con, $domain, $value, $time)
	{
		//error_log("Searching for value $value in index '$this->identifier'");

		$ret = array();

		$sql = "SELECT o.key, o.type, o.data, i.value FROM `indices` i LEFT JOIN `objects` o ON i.ref=o.key WHERE i.id='$this->identifier' AND i.domain='$domain'";
		if ($value != "*")
			$sql .= " AND i.value='$value'";
		if ($time > 0)
			$sql .= " AND UNIX_TIMESTAMP(o.ts) > $time";
		$sql .= " ORDER BY i.value";

		//error_log("Doing index query: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($con);
		if ($error != "")
		{
			error_log("Failed search index $this->identifier: $error");
			return $ret;
		}

		while ($row = mysql_fetch_row($results))
		{
			$key = $row[0];
			$type = $row[1];
			$data = $row[2];
            $value = $row[3];
			//error_log("Found data $data");
			if (!isset($ret[$type]))
				$ret[$type] = array();
			$ret[$type][$key] = array();
            $ret[$type][$key][0] = $data;
            $ret[$type][$key][1] = $value;
		}
		return $ret;
	}
};

class SimpleStoreSumIndex
{
	function __construct($name, $group_index, $subgroup_index, $sum_index)
    {
		$this->name = $name;
        $this->group_index = $group_index;
        $this->subgroup_index = $subgroup_index;
        $this->sum_index = $sum_index;

        $subgroup_index_name = "*";
        if ($this->subgroup_index != null)
            $subgroup_index_name = $this->subgroup_index->name;
            
		$this->identifier = $name . '_' . substr(hash('crc32',$group_index->name,FALSE), 4, 4) . substr(hash('crc32',$subgroup_index_name,FALSE), 4, 4) . substr(hash('crc32',$sum_index->name,FALSE), 4, 4);
    }

    function clearDataFromIndex($con, $domain)
    {
		$sql = "DELETE FROM `sums` WHERE `id`='$this->identifier' AND `domain`='$domain';";
		mysql_query($sql);
		if (mysql_error($con) != "")
		{
			error_log("Failed to remove element from sum index '$this->identifier': $error");
			return false;
		}
        return true;
    }

	function insertDataIntoIndex($con, $domain, $timestamp, $value, $value2, $amount)
	{
        $sql = "INSERT INTO `sums` (`id`,`value`,`subvalue`,`sum`,`domain`,`ts`) VALUES ('$this->identifier','$value','$value2','$amount','$domain','$timestamp');";
        mysql_query($sql);
        $error = mysql_error($con);
        if ($error != "")
        {
            error_log("Failed to add element to sum index '$this->identifier': $error");
            return false;
        }

		return true;
	}

    function indexProcessIndexChangeset($con, $domain, $timestamp, $index_changeset)
    {
        if (isset($index_changeset[$this->group_index->name]) || isset($index_changeset[$this->sum_index->name]) ||
            ($this->subgroup_index != null && isset($index_changeset[$this->subgroup_index->name])))
        {
            error_log("Updating sum index $this->name.");

            $this->clearDataFromIndex($con, $domain);

            $group_identifier = $this->group_index->identifier;
            $sum_identifier = $this->sum_index->identifier;

            if ($this->subgroup_index != null)
            {
                $subgroup_identifier = $this->subgroup_index->identifier;
                $sql = "SELECT i1.value, i2.value, SUM(isum.value) FROM `indices` i1 JOIN `indices` i2 ON i1.ref=i2.ref JOIN `indices` isum ON i1.ref=isum.ref WHERE i1.id='$group_identifier' AND i2.id='$subgroup_identifier' AND isum.id='$sum_identifier' AND i1.domain='$domain' GROUP BY i1.value, i2.value";
            } else {
                $sql = "SELECT i1.value, '*', SUM(isum.value) FROM `indices` i1 JOIN `indices` isum ON i1.ref=isum.ref WHERE i1.id='$group_identifier' AND isum.id='$sum_identifier' AND i1.domain='$domain' GROUP BY i1.value";
            }

            error_log("Doing index query: $sql");
            $results = mysql_query($sql);
            $error = mysql_error($con);
            if ($error != "")
            {
                error_log("Failed search index $this->identifier: $error");
                return false;
            }

            while ($row = mysql_fetch_row($results))
            {
                $value = $row[0];
                $value2 = $row[1];
                $sum = $row[2];
                if (!$this->insertDataIntoIndex($con, $domain, $timestamp, $value, $value2, $sum))
                    return false;
            }
        }

        return true;
    }

	function fetchSums($con, $domain, $value, $time)
	{
		$ret = array();

		$sql = "SELECT s.value, s.subvalue, s.sum FROM `sums` s WHERE s.id='$this->identifier' AND s.domain='$domain'";
		if ($value != "*")
			$sql .= " AND s.value='$value'";
		if ($time > 0)
			$sql .= " AND UNIX_TIMESTAMP(s.ts) > $time";
		$sql .= " ORDER BY s.value";

		error_log("Doing index query: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($con);
		if ($error != "")
		{
			error_log("Failed search index $this->identifier: $error");
			return $ret;
		}

		while ($row = mysql_fetch_row($results))
		{
			$value = $row[0];
            $subvalue = $row[1];
			$sum = $row[2];
            if ($subvalue == '*')
            {
                $ret[$value] = $sum;
            }
            else
            {
                if (!isset($ret[$value]))
                    $ret[$value] = array();
                $ret[$value][$subvalue] = $sum;
            }
		}

		return $ret;
	}
}

class SimpleStoreTrigger
{
	function __construct($name, $type)
	{
		$this->name = $name;
		$this->type = $type;
	}

	function postUpdateCallback($store, $type, $key, $data)
	{
	}
}

class SimpleStore
{
	function __construct($username, $password, $database, $domain)
	{
		//error_log("Connecting to DB $database");

		$this->con = mysql_connect('localhost', $username, $password);
        $this->domain = $domain;
		$this->object_indices = array();
		$this->sum_indices = array();
		$this->triggers = array();
        $this->triggerStateIndex = 0;
        $this->currentChangeset = null;
		if (!$this->con)
		{
			die('Could not connect: ' . mysql_error());
		}
		mysql_select_db($database, $this->con);
		error_log("Connected to $database. Using domain \"$this->domain\".");
	}

    function setDomainOverride($domain)
    {
        $this->domain = $domain;
    }

	function addObjectIndex($index)
	{
		$this->object_indices[$index->name] = $index;
	}

	function addSumIndex($index)
	{
		$this->sum_indices[$index->name] = $index;
	}

	function addTrigger($trigger)
	{
		$this->triggers[$trigger->name] = $trigger;
	}

    function startChangeset()
    {
        if ($this->currentChangeset != null)
        {
            error_log("Attempted to start a changeset while in another changeset!");
            return;
        }

        $this->currentChangeset = array();
        $this->currentChangeset["objects"] = array();
        $this->currentChangeset["indices"] = array();

        $row = mysql_fetch_row(mysql_query('SELECT UNIX_TIMESTAMP(NOW())'));
        $this->currentChangeset['timestamp'] = $row[0];

		mysql_query("START TRANSACTION;");
    }

    function endChangeset()
    {
        if ($this->currentChangeset == null)
        {
            error_log("Attempted to end a changeset when this is none active!");
            return;
        }

        $index_changeset = array();

        error_log("Processing object indices...");
		foreach ($this->object_indices as $index)
        {
            if (!$index->indexProcessObjectChangeset($this->con, $this->domain, $this->currentChangeset, $index_changeset))
            {
                mysql_query("ROLLBACK;");
            }
        }

        error_log("Processing sum indices...");
		foreach ($this->sum_indices as $index)
        {
            if (!$index->indexProcessIndexChangeset($this->con, $this->domain, $this->currentChangeset["timestamp"], $index_changeset))
            {
                mysql_query("ROLLBACK;");
            }
        }

        mysql_query("COMMIT;");

        $this->currentChangeset = null;
    }

    function abortChangeset()
    {
        if ($this->currentChangeset != null)
        {
            mysql_query("ROLLBACK;");
            $this->currentChangeset = null;
        }
    }

	function createDataTables()
	{
		$sql = <<<EOSQL
CREATE TABLE `objects` (
	`key` varchar(32) NOT NULL,
	`type` varchar(32) NOT NULL,
    `domain` varchar(32) NOT NULL,
	`ts` integer,
	`data` blob,
	KEY `key` (`key`),
	PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOSQL;
		error_log("Creating objects table: $sql");
		mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error creating objects table: $error");
		}

		$sql = <<<EOSQL
CREATE TABLE `indices` (
	`id` varchar(128) NOT NULL,
	`value` varchar(32) NOT NULL,
	`ref` varchar(32) NOT NULL,
    `domain` varchar(32) NOT NULL,
	`ts` integer,
	KEY `ref` (`ref`),
	CONSTRAINT `index-ref` FOREIGN KEY (`ref`) REFERENCES `objects` (`key`) ON DELETE CASCADE ON UPDATE CASCADE,
	INDEX `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOSQL;

		error_log("Creating indices table: $sql");
		mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error creating index table: $error");
		}

		$sql = <<<EOSQL
CREATE TABLE `sums` (
	`id` varchar(128) NOT NULL,
	`value` varchar(32) NOT NULL,
	`subvalue` varchar(32) NOT NULL,
	`sum` varchar(32) NOT NULL,
    `domain` varchar(32) NOT NULL,
	`ts` integer,
	INDEX `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOSQL;

		error_log("Creating sums table: $sql");
		mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error creating sum table: $error");
		}
	}

	function clearObjects($typeUNSAFE)
	{
		$type = mysql_real_escape_string($typeUNSAFE);

		$sql = "DELETE FROM `objects` WHERE `type`='$type' AND `domain`='$this->domain'";
		mysql_query($sql);
	}

	function storeObject($typeUNSAFE, $keyUNSAFE, $dataUNSAFE)
	{
        if ($this->currentChangeset == null)
        {
            error_log("Attempted to store an object while not in a changeset!");
            return;
        }

        // TO DO: Validate key begins with domain name

		$type = mysql_real_escape_string($typeUNSAFE);
		$key = mysql_real_escape_string($keyUNSAFE);
		$data = mysql_real_escape_string($dataUNSAFE);

		// Look for object already in table
		$sql = "SELECT o.key FROM `objects` o WHERE o.key = '$key' AND o.domain = '$this->domain';";
		//error_log("Key collision search: $sql");
		$results = mysql_query($sql);
		if (mysql_error($this->con) != "")
		{
			error_log("Query failed. Creating objects table...");
			$this->createDataTables();
			error_log("Re-running search: $sql");
			$results = mysql_query($sql);
			$error = mysql_error($this->con);
			if ($error != "")
			{
				error_log("Search for key collision failed: $error");
				return false;
			}
		}
		if (mysql_num_rows($results) > 0)
		{
			error_log("Key collision found. Bailing.");
			return false;
		}

        $timestamp = $this->currentChangeset["timestamp"];
		$sql = "INSERT INTO `objects` (`key`,`type`,`domain`,`data`,`ts`) VALUES ('$key','$type','$this->domain','$data',$timestamp);";
		//error_log("Inserting new object: $sql");
		$results = mysql_query($sql);
		if (mysql_error($this->con) != "")
		{
			error_log("Error inserting new object. Bailing.");
			return false;
		}

		// Update changeset
        if (!isset($this->currentChangeset[$type]))
            $this->currentChangeset[$type] = array();
        $this->currentChangeset[$type][$key] = $dataUNSAFE;

		// Call triggers
		foreach ($this->triggers as $trigger)
		{
			if ($trigger->type == $type)
				$trigger->postUpdateCallback($this, $type, $key, json_decode($dataUNSAFE));
		}

        $this->pushTriggerState();
        $this->popTriggerState();

		return true;
	}

	function updateObject($typeUNSAFE, $keyUNSAFE, $dataUNSAFE)
	{
        if ($this->currentChangeset == null)
        {
            error_log("Attempted to store an object while not in a changeset!");
            return;
        }

        // TO DO: Validate key begins with domain name

		$type = mysql_real_escape_string($typeUNSAFE);
		$key = mysql_real_escape_string($keyUNSAFE);
		$data = mysql_real_escape_string($dataUNSAFE);
        $timestamp = $this->currentChangeset["timestamp"];

		$sql = "UPDATE `objects` SET `data`='$data',`ts`=$timestamp WHERE `type`='$type' AND `key`='$key' AND `domain`='$this->domain';";
		//error_log("Updating object: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error updating new object: $error");
			return false;
		}

		// Update changeset
        if (!isset($this->currentChangeset[$type]))
            $this->currentChangeset[$type] = array();
        $this->currentChangeset[$type][$key] = $dataUNSAFE;

		// Call triggers
		foreach ($this->triggers as $trigger)
		{
			if ($trigger->type == $type)
				$trigger->postUpdateCallback($this, $type, $key, json_decode($dataUNSAFE));
		}

        $this->pushTriggerState();
        $this->popTriggerState();

		return true;
	}

	function deleteObject($typeUNSAFE, $keyUNSAFE)
	{
		return $this->updateObject($typeUNSAFE, $keyUNSAFE, '{}');
	}

	function fetchObject($keyUNSAFE)
	{
		$key = mysql_real_escape_string($keyUNSAFE);

		// Look for object in table
		$sql = "SELECT o.data FROM `objects` o WHERE o.key='$key' AND o.domain='$this->domain';";
		//error_log("Data search: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error fetching object: $error");
			return '';
		}

        if (mysql_num_rows($results) == 0)
        {
			return '';
        }

		$data = mysql_result($results, 0);
		if (!$data)
		{
			//error_log("Object not found. Bailing.");
			return '';
		}

		return $data;
	}

	function getObjectType($keyUNSAFE)
	{
		$key = mysql_real_escape_string($keyUNSAFE);

		// Look for object in table
		$sql = "SELECT o.type FROM `objects` o WHERE o.key='$key' AND o.domain='$this->domain';";
		//error_log("Type search: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error reading object type: $error");
			return '';
		}

		$type = mysql_result($results, 0);
		if (!$type)
		{
			//error_log("Object not found. Bailing.");
			return '';
		}

		return $type;
	}

	function fetchAll($typeUNSAFE)
	{
		$type = mysql_real_escape_string($typeUNSAFE);

		$ret = array();
		$ret["type"] = $type;
		$ret["data"] = array();

		$sql = "SELECT o.key, o.data FROM `objects` o WHERE o.type='$type' AND o.domain='$this->domain'";

		$results = mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Failed get object type $type: $error");
			return $ret;
		}

		while ($row = mysql_fetch_row($results))
		{
			$key = $row[0];
			$data = $row[1];
			$ret["data"][$key] = json_decode($data);
		}
		return $ret;
	}

	function fetchObjectsFromIndex($indexname, $valueUNSAFE, $timeUNSAFE = 0)
	{
		$value = mysql_real_escape_string(strtolower($valueUNSAFE));
		$time = $timeUNSAFE*1;

		if (!isset($this->object_indices[$indexname]))
		{
			error_log("Query on invalid index $indexname. Bailing.");
			return array();
		}

		$index = $this->object_indices[$indexname];
		return $index->fetchObjects($this->con, $this->domain, $value, $time);
	}

    function fetchSumsFromIndex($indexname, $valueUNSAFE, $timeUNSAFE = 0)
    {
		$value = mysql_real_escape_string(strtolower($valueUNSAFE));
		$time = $timeUNSAFE*1;

		if (!isset($this->sum_indices[$indexname]))
		{
			error_log("Query on invalid index $indexname. Bailing.");
			return array();
		}

		$index = $this->sum_indices[$indexname];
		return $index->fetchSums($this->con, $this->domain, $value, $time);
    }

	function rebuildAllIndices()
	{
    /*
		$types = array();

		foreach ($this->indices as $index)
		{
			$index->clearIndex($this->con);
			foreach ($index->types as $idxType)
			{
				if (!isset($types[$idxType]))
					$types[$idxType] = array();
				$types[$idxType][] = $index;
			}
		}

		foreach ($types as $typename => $list)
		{
			error_log("Building index for type $typename...");

			$sql = "SELECT `key`, `domain`, `data` FROM `objects` WHERE type='$typename';";
			$results = mysql_query($sql);
			if (mysql_error($this->con) != "")
			{
				error_log("Failed to get all elements of type $typename. Bailing...");
				return false;
			}

			while ($row = mysql_fetch_row($results))
			{
				$key = $row[0];
                $domain = $row[1];
				$data = $row[2];
				error_log("Indexing $typename: $key");
				foreach ($list as $index)
				{
					error_log("Index: $index->identifier");
					if (!$index->insertObjectIntoIndex($this->con, $key, $domain, $data))
						return false;
				}
			}
		}
        */
		return true;
	}

	function updateIndicesForObject($keyUNSAFE)
	{
    /*
		$key = mysql_real_escape_string($keyUNSAFE);

		// Look for object in table
		$sql = "SELECT o.type, o.domain, o.data FROM `objects` o WHERE o.key='$key';";
		error_log("Type search: $sql");
		$results = mysql_query($sql);
		$error = mysql_error($this->con);
		if ($error != "")
		{
			error_log("Error fetching object: $error");
			return '';
		}

		if ($row = mysql_fetch_row($results))
		{
			$type = $row[0];
            $domain = $row[1];
			$data = $row[2];
		} else {
			error_log("Object not found. Bailing.");
			return false;
		}

		error_log("Updating single object indices: $key $data");

		mysql_query("START TRANSACTION;");

		// Update indices
		foreach ($this->indices as $index)
		{
			if (!$index->indexUpdateObject($this->con, $key, $type, $domain, $data))
			{
				mysql_query("ROLLBACK;");
				return false;
			}
		}

		mysql_query("COMMIT;");
        */
		return true;
	}

	function triggerAllTriggers()
	{
        $types = Array();
		foreach ($this->triggers as $trigger)
		{
            if (!isset($types[$trigger->type]))
            {
                $types[$trigger->type] = True;
            }
		}
        foreach ($types as $type => $true)
        {
            error_log("Triggering all for type $type");
            $objects = $this->fetchAll($type);
            foreach ($objects["data"] as $key => $data)
            {
                foreach ($this->triggers as $trigger)
                {
                    if ($trigger->type == $type)
                    {
                        $trigger->postUpdateCallback($this, $type, $key, $data);
                    }
                }
            }
        }

        foreach ($this->triggers as $trigger)
        {
            $trigger->transactionCompleteCallback($this);
        }
	}

    function pushTriggerState()
    {
        $this->triggerStateIndex++;
    }
    function popTriggerState()
    {
        if ($this->triggerStateIndex > 0)
        {
            if ($this->triggerStateIndex == 1)
            {
                foreach ($this->triggers as $trigger)
                {
                    $trigger->transactionCompleteCallback($this);
                }
            }
            $this->triggerStateIndex--;
        }
    }
};

?>
