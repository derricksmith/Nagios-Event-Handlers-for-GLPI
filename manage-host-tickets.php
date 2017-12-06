<?php
error_reporting(E_ALL);
require_once("glpi_api.php");

##  Variables ##

## Required ##
$glpi_user			= '';
$glpi_password			= '';
$glpi_apikey			= '';
$glpi_host			= '';
$nagios_host			= '';
$verifypeer			= FALSE; // SETS curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
$logging			= TRUE;
$critical_priority		= 5;
$warning_priority		= 3;

## Optional ##
$glpi_requester_user_id		= '';
$glpi_requester_group_id	= '';
$glpi_watcher_user_id		= '';
$glpi_watcher_group_id		= '';
$glpi_assign_user_id		= '';
$glpi_assign_group_id		= '';

## End Variables ##




function logging($msg, $log){
	if ($log === true){
		syslog(LOG_INFO, $msg);
	}
}

logging("Manage Host Tickets: Called Script", $logging);

if (!extension_loaded("curl")) {
	logging("Manage Host Tickets: Extension curl not loaded", $logging);
	die("Extension curl not loaded");
}

$glpi = new GLPI_API(array('username' => $glpi_user, 'password' => $glpi_password, 'apikey' => $glpi_apikey, 'host' => $glpi_host, 'verifypeer' => $verifypeer));

$eventval=array();
if ($argv>1) {
	for ($i=1 ; $i<count($argv) ; $i++) {
		$it = explode("=",$argv[$i],2);
		$it[0] = preg_replace('/^--/','',$it[0]);
		$eventval[$it[0]] = (isset($it[1]) ? $it[1] : true);
	}
}

$eventhost=$eventval['eventhost'];
$hoststate=$eventval['hoststate'];
$hoststatetype=$eventval['hoststatetype'];
$hostattempts=$eventval['hostattempts'];
$maxhostattempts=$eventval['maxhostattempts'];
$hostproblemid=$eventval['hostproblemid'];
$lasthostproblemid=$eventval['lasthostproblemid'];
unset($eventval);

logging("Manage Host Tickets: EventHost = ".$eventhost, $logging);
logging("Manage Host Tickets: HostState = ".$hoststate, $logging);
logging("Manage Host Tickets: HostStateType = ".$hoststatetype, $logging);
logging("Manage Host Tickets: HostAttempts = ".$hostattempts, $logging);
logging("Manage Host Tickets: MaxHostAttempts = ".$maxhostattempts, $logging);
logging("Manage Host Tickets: HostProblemID = ".$hostproblemid, $logging);
logging("Manage Host Tickets: LastHostProblemID = ".$lasthostproblemid, $logging);

# What state is the HOST in?
switch ($hoststate) {
	case "UP":
		# The host just came back up - perhaps we should close the ticket...
		logging("Manage Host Tickets: Host '".$eventhost."' is UP, checking last problem ID", $logging);
		if ($lasthostproblemid != 0) {
			logging("Manage Host Tickets: Last Problem ID does not equal UP, searching for tickets to close", $logging);
			$search = array(
				'criteria' => array(
					array(
						'field' => '12', //Status field
						'searchtype' => 'equals',
						'value' => 1 //Search on Open Tickets
					),
					array(
						'link' => 'AND',
						'field' => '1', //Title field
						'searchtype' => 'contains',
						'value' => "$eventhost is down!" //Search Title
					)
				)
			);

			$tickets = $glpi->search('Ticket', $search);

			if (!empty($tickets['data']->data)){
				logging("Manage Host Tickets: Found tickets to close, closing each ticket", $logging);
				$post = array('input' => array());
				foreach ($tickets['data']->data as $ticket) {
					$post['input'][] = array('id' => $ticket->{2} , 'status' => 6);
					$glpi->updateItem('Ticket', $post);
				}
			}
		}
		break;
	case "UNREACHABLE":
		# We don't really care about warning states, since the host is probably still running...
		logging("Manage Host Tickets: Host '".$eventhost."' is UNKNOWN, exiting gracefully", $logging);
		break;
	case "DOWN":
		logging("Manage Host Tickets: Host '".$eventhost."' is DOWN, checking state", $logging);
		# Aha!  The host appears to have a problem - perhaps we should open a ticket...
		# Is this a "soft" or a "hard" state?
			switch ($hoststatetype) {
				case "SOFT":
					logging("Manage Host Tickets: Host State is SOFT, exiting gracefully", $logging);
					# We're in a "soft" state, meaning that Nagios is in the middle of retrying the
					# check before it turns into a "hard" state and contacts are notified...
					# We don't want to open a ticket on a "soft" state.
					break;

				case "HARD":
					logging("Manage Host Tickets: Host State is HARD, checking last problem ID", $logging);
					if ($lasthostproblemid != 1) {
						logging("Manage Host Tickets: Last Problem ID is incremented, checking host attempts", $logging);
						if ($hostattempts == $maxhostattempts){
							logging("Manage Host Tickets: Host Attempts equal Max Host Attempts, creating a new ticket", $logging);
							$ticket = array(
								'input' => array(
									'name' => "$eventhost is down!",
									'content' => "$eventhost is down.  Please check that the host is up and responding \n\r
									Check host status at $eventhost",
									'priority' => $critical_priority,
									'_users_id_requester' => $glpi_requester_user_id,
									'_groups_id_requester' => $glpi_requester_group_id,
									'_users_id_observer' => $glpi_watcher_user_id,
									'_groups_id_observer' => $glpi_watcher_group_id,
									'_users_id_assign' => $glpi_assign_user_id,
									'_groups_id_assign' => $glpi_assign_user_id
								)
							);
							$glpi->addItem('Ticket', $ticket);
						}
					}
					break;
				//end state cases
			}
	//end event cases
}

$glpi->killSession();
?>
