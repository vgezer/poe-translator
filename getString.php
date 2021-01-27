<?php

$dbserver = "localhost";
$dbusername = "root";
$dbpassword = "pass";
$dbname = "poeHistory";

$rootDir = "tyranny/text/"; // relative path to: text directory.
$from = "en"; // Original string language
$to = "tr"; // Target string language
$enableHistory = false; // requires database, set to false to disable history.
$enableMachineTranslation = true; // set to true if you want machine translation.
$ignorefolders = array(".git", "ignoreme"); // comma separated folders to ignore while deep searching files.
$allowedfiletypes = array(".stringtable"); // which file types must be shown in the list

// XML Based settings START
$accessPathToString = "/StringTableFile/Entries/Entry"; // Path in XML file to reach multiple entries of the source strings.
$translationField = "DefaultText"; // Which field in these entries must be translated?
$xmlStringIdField = "ID"; // The internal string ID, as defined in XML.
// XML Based settings END

// DON'T TOUCH BELOW

$stringId=@$_REQUEST["stringId"];
$xmlStringId=@$_REQUEST["xmlStringId"];
$oldLine=@$_POST["oldLine"];
$newLine=@$_POST["newLine"];
$file=@$_REQUEST["file"];
$activeDir=@$_GET["activeDir"];
$term=@$_REQUEST["searchTerm"];
$getStringWithId=@$_GET["getStringWithId"];


function listFolderFiles($dir){
	global $ignorefolders;
	global $allowedfiletypes;
    $ffs = scandir($dir);

    unset($ffs[array_search(basename(__FILE__), $ffs, false)]);
    unset($ffs[array_search('..', $ffs, true)]);
    unset($ffs[array_search('.', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1)
        return;

    echo '<ul>';
    foreach($ffs as $ff){
		foreach($allowedfiletypes as $allowedtype) {
			if(strstr($ff, $allowedtype)) {
				echo "<li><a href='".basename(__FILE__)."?file=".$dir."/".$ff."'>".$ff."</a>";
			}	
		}
		//echo "dir " . $dir.'/'.$ff;
        if(is_dir($dir.'/'.$ff)) {
			if(!in_array($ff, $ignorefolders)) {
				echo "<li><a href='".basename(__FILE__)."?activeDir=".$dir."/".$ff."'>".$ff."</a>";
				listFolderFiles($dir.'/'.$ff);
			}
		}
        echo '</li>';
    }
    echo '</ul>';
}

function scanSpecific($file) {

	$it = new RecursiveDirectoryIterator("./");
	#$allowed=array("0004_bs_worker_m_01.stringtable");
	foreach(new RecursiveIteratorIterator($it) as $file) {
		$pureFile = substr($file, strrpos($file, DIRECTORY_SEPARATOR)+1);
		if($pureFile == $file) {
			//echo $pureFile . "<br/> \n";
			break;
		}
	}
}

if(!isset($file)) {
	echo("<html><head><title>Easy Translator</title></head><body>");
	echo("Choose a file to work on: <br />");
	if(!isset($activeDir)) {
		$activeDir = ".";
	}
	echo("Currently active directory: $activeDir <br /> <a href='".basename(__FILE__)."'>Go Home");
	listFolderFiles($activeDir);
	echo("</body></html>");
	die;

}


if(!isset($stringId)) {
	$stringId=1;
}

function readXml($file) {
	$xml=simplexml_load_file($file);
	return $xml;
}

function countStrings() {
	global $xml;
	return @count($xml->Entries->Entry);
}

function translateOnGoogle($text) {
	
	global $translateOnGoogle;
	global $from;
	global $to;
	
	if(!$translateOnGoogle) {
		return "Machine translation is disabled. Enable via configuration.";
	}
	
	$url = "https://translate.google.com/m?hl=$to&sl=$from&q=" . urlencode($text);
	$lines_string = file_get_contents($url);
	$translation_begin = substr($lines_string, strpos($lines_string, 'result-container">')+18);
	$translation_end = strpos($translation_begin, "<");
	$translation = substr($translation_begin, 0, $translation_end);

	//$translation = mb_convert_encoding($translation, 'UTF-8', "ISO-8859-9");
	return $translation;

}

if(isset($_POST["submitcurrent"]) || isset($_POST["submitnext"]) || isset($_POST["submitprev"])) {
	
	if(isset($_POST["submitnext"])) {
			$stringId++;
	}
	if(isset($_POST["submitprev"])) {

			$stringId--;
	}

	if($newLine != "") {
		$str=file_get_contents($file);
		$str=str_replace($oldLine, $newLine, $str);
		$newLine = str_replace("'", "\'", $newLine);
		file_put_contents($file, $str);
		if($enableHistory) {
			$pathCompact = buildNicePaths(readXml($file), false);
			$result = mysqlExecute("SELECT * FROM history WHERE text='$newLine' and stringid='$xmlStringId'");
			if ($result->num_rows == 0) {
				mysqlExecute("INSERT INTO history (file, stringid, text, date) VALUES ('$pathCompact', '$xmlStringId', '".$newLine."','".date("Y-m-d H:i:s")."')");
			}
			else {
			}
		}

	}
	else {
		echo("Translation text cannot be blank!");
	}

}

function getLine() {
	global $xml;
	global $stringId;
	global $getStringWithId;
	global $accessPathToString;
	global $xmlStringIdField;
	if(isset($getStringWithId)) {
		return $xml->xpath($accessPathToString . '['. $xmlStringIdField .'='.$getStringWithId.']')[0];
	}
	else {
		//return $xml->Entries->Entry[(int)$stringId];
		//return $xml->xpath('/StringTableFile/Entries/Entry['.$stringId.']');
		//print_r($xml->xpath($accessPathToString . '[3]'));
		//die;
		//return $xml->xpath($accessPathToString . '[' . $xmlStringIdField . '='.$stringId.']')[0];
		return $xml->xpath($accessPathToString . '['.$stringId.']')[0];
	}
}

libxml_use_internal_errors(true);

$xml=readXml($file);

if($xml == false) {
	die("Not a valid XML file!");
}

if(isset($_POST["search"]) && strlen($term)>0) {
	$xpath = $xml->xpath($accessPathToString . '[contains(' . $translationField . ',"'.$term.'")]');
	$test = $xml->xpath('index-of(/'.$accessPathToString.', /'.$accessPathToString.'[.=\'You are surely looking for Beodmar. He is not in the village. I\\\'ll just make sure the candles are lit until he gets back.\'])');
	print_r($test);
	if ($xpath) {
		foreach($xpath as $result) {
			echo("ID: <a href=".basename(__FILE__)."?file=$file&getStringWithId=".$result[0]->$xmlStringIdField."&searchTerm=$term>" . $result[0]->$xmlStringIdField . "</a> String: ". preg_replace("/".$term."/", "<font color='red'>".$term."</font>", $result[0]->$translationField) ."<br/>");
		}
		die;
	} else {
		echo "Not found. <br />";
	}
}

if(isset($_POST["previous"])) {
	if($stringId <= 1) {
		echo("Already the first string!");
		$stringId = 1;
	}
	else {
		$stringId--;
	}
}

if(isset($_POST["next"])) {
	if($stringId >= countStrings()) {
		echo("Already the last string!");
		$stringId = countStrings();
	}
	else {
		$stringId++;
	}
}

if(isset($_POST["last"])) {
	$stringId = countStrings();
}
if(isset($_POST["first"])) {
	$stringId = 1;
}

function buildNicePaths($forceFile = "", $echoResult = true) {
	if($forceFile == "") {
		global $xml;
	}
	else {
		$xml = $forceFile;
	}
	global $rootDir;
	$paths = explode(DIRECTORY_SEPARATOR, $xml->Name);
	$fileName = end($paths).".stringtable";
	
	$longStr = "<a href='".basename(__FILE__)."?activeDir=./'>root</a> / ";
	for($i = 0; $i < count($paths) - 1; $i++) {
		if($i > 0) {
			$dir[$i] = $dir[$i-1] . "/" . $paths[$i];
		}
		else {
			$dir[$i] = $paths[$i];
		}
		$longStr = $longStr . "<a href='".basename(__FILE__)."?activeDir=./$rootDir/".$dir[$i]."'>".$paths[$i]."</a> / ";
	}
	if($echoResult) {
		echo($longStr . end($paths));	
	}
	return str_replace(DIRECTORY_SEPARATOR, "\\\\", $xml->Name);;
	
}

function mysqlExecute($query) {
	global $enableHistory;
	if(!$enableHistory) {
		return;
	}
	// Create connection
	global $dbserver;
	global $dbusername;
	global $dbpassword;
	global $dbname;
	$conn = new mysqli($dbserver, $dbusername, $dbpassword, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$result = $conn->query($query);
	if ($result === TRUE) {
		echo "Added default text to history.";
	} else {
		echo $conn->error;
	}

	$conn->close();
	return $result;
}

?>

<html>
<head>
	<meta charset="UTF-8">
	<title>Easy Translator</title>
</head>
<script type="text/javascript">
	function copyMachineToTarget() {
    var src = document.getElementsByTagName('pre')[0].innerHTML,
        dst = document.getElementById("newLineId");
		dst.value = src;
        
	};
	document.onkeyup = function(e) {
	  if (e.altKey && e.which == 77) { // Alt + M : next
		document.getElementById("next").click();
	  } else if (e.ctrlKey && e.altKey && e.which == 77) { // CTRL + Alt + M : save & next
		document.getElementById("submitnext").click();
	  } else if (e.altKey && e.which == 78) { // Alt + N : previous
		document.getElementById("previous").click();
	  }
	  else if (e.altKey && e.which == 83) { // Alt + S : save
		document.getElementById("submitcurrent").click();
	  } else if (e.altKey && e.which == 86) { // Alt + V : paste machine
		copyMachineToTarget();
	  }
	};

</script>
<body>
<form method="POST" id="translationForm" name="translationForm" action="">
<table>
<tr><td>Navigation:</td><td>
<input type="submit" id="first" name="first" value="|&lt First" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="submit" id="previous" title="Shortcut: Alt + N" name="previous" value="&lt&lt Previous" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="submit" id="submitprev" name="submitprev" value="Submit & Previous" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="button" id="copyMachineTranslation" onclick="copyMachineToTarget()" title="Shortcut: Alt + V" name="copyMachineTranslation" value="Paste Machine Translation" <?php echo($enableMachineTranslation==false?"disabled=disabled":"") ?>/>
<input type="submit" id="submitcurrent" name="submitcurrent" title="Shortcut: Alt + S" value="Submit" />
<input type="submit" id="submitnext" name="submitnext" title="Shortcut: Ctrl + Alt + M" value="Submit & Next" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>
<input type="submit" id="next" name="next" title="Shortcut: Alt + M" value="Next &gt&gt" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>
<input type="submit" id="last" name="last" value="Last &gt|" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>

<input type="text" id="searchTerm" name="searchTerm" value="<?php echo($term) ?>" />
<input type="submit" id="search" name="search" value="Search" />
<?php 
if(strlen($term)>0) {
	echo("<a href='".$_SERVER["SCRIPT_NAME"]."?file=$file'>Clear Search</a>");
}
?>
</td></tr>
<tr><td>File:</td><td><?php $pathCompact = buildNicePaths() ?></td></tr>
<tr><td>Current string:</td><td><?php echo($stringId . "/"); echo(countStrings()) ?></td></tr>
<tr><td>String ID:</td><td><?php echo("<a title='Copy the permanent link to share this string.' href='".basename(__FILE__)."?file=$file&stringId=$stringId'>".getLine()->$xmlStringIdField."</a>") ?></td></tr>
<tr><td>Source string:</td><td>
<textarea readonly=readonly rows="10" cols="100" id="oldLineId" name="oldLine"><?php echo(getLine()->$translationField) ?></textarea></td></tr>
<tr><td>Translation:</td><td>
<textarea rows="10" cols="100" id="newLineId" name="newLine" autofocus></textarea></td></tr>
<tr><td colspan='2'><hr></td>
<tr><td>Machine Translation:</td><td width='50px'><pre id="machineTranslation"><?php echo(translateOnGoogle(getLine()->$translationField))?></pre></td></tr>
<tr><td colspan='2'><hr></td>
<tr><td>History:</td><td width='50px'>
<?php
if($enableHistory) {
	$result = mysqlExecute("SELECT * FROM history WHERE file='$pathCompact' and stringid='".(getLine()->$xmlStringIdField)."'");
	$current_row = 0;
	if ($result->num_rows > 0) {
		// output data of each row
		while($row = $result->fetch_assoc()) {
			if($current_row === 0) {
				echo ("<pre>[Original Text] " . $row["text"]. "</pre>");
			}
			else {
				echo ("<pre>[". date("d.m.Y H:i:s", strtotime($row["date"])) ."] " . $row["text"]. "</pre>");
			}
			$current_row++;
		}
	} else {
		echo "No history found.<br>";
		mysqlExecute("INSERT INTO history (file, stringid, text, date) VALUES ('$pathCompact', '".getLine()->$xmlStringIdField."', '".str_replace("'","\'",getLine()->$translationField)."','".date("Y-m-d H:i:s")."')");
	}
}
else {
	echo("History disabled.");
}

?>
</td></tr>
<tr><td colspan='2'><hr></td></tr>
<tr><td>Similar:</td><td width='50px'>
<?php
if($enableHistory) {
	$result = mysqlExecute("SELECT * FROM history WHERE text='".(getLine()->$translationField)."'");
	$current_row = 0;
	if ($result->num_rows > 0) {
		// output data of each row
		while($row = $result->fetch_assoc()) {
			echo ("<pre>[". date("d.m.Y H:i:s", strtotime($row["date"])) ."] " . $row["text"]. "</pre>");
		}
	} else {
		echo "No similarities found.<br>";
	}
}
else {
	echo("History disabled. Similar strings can only be used history is enabled.");
}

?>
</td></tr>
<tr><td colspan='2'><hr></td>
<tr><td>Navigation:</td><td>
<input type="submit" id="first" name="first" value="|&lt First" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="submit" id="previous" title="Shortcut: Alt + N" name="previous" value="&lt&lt Previous" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="submit" id="submitprev" name="submitprev" value="Submit & Previous" <?php echo($stringId<=1?"disabled=disabled":"") ?>/>
<input type="button" id="copyMachineTranslation" title="Shortcut: Alt + V" onclick="copyMachineToTarget()" name="copyMachineTranslation" value="Paste Machine Translation" <?php echo($enableMachineTranslation==false?"disabled=disabled":"") ?>/>
<input type="submit" id="submitcurrent" title="Shortcut: Alt + S" name="submitcurrent" value="Submit" />
<input type="submit" id="submitnext" title="Shortcut: Ctrl + Alt + M" name="submitnext" value="Submit & Next" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>
<input type="submit" id="next" name="next" title="Shortcut: Alt + M" value="Next &gt&gt" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>
<input type="submit" id="last" name="last" value="Last &gt|" <?php echo($stringId>=countStrings()?"disabled=disabled":"") ?>/>
</td></tr>
<input type="hidden" id="stringId" name="stringId" value="<?php echo($stringId) ?>" />
<input type="hidden" id="xmlStringId" name="xmlStringId" value="<?php echo(getLine()->$xmlStringIdField) ?>" />
<input type="hidden" id="file" name="file" value="<?php echo($file) ?>" />
</table>
</form>

</body>
</html>
