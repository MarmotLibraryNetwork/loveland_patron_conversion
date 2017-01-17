<?php
/**
 * Description goes here
 *
 * @category longmont_patron_conversion
 * @author Mark Noble <mark@marmot.org>
 * Date: 4/1/2016
 * Time: 1:34 PM
 */
date_default_timezone_set('America/Denver');

$holdsToFixFile = "C:/web/lafayette_patron_conversion/converted/HoldsToFix.csv";
$holdsToFixFhnd = fopen($holdsToFixFile, 'r');
while (($rawData = fgetcsv($holdsToFixFhnd, 1000, ",", "'")) !== FALSE) {
	set_time_limit(600);
	$barcode = $rawData[0];
	$lastName = $rawData[1];

	$patronHoldsRaw = file_get_contents('http://flatirons.localhost/API/UserAPI?method=getPatronHolds&username=' . urlencode($lastName) . "&password=$barcode");
	$patronHolds = json_decode($patronHoldsRaw);
	$numFailures = 0;
	$numUpdates = 0;
	foreach ($patronHolds->result->holds->unavailable as $hold){
		if ($hold->location == 'Not Set'){
			$return = file_get_contents('http://flatirons.localhost/API/UserAPI?method=changeHoldPickUpLocation&username=' . urlencode($lastName) . "&password=$barcode&holdId={$hold->cancelId}&location=la");
			$response = json_decode($return);

			if (!$response->result->success){
				$numFailures++;
			}else{
				$numUpdates++;
			}
		}
	}
	foreach ($patronHolds->result->holds->available as $hold){
		if ($hold->location != 'Lafayette Public Library'){
			echo ("$barcode,{$hold->itemId},{$hold->recordId}<br/>\r\n");
		}
	}
	if ($numFailures > 0){
		echo("Failed to update location for patron $barcode $lastName<br/>\r\n");
	}elseif ($numUpdates > 0){
		//echo("Update $numUpdates holds for patron $barcode $lastName<br/>\r\n");
	}else{
		//echo("Everything was fine for for patron $barcode $lastName<br/>\r\n");
	}
}
fclose($holdsToFixFhnd);