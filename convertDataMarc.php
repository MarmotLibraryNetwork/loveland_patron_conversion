<?php
require_once 'File/MARC.php';
date_default_timezone_set('America/Denver');

$startTime = date('Y-m-d-H-i', time() );

ini_set("auto_detect_line_endings", true);

ini_set('display_errors', true);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

putenv("LANG=en_US.UTF-8");
$allPatrons = array();

$exportFile = 'C:\web\loveland_patron_conversion\patrons.mrc';
$exportedPatrons = new File_MARC($exportFile, File_MARC::SOURCE_FILE);

$finalFile = "C:/web/loveland_patron_conversion/converted/loveland_patrons.mrc";
$finalFileHnd = fopen($finalFile, 'wb');

$checkoutsFile = "C:/web/loveland_patron_conversion/converted/circ.dat";
$checkoutsFhnd = fopen($checkoutsFile, 'w');

$millenniumFineLoader = new MillenniumFineLoader();

$patronsWritten = 0;
while (($exportedPatron = $exportedPatrons->next()) !== FALSE) {
	set_time_limit(30);
	//Adjust the record for import

	//Unique Id
	$uniqueId = getFieldData($exportedPatron, '020');
	$barcode = getFieldData($exportedPatron, '030');
	$patronId = getFieldData($exportedPatron, '907');
	$patronId = str_replace('.p', '', $patronId);
	$newUnique = 'lv' . $barcode;
	setFieldData($exportedPatron, '020', $newUnique);

	//Remove old export data
	removeField($exportedPatron, '907');

	//Add a second barcode starting with D and then the barcode
	$field = new File_MARC_Data_Field('030');
	$subfield = new File_MARC_Subfield('a', 'D' . $barcode);
	$field->appendSubfield($subfield);
	$exportedPatron->appendField($field);

	//Update expiration date to be mdy rather than ymd because iii is weird
	$expirationDate = getFieldData($exportedPatron, '080');
	if ($expirationDate != null){
		$currentDate = date_parse_from_format('ymd', $expirationDate);
		$newExpirationDate = sprintf("%'.02d-%'.02d-%'.04d", $currentDate['month'], $currentDate['day'], $currentDate['year']);
		setFieldData($exportedPatron, '080', $newExpirationDate);
	}

	//081 = pcode1
	//set residency / pcode1 based on address
	$firstAddress = getFieldData($exportedPatron, '220');
	$exportedPatron->deleteFields('081');
	if (stripos($firstAddress, 'longmont')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'v'))));
	}elseif (stripos($firstAddress, 'Aurora')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'c'))));
	}elseif (stripos($firstAddress, 'Boulder')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'k'))));
	}elseif (stripos($firstAddress, 'Broomfield')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'm'))));
	}elseif (stripos($firstAddress, 'Denver')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'n'))));
	}elseif (stripos($firstAddress, 'Erie')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'o'))));
	}elseif (stripos($firstAddress, 'Lafayette')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'u'))));
	}elseif (stripos($firstAddress, 'Louisville')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'w'))));
	}elseif (stripos($firstAddress, 'Lyons')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'x'))));
	}elseif (stripos($firstAddress, 'Nederland')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'y'))));
	}elseif (stripos($firstAddress, 'Niwot')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', 'z'))));
	}elseif (stripos($firstAddress, 'Superior')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', '1'))));
	}elseif (stripos($firstAddress, 'Westminster')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', '3'))));
	}elseif (stripos($firstAddress, 'Loveland')){
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', '6'))));
	}else{
		$exportedPatron->appendField(new File_MARC_Data_Field('081', array(new File_MARC_Subfield('a', '-'))));
	}

	//Adjust ptype
	$curPType = getFieldData($exportedPatron, '084');
	if ($curPType == 1){
		$newPType = 125;
	}elseif ($curPType == 2){
		$newPType = 126;
	}elseif ($curPType == 3){
		$newPType = 127;
	}elseif ($curPType == 4){
		$newPType = 128;
	}elseif ($curPType == 5){
		$newPType = 129;
	}elseif ($curPType == 6){
		$newPType = 130;
	}elseif ($curPType == 7){
		$newPType = 131;
	}elseif ($curPType == 8){
		$newPType = 132;
	}elseif ($curPType == 9){
		$newPType = 133;
	}else{
		echo ('Unkown ptype ' . $curPType . '<br/>\r\n');
	}
	setFieldData($exportedPatron, '084', $newPType);
	setFieldData($exportedPatron, '085', 'lv'); //Home library
	$mblock = getFieldData($exportedPatron, '086');
	//Set ptype to 134 for internet cards
	if ($mblock != null && $mblock == 'i'){
		setFieldData($exportedPatron, '084', 134);
	}

	//Move custom birthday to 089
	$birthday = getFieldData($exportedPatron, '301');
	$exportedPatron->deleteFields('301');
	setFieldData($exportedPatron, '089', $birthday);

	//removeField($exportedPatron, '087'); //Remove pmessage
	setFieldData($exportedPatron, '090', '6'); //Agency code

	removeField($exportedPatron, '907'); //Remove the old patron number

	//Load additional information by calling patron api and scraping from webpac if needed
	$patronAPIDataRaw = file_get_contents("http://libra.loveland.lib.co.us:4500/PATRONAPI/$barcode/dump");
	if (preg_match('/CIRCACTIVE\[p163\]=(.*?)<BR>/', $patronAPIDataRaw, $circActive)){
		$circActive = $circActive[1];
		$exportedPatron->appendField(new File_MARC_Data_Field('091',array( new File_MARC_Subfield('a', $circActive))));
	}else{
		$circActive = '';
	}
	if (preg_match('/MONEY OWED\[p96\]=(.*?)<BR>/', $patronAPIDataRaw, $moneyOwed)){
		$moneyOwed = $moneyOwed[1];
	}else{
		$moneyOwed = '';
	}
	if (preg_match('/TOT CHKOUT\[p48\]=(.*?)<BR>/', $patronAPIDataRaw, $totChkout)){
		$totChkout = $totChkout[1];
		$exportedPatron->appendField(new File_MARC_Data_Field('092',array( new File_MARC_Subfield('a', $totChkout))));
	}else{
		$totChkout = '';
	}
	if (preg_match('/TOT RENWAL\[p49\]=(.*?)<BR>/', $patronAPIDataRaw, $totRenewal)){
		$totRenewal = $totRenewal[1];
		$exportedPatron->appendField(new File_MARC_Data_Field('093',array( new File_MARC_Subfield('a', $totRenewal))));
	}else{
		$totRenewal = '';
	}
	if (preg_match('~PAR/GUARDI\[pg\]=(.*?)<BR>~', $patronAPIDataRaw, $parentGuardian)){
		$parentGuardian = $parentGuardian[1];
		$exportedPatron->appendField(new File_MARC_Data_Field('300',array( new File_MARC_Subfield('a', $parentGuardian))));
	}else{
		$parentGuardian = '';
	}

	if (($moneyOwed != '') && ($moneyOwed != '$0.00')){
		$hasFines = true;
		//Add a note with the amount of money owed
		$exportedPatron->appendField(new File_MARC_Data_Field('400',array( new File_MARC_Subfield('a', 'Total fines from Millennium ' . $moneyOwed))));

		//Load WebPac to figure out more details about the fines
		$patronName = getFieldData($exportedPatron, '100');
		$fines = $millenniumFineLoader->getPatronFines($patronId, $patronName, $barcode);
		foreach ($fines as $fine){
			$fineMessage = $fine['reason'] . ' ' . $fine['amount'] . ' - ' . $fine['message'];
			foreach ($fine['details'] as $fineDetail){
				$fineMessage .= "\r\n  {$fineDetail['label']}: {$fineDetail['value']}";
			}
			$exportedPatron->appendField(new File_MARC_Data_Field('400',array( new File_MARC_Subfield('a', $fineMessage))));
		}
	}else{
		$hasFines = false;
	}

	//Get a list of checkouts
	$checkouts = $exportedPatron->getFields('989');
	/** @var File_MARC_Data_Field $checkout */
	foreach ($checkouts as $checkout){
		$itemBarcode = $checkout->getSubfield('b')->getData();

		$now = new DateTime();
		$nowFormatted = $now->format('ymdHi');
		$dueDate = date_add($now, new DateInterval('P3W'));
		$dueDateFormatted = $dueDate->format('ymdHi');
		$sql = "o:$nowFormatted:b$itemBarcode:b$barcode:$dueDateFormatted:400";
		fwrite($checkoutsFhnd, "$sql\r\n");
	}

	//Save to the final file
	//Write the record to the file
	$rawRecord = $exportedPatron->toRaw();
	fwrite($finalFileHnd, $rawRecord);
	$patronsWritten++;;
} //End reading raw data export
fclose($finalFileHnd);
fclose($checkoutsFhnd);
echo ("converted $patronsWritten patrons");


//End of processing

/**
 * @param File_MARC_Record $patronRecord
 * @param string $tag
 */
function getFieldData($patronRecord, $tag){
	/** @var File_MARC_Data_Field $field */
	$field = $patronRecord->getField($tag);
	if ($field != null){
		$subfield = $field->getSubfield('a');
		if ($subfield != null){
			return trim($subfield->getData());
		}
	}
	return null;
}

/**
 * @param File_MARC_Record $patronRecord
 * @param string $tag
 * @param string $newValue
 */
function setFieldData($patronRecord, $tag, $newValue){
	$field = $patronRecord->getField($tag);
	if ($field == null){
		$field = new File_MARC_Data_Field($tag);
		$patronRecord->appendField($field);
	}
	$subfield = $field->getSubfield('a');
	if ($subfield == null){
		$subfield = new File_MARC_Subfield('a', $newValue);
		$field->appendSubfield($subfield);
	}else{
		$subfield->setData($newValue);
	}
}

/**
 * @param File_MARC_Record $patronRecord
 * @param string $tag
 */
function removeField($patronRecord, $tag){
	$patronRecord->deleteFields($tag);
}

class MillenniumFineLoader
{
	var $cookieJar;
	var $curl_connection;

	function getPatronFines($patronId, $username, $barcode)
	{
		//Load the information from millennium using CURL
		$pageContents = $this->fetchPatronInfoPage($patronId, $username, $barcode, 'overdues');

		//Get the fines table data
		$messages = array();
		if (preg_match('/<table border="0" class="patFunc">(.*?)<\/table>/si', $pageContents, $regs)) {
			$finesTable = $regs[1];
			//Get the title and, type, and fine detail from the page
			preg_match_all('/<tr class="(patFuncFinesEntryTitle|patFuncFinesEntryDetail|patFuncFinesDetailDate)">(.*?)<\/tr>/si', $finesTable, $rowDetails, PREG_SET_ORDER);
			$curFine = array();
			for ($match1 = 0; $match1 < count($rowDetails); $match1++) {
				$rowType = $rowDetails[$match1][1];
				$rowContents = $rowDetails[$match1][2];
				if ($rowType == 'patFuncFinesEntryTitle') {
					if ($curFine != null) $messages[] = $curFine;
					$curFine = array();
					if (preg_match('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails)) {
						$curFine['message'] = trim(strip_tags($colDetails[1]));
					}
				} else if ($rowType == 'patFuncFinesEntryDetail') {
					if (preg_match_all('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails, PREG_SET_ORDER) > 0) {
						$curFine['reason'] = trim(strip_tags($colDetails[1][1]));
						$curFine['amount'] = trim($colDetails[2][1]);
					}
				} else if ($rowType == 'patFuncFinesDetailDate') {
					if (preg_match_all('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails, PREG_SET_ORDER) > 0) {
						if (!array_key_exists('details', $curFine)) $curFine['details'] = array();
						$curFine['details'][] = array(
								'label' => trim(strip_tags($colDetails[1][1])),
								'value' => trim(strip_tags($colDetails[2][1])),
						);
					}
				}
			}
			if ($curFine != null) $messages[] = $curFine;
		}

		return $messages;
	}

	function fetchPatronInfoPage($patronId, $username, $barcode, $page)
	{
		//First we have to login to classic
		if ($this->curl_login($username, $barcode)) {
			//Now we can get the page
			$curlUrl = "https://libra.loveland.lib.co.us/patroninfo~S0/" . $patronId . "/$page";
			$curlResponse = $this->curlGetPage($curlUrl);

			//Strip HTML comments
			$curlResponse = preg_replace("/<!--([^(-->)]*)-->/", " ", $curlResponse);
			return $curlResponse;
		}
		return false;
	}

	function curl_login($username, $barcode)
	{
		$loginResult = false;

		$curlUrl = "https://libra.loveland.lib.co.us/patroninfo/";
		$post_data = array();
		$post_data['name'] = $username;
		$post_data['code'] = $barcode;
		$post_data['submit'] = "Submit";

		$loginResponse = $this->curlPostPage($curlUrl, $post_data);

		//When a library uses IPSSO, the initial login does a redirect and requires additional parameters.
		if (preg_match('/<input type="hidden" name="lt" value="(.*?)" \/>/si', $loginResponse, $loginMatches)) {
			$lt = $loginMatches[1]; //Get the lt value
			//Login again
			$post_data['lt'] = $lt;
			$post_data['_eventId'] = 'submit';

			//Don't issue a post, just call the same page (with redirects as needed)
			$post_string = http_build_query($post_data);
			curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $post_string);

			$loginResponse = curl_exec($this->curl_connection);
		}

		if ($loginResponse) {
			$loginResult = true;

			// Check for Login Error Responses
			$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $loginResponse, $matches);
			if ($numMatches > 0) {
				$loginResult = false;
			} else {

				// Pause briefly after logging in as some follow-up millennium operations (done via curl) will fail if done too quickly
				usleep(15000);
			}
		}

		return $loginResult;
	}

	function curlPostPage($url, $postParams)
	{
		$post_string = http_build_query($postParams);

		$this->curl_connect($url);
		curl_setopt_array($this->curl_connection, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $post_string
		));
		$return = curl_exec($this->curl_connection);
		if (!$return) { // log curl error
			echo('curl post error : ' . curl_error($this->curl_connection));
		}
		return $return;
	}

	public function curlGetPage($url){
		$this->curl_connect($url);
		$return = curl_exec($this->curl_connection);
		if (!$return) { // log curl error
			echo('curl get error : '.curl_error($this->curl_connection));
		}
		return $return;
	}

	public function curl_connect($curlUrl = null, $curl_options = null){
		//Make sure we only connect once
		if (!$this->curl_connection){
			$header = array();
			$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
			$header[] = "Cache-Control: max-age=0";
			$header[] = "Connection: keep-alive";
			$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
			$header[] = "Accept-Language: en-us,en;q=0.5";
			$header[] = "User-Agent: Patron conversion tool";

			$cookie = $this->getCookieJar();

			$this->curl_connection = curl_init($curlUrl);
			$default_curl_options = array(
					CURLOPT_CONNECTTIMEOUT => 20,
					CURLOPT_TIMEOUT => 60,
					CURLOPT_HTTPHEADER => $header,
				//CURLOPT_USERAGENT => 'User-Agent: Mozilla/5.0 (Windows NT 6.2; WOW64; rv:39.0) Gecko/20100101 Firefox/39.0',
				//CURLOPT_USERAGENT => "User-Agent:Pika " . $gitBranch,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_UNRESTRICTED_AUTH => true,
					CURLOPT_COOKIEJAR => $cookie,
					CURLOPT_COOKIESESSION => false,
					CURLOPT_FORBID_REUSE => false,
					CURLOPT_HEADER => false,
					CURLOPT_AUTOREFERER => true,
				//  CURLOPT_HEADER => true, // debugging only
				//  CURLOPT_VERBOSE => true, // debugging only
			);

			if ($curl_options) {
				$default_curl_options = array_merge($default_curl_options, $curl_options);
			}
			curl_setopt_array($this->curl_connection, $default_curl_options);
		}else{
			//Reset to HTTP GET and set the active URL
			curl_setopt($this->curl_connection, CURLOPT_HTTPGET, true);
			curl_setopt($this->curl_connection, CURLOPT_URL, $curlUrl);
		}

		return $this->curl_connection;
	}

	public function getCookieJar() {
		if (is_null($this->cookieJar)){
			$this->setCookieJar();
		}
		return $this->cookieJar;
	}

	public function setCookieJar(){
		$cookieJar = tempnam("/tmp", "CURLCOOKIE");
		$this->cookieJar = $cookieJar;
	}
}