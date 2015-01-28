#!/usr/bin/php
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
	if(stristr($argv[$i],"i=") == true) {
		$pieces = explode("=",$argv[$i]);
		$interface = $pieces['1'];
	}
	if(stristr($argv[$i],"t=") == true) {
		$pieces = explode("=",$argv[$i]);
		$time = $pieces['1'];
	}
}

//Make sure that all arguments were passed, there needs to be 4
if($argc < 4) {
	if($currenthost == "") {
		echo "No host defined, exiting.\n";
	}
	if($community == "") {
		echo "No community defined, exiting.\n";
	}
	if($interface == "") {
		echo "No interface defined, exiting.\n";
	}
	if($time == "") {
		echo "No time defined, exiting.\n";
	}
	
	showUsage();
}
//Check for lok file and exit if exists
if(file_exists("/tmp/lok/bandmon")) {
	die("Already running or lok file still in place!\n");
} else {
	echo "No lok file found, starting...\n";
	passthru("touch /tmp/lok/bandmon");
}

//Remove log file at startup
passthru("rm -rfv /var/log/bandmon");


/*
$currenthost = "8.10.97.230";
$community = "bandmon";
$interface = "1";
//$portspeed = "1000000000"; //This is in bits per second (find it by doing snmprealwalk($host,$community,$ifSpeed.1))
*/

//We've got all the arguments we need, so let's start doing stuff.

//Query HOST for the port speed of INTERFACE
$portspeed = snmprealwalk($currenthost,$community,"ifSpeed");
$arraypointer = "IF-MIB::ifSpeed.".$interface."";
$portspeed = $portspeed[$arraypointer];
$pieces = explode(" ",$portspeed);
$portspeed = $pieces['1'];

//Now we know the port speed, to calculate % of total speed. Let's kick off
//the polling function, which is an infinite loop that polls every TIME seconds.

pollBandwidth($currenthost,$community,$interface,$portspeed,$time);


//The polling function
function pollBandwidth($host,$community,$interface,$portspeed,$time) {
	//The port speed is reported in bits per second, this will convert to kbps and Mbps
	$portspeed = round(($portspeed / 1000),2);
	$portspeedM = round(($portspeed / 1000),2);
	
	//Query HOST for the inbound octets to get our delta
	$inbytes = snmpget($host,$community,"ifInOctets.".$interface."");
	$pieces = explode(" ",$inbytes);
	$inbytes = $pieces['1'];
	
	//Query HOST for the outbound octets to get our delta
	$outbytes = snmpget($host,$community,"ifOutOctets.".$interface."");
	$pieces = explode(" ",$outbytes);
	$outbytes = $pieces['1'];
	
	//Now we have our initial readings to calculate the delta, so now we can calculate bandwidth.
	if($inbytes == "" || $outbytes == "") {
		$errorstate = "CRITICAL";
		$errmsg = "Unable to get initial reading via SNMP. Host '".$host."' in community ".$community.", for interface ".$interface."";
		echo "".$errorstate." :: ".$errmsg."\n";
		exit(1);
	} else {
		//Now we're getting some data! We know the port speed, and got an initial reading so we can calculate
		//bandwidth based on our reported number of octets minus the delta over the specified TIME.
		$i = 0;
		$loopcount = 0;
		while(true) {
			if($loopcount >= 30000) {
				echo "Restarting BandMon process...\n";
				passthru("rm -rf /tmp/lok/bandmon");
				die("Lokfile removed, killing process to allow cron to restart...\n");
			} else {
				$loopcount++;
			}

			//Query HOST for the latest inbound octets
			$inbytes = snmpget($host,$community,"ifInOctets.".$interface."");
			$pieces = explode(" ",$inbytes);
			$inbytes = $pieces['1'];
			
			//Query HOST for the latest outbound octets
			$outbytes = snmpget($host,$community,"ifOutOctets.".$interface."");
			$pieces = explode(" ",$outbytes);
			$outbytes = $pieces['1'];
			
			//Sleep TIME
			sleep($time);
			
			//We've slept TIME, so let's poll again
			$currentin = snmpget($host,$community,"ifInOctets.".$interface."");
			$pieces = explode(" ",$currentin);
			$currentin = $pieces['1'];
			$currentout = snmpget($host,$community,"ifOutOctets.".$interface."");
			$pieces = explode(" ",$currentout);
			$currentout = $pieces['1'];
			
			//Now we can calculate the delta having our two datasets
			$deltain = $currentin - $inbytes;
			$deltaout = $currentout - $outbytes;
			
			//Using the delta divided by TIME, we get the number of octets transferred. Multiply by 8 to convert
			//to bytes, and then divide by 1024 to get kilobytes (technically, kibibytes).
			$inbw = round( (($deltain/$time)*8/1024),2 );
			$outbw = round( (($deltaout/$time)*8/1024),2 );
			$totalbw = round(($inbw + $outbw),2);
			
			//We know our bandwidth now, let's calculate the percentage of our total port speed
			$inpct = round(($inbw/$portspeed),4);
			$outpct = round(($outbw/$portspeed),4);
			
			//We want nice output, in case this is ran in the foreground to watch bandwidth
			system("clear");
			echo "HOST ".$host." :: Interface ".$interface." :: Port Speed ".$portspeed." kbps (".$portspeedM." Mbps)\n\n";
			
			$unitsin = "kbps";
			$unitsout = "kbps";
			$unitstotal = "kbps";

/* - Commented this block out to force KBPS readings only
			//Convert bandwidth to Mbps if it's over 1024kbps.
			if($inbw >= 1024) {
				$inbw = round(($inbw / 1024),2);
				$unitsin = "Mbps";
				$inpct = round((($inbw*1024)/$portspeed),4);
			}
			
			if($outbw >= 1024) {
				$outbw = round(($outbw / 1024),2);
				$unitsout = "Mbps";
				$outpct = round((($outbw*1024)/$portspeed),4);
			}
			
			if($totalbw >= 1024) {
				$totalbw = round(($totalbw / 1024),2);
				$unitstotal = "Mbps";
				$totalpct = round((($totalbw*1024)/$portspeed),4);
			}
*/
			if($i < 25) {
				passthru("echo 'TOTAL:\t".str_pad($totalbw,7)." ".str_pad($unitstotal,5)."' >> /var/log/bandmon");
				$i++;
			} else {
				$i = 0;
				passthru("rm -rfv /var/log/bandmon");
				passthru("echo 'TOTAL:\t".str_pad($totalbw,7)." ".str_pad($unitstotal,5)."' >> /var/log/bandmon");
			}
			echo "IN: \t".str_pad($inbw,7)." ".str_pad($unitsin,5)." (".str_pad($inpct,6)."% of total port speed)\n";
			echo "OUT:\t".str_pad($outbw,7)." ".str_pad($unitsout,5)." (".str_pad($outpct,6)."% of total port speed)\n";
			echo "TOTAL:\t".str_pad($totalbw,7)." ".str_pad($unitstotal,5)." (".str_pad($totalpct,6)."% of total port speed)\n";
		}
	}
}

function showUsage() {
	echo "\nUsage: ./scriptname.php h=[HOST] c=[COMMUNITY] i=[INTERFACE] t=[TIME]\n\n";
	echo "\tHOST:\t\t The host you want to poll via SNMP.\n";
	echo "\tCOMMUNITY:\t The SNMP community as setup on HOST.\n";
	echo "\tINTERFACE:\t The interface you wish to poll on HOST.\n";
	echo "\tTIME:\t\t How often to poll HOST, in seconds.\n\n\n";
	exit(1);
}
?>
