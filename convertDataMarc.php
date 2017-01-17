<?php
require_once 'File/MARC.php';
date_default_timezone_set('America/Denver');

$startTime = date('Y-m-d-H-i', time() );

ini_set("auto_detect_line_endings", true);
putenv("LANG=en_US.UTF-8");
$allPatrons = array();

$exportFile = 'C:\web\loveland_patron_conversion\patrons.mrc';
$exportedPatrons = new File_MARC($exportFile, File_MARC::SOURCE_FILE);

$finalFile = "C:/web/loveland_patron_conversion/converted/loveland_patrons.mrc";
$finalFileHnd = fopen($finalFile, 'wb');

$patronsWritten = 0;
while (($exportedPatron = $exportedPatrons->next()) !== FALSE) {
	//Adjust the record for import

	//Unique Id
	$uniqueId = getFieldData($exportedPatron, '020');
	if ($uniqueId == null){
		$barcode = getFieldData($exportedPatron, '030');
		$newUnique = 'pr' . $barcode;
		setFieldData($exportedPatron, '020', $newUnique);
	}else{
		$newUnique = 'pr' . $uniqueId;
		setFieldData($exportedPatron, '020', $newUnique);
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
	//removeField($exportedPatron, '087'); //Remove pmessage
	setFieldData($exportedPatron, '090', '6'); //Agency code

	removeField($exportedPatron, '093'); //Remove the old patron number

	//Save to the final file
	//Write the record to the file
	$rawRecord = $exportedPatron->toRaw();
	fwrite($finalFileHnd, $rawRecord);
	$patronsWritten++;;
} //End reading raw data export
fclose($finalFileHnd);
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