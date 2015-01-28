<?php
//Get the CLI arguments
for($i=0;$i<$argc;$i++) {
	if(stristr($argv[$i],"h=") == true) {
		$pieces = explode("=",$argv[$i]);
		$currenthost = $pieces['1'];
	}
	if(stristr($argv[$i],"c=") == true) {
		$pieces = explode("=",$argv[$i]);
		$community = $pieces['1'];
	}
}

//Make sure that all arguments were passed, there needs to be 4
if($argc < 3) {
	if($currenthost == "") {
		echo "No host defined, exiting.\n";
	}
	if($community == "") {
		echo "No community defined, exiting.\n";
	}
	showUsage();
}

$ifnames = snmprealwalk($currenthost,$community,"ifName");
print_r($ifnames);


function showUsage() {
	echo "\nUsage: ./scriptname.php h=[HOST] c=[COMMUNITY]\n\n";
	echo "\tHOST:\t\t The host you want to poll via SNMP.\n";
	echo "\tCOMMUNITY:\t The SNMP community as setup on HOST.\n";
	exit(1);
}
?>
