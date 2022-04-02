<?php

$servername='127.0.0.1';
$username='USERNAME';
$password='PASSWORD';
$db='DATABASE-NAME';
$tablename='table'

$fields = array( // CSV field => SQL field
	'ID' => 'id',
	'Title' => 'title',
	'Description' => 'description',
	'Created_Date' => 'createdDate',
	'Size' => 'size'
);

function is_date($value) {
	$ident = substr($value, 4, 1).substr($value, 7, 1).substr($value, 10, 1).substr($value, 13, 1).substr($value, 16, 1).substr($value, 19, 1);
	return ($ident == "--T::.");
}

function insert_rows($field_names, $rows, $table_name, $conn) {
	$sql = "REPLACE into $table_name (".implode(",", $field_names).")";
	$sql.= " VALUES \n".implode(",\n", $rows);
	$result = mysqli_query($conn, $sql);
	if($result === false) {
		echo " Error inserting the data. ".mysqli_error($conn)."\n";
		return -1;
	} else {
		return count($rows);
	}
}

function get_values($conn, $fields_definition, $csv_columns, $rowData) {
	$field_values = array();
	foreach ($fields_definition as $csv_field => $sql_field) {
		// get the column index
		$i = array_search($csv_field, $csv_columns);
		// add the value the value
		if (($i === FALSE) || empty($rowData[$i]??'')) {
			$field_values[]= "NULL";
		} else {
			$v = mysqli_real_escape_string($conn, $rowData[$i]);
			if (is_date($v)) {
				$field_values[]= "'".substr($v, 0, 10)." ".substr($v, 11, 8)."'";
			} else {
				$field_values[]= "'$v'";
			}
		}
	}
	return $field_values;
}

function load_file($filename, $fields, $table_name, $conn) {
	$total_rows = 0;
	$inserted_rows = 0;
	$rows = array();
	$file = fopen($filename, 'r');

	echo "Processing $filename..";
	if (($csv_columns = fgetcsv($file, 100000, ',')) !== FALSE) {
		$field_names = array_values($fields);

		while (($getData = fgetcsv($file, 100000, ',')) !== FALSE) {
			$rows[]="(".implode(",", get_values($conn, $fields, $csv_columns, $getData)).")";
			$total_rows++;

			if (count($rows) >= 1000) {
				$r = insert_rows($field_names, $rows, $table_name, $conn);
				echo ".";
				if ($r > 0) {
					$inserted_rows+= $r;
					$rows = array();
				} else {
					return -1;
				}
			}
		}

		if (count($rows) > 0) {
			$r = insert_rows($field_names, $rows, $table_name, $conn);
			echo ".";
			if ($r > 0) {
				$inserted_rows+= $r;
				$rows = array();
			} else {
				return -1;
			}
		}
		echo "Inserted $inserted_rows records.\n";

	} else {
		echo "Empty CSV file\n";
		$total_rows = -1;
	}
	fclose($file);
	return $inserted_rows;
}


if (!empty($argv[1])) {

	$filename = $argv[1]??'';
	if (is_file($filename)) {

		try {
			$conn = mysqli_connect($servername, $username, $password, $db);
			$count = load_file($filename, $fields, $tablename, $conn);
			if ($count >= 0) {
				exit(0);
			} else {
				exit(4);
			}
		}
		catch(exception $e) {
			echo "Connection failed: " . $e->getMessage()."\n";
			exit(3);
		}


	} else {
		echo "File not found: '$filename'\n";
			exit(2);
	}

} else {
	echo "Missing arguments\n";
	exit(1);
}
