<?php
$_quickdb_multirow_lookup = null;
$_quickdb_dbh = null;

//Just a library for quickly interacting with a database
function connectToDatabase($dbname,$host,$user,$pass,$sslmode,$sslrootcert) {
	global $_quickdb_dbh;
	$_quickdb_dbh = pg_pconnect(
		"dbname=$dbname " .
		"host=$host " .
		"user=$user " .
		"password=$pass " .
		"sslmode=$sslmode " .
		"sslrootcert=$sslrootcert"
	);
	return $_quickdb_dbh;
}

function disconnectFromDatabase() {
	global $_quickdb_dbh;
	pg_close($_quickdb_dbh);
}

function getRecord($table,$pk_field,$lookup_key) {
	global $_quickdb_dbh;

	//If the primary key type is non-numeric we need to quote it
	if (is_numeric($lookup_key)) {
		$query = "SELECT * FROM $table WHERE $pk_field=$lookup_key";
	} else {
		$query = "SELECT * FROM $table WHERE $pk_field='" . pg_escape_string($lookup_key) . "'";
	}

	//Perform the query to get the row to be edited from the database
	//If the query fails or returns more or less than one row die
	//otherwise get the associative array of the columns
	$result = pg_query($_quickdb_dbh,$query);
	if (!$result) {
		cleanUp();
		die("ERROR: Couldn't perform query $query");
	}
	$num_rows = pg_num_rows($result);
	if ($num_rows != 1) {
		return false;
	}

	return pg_fetch_assoc($result);
}

function startMultiRowLookup($query) {
	global $_quickdb_dbh,$_quickdb_multirow_lookup;

	$_quickdb_multirow_lookup = pg_query($_quickdb_dbh,$query);

	if ($_quickdb_multirow_lookup) {
		return true;
	} else {
		return false;
	}
}

function getNextRowAssoc() {
	global $_quickdb_multirow_lookup;

	return pg_fetch_assoc($_quickdb_multirow_lookup);
}

function getNextRow() {
	global $_quickdb_multirow_lookup;

	return pg_fetch_row($_quickdb_multirow_lookup);
}

function numRows() {
	global $_quickdb_multirow_lookup;
	return pg_num_rows($_quickdb_multirow_lookup);
}

function getAllRows($query, $value, $key = null) {
	startMultiRowLookup($query);

	$results = array();

	while ($row = getNextRowAssoc())
	{
		if ($key == null)
		{
			$results[] = $row[$value];
		}
		else
		{
			$results[$row[$key]] = $row[$value];
		}
	}

	return $results;
}
?>
