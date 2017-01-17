<?php
/**
 * Loads checkouts and holds into the offline circ tables
 *
 * @category lafayette_patron_conversion
 * @author Mark Noble <mark@marmot.org>
 * Date: 10/28/13
 * Time: 9:19 AM
 */
date_default_timezone_set('America/Denver');

$startTime = date('Y-m-d-H-i', time() );

$dbh = mysqli_connect('localhost', 'root', 'Ms$qlR00t', 'flatirons_new_member_loads');

$itemBarcode = "";
$itemId = null;
$getBibWithItemStmt = mysqli_prepare($dbh, 'SELECT bib, itemId from barcode_to_bib where barcode = ?');
$getBibWithItemStmt->bind_param("s", $itemBarcode);

//Process Checkouts
$checkoutsFile = "C:/web/lafayette_patron_conversion/Patron Checkouts.txt";
//Create a sql file to load into the database
$checkoutsSqlFile = "C:/web/lafayette_patron_conversion/converted/circ.dat";
$checkoutsSqlFhnd = fopen($checkoutsSqlFile, 'w');
$checkoutsFhnd = fopen($checkoutsFile, 'r');
while (($rawData = fgetcsv($checkoutsFhnd, 1000, "\t")) !== FALSE) {
	$patronBarcode = $rawData[0];
	$itemBarcode = $rawData[1];

	$now = new DateTime();
	$nowFormatted = $now->format('ymdHi');
	$dueDate = date_add($now, new DateInterval('P3W'));
	$dueDateFormatted = $dueDate->format('ymdHi');
	$sql = "o:$nowFormatted:b$itemBarcode:b$patronBarcode:$dueDateFormatted:400";
	fwrite($checkoutsSqlFhnd, "$sql\r\n");
}
fclose($checkoutsSqlFhnd);
fclose($checkoutsFhnd);

//Process holds
$bibId = null;
$holdsFile = "C:/web/lafayette_patron_conversion/Bib Level Holds.txt";
$holdsSqlFile = "C:/web/lafayette_patron_conversion/converted/{$startTime}-holds.sql";
$holdsSqlFhnd = fopen($holdsSqlFile, 'w');
$holdsFhnd = fopen($holdsFile, 'r');
while (($rawData = fgetcsv($holdsFhnd, 1000, "\t")) !== FALSE) {
	$patronName = mysql_escape_string($rawData[1]);
	$patronBarcode = $rawData[3];
	$itemBarcode = $rawData[4];

	//Get the bib from the database
	$getBibWithItemStmt->execute();
	$getBibWithItemStmt->bind_result($bibId, $itemId);

	if ($getBibWithItemStmt->fetch()){
		$trimmedItem = substr($itemId, 1, strlen($itemId) -2);
		$sql = "INSERT IGNORE INTO offline_hold (patronBarcode, bibId, itemId, status, timeEntered, patronName) VALUES ('$patronBarcode', '$bibId', '$trimmedItem', 'Not Processed', '" . time() . "', '$patronName');";
		fwrite($holdsSqlFhnd, "$sql\r\n");
	}else{
		echo("Could not find bib for barcode $itemBarcode<br/>");
	}
}
fclose($holdsFhnd);

$holdsFile = "C:/web/lafayette_patron_conversion/Item Level Holds.txt";
$holdsFhnd = fopen($holdsFile, 'r');
while (($rawData = fgetcsv($holdsFhnd, 1000, "\t")) !== FALSE) {
	$patronBarcode = $rawData[3];
	$itemBarcode = $rawData[4];
	$patronName = mysql_escape_string($rawData[5]);
	//Get the bib from the database
	$getBibWithItemStmt->execute();
	$getBibWithItemStmt->bind_result($bibId, $itemId);
	if ($getBibWithItemStmt->fetch()){
		$trimmedItem = substr($itemId, 1, strlen($itemId) -2);
		$sql = "INSERT IGNORE INTO offline_hold (patronBarcode, bibId, itemId, status, timeEntered, patronName) VALUES ('$patronBarcode', '$bibId', '$trimmedItem', 'Not Processed', '" . time() . "', '$patronName');";
		fwrite($holdsSqlFhnd, "$sql\r\n");
	}else{
		echo("Could not find bib for barcode $itemBarcode<br/>");
	}
}
fclose($holdsFhnd);

fclose($holdsSqlFhnd);

$getBibWithItemStmt->close();


