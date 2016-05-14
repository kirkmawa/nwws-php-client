<?php

include 'vendor/autoload.php';

if($argc < 2) {
        echo "Usage: $argv[0] /path/to/config/file\n";
        exit;
}

//
// Parse config file
//
$CONF = json_decode(file_get_contents($argv[1]), TRUE);

//
// initialize JAXL object with initial config
//
$client = new JAXL(array(
	// (required) credentials
	'jid'       => $CONF['username'] . '@' . $CONF['server'],
	'pass'      => $CONF['password'],
	'log_level' => JAXL_INFO
));

$client->require_xep(array(
	'0045',	// MUC
	'0203',	// Delayed Delivery
	'0199'  // XMPP Ping
));

//
// add necessary event callbacks here
//

$_room_full_jid = "nwws@conference." . $CONF['server'] . "/" . $CONF['resource'];
$room_full_jid = new XMPPJid($_room_full_jid);

$client->add_cb('on_auth_success', function() {
	global $client, $room_full_jid;
	_info("got on_auth_success cb, jid ".$client->full_jid->to_string());

	// join muc room
	$client->xeps['0045']->join_room($room_full_jid);
});

$client->add_cb('on_auth_failure', function($reason) {
	global $client;
	$client->send_end_stream();
	_info("got on_auth_failure cb with reason $reason");
});

$client->add_cb('on_groupchat_message', function($stanza) {
	global $client;
	
	if (preg_match('/^\*\*WARNING\*\*/', $stanza->body)) {
		return;
	}
	if (preg_match('/issues  valid/', $stanza->body)) {
		return;
	}
	if (preg_match('/issues TST valid/', $stanza->body)) {
		return;
	}

	$from = new XMPPJid($stanza->from);
	$delay = $stanza->exists('delay', NS_DELAYED_DELIVERY);
	
	if($from->resource) {
		echo "message stanza rcvd from ".$from->resource." saying... ".$stanza->body.($delay ? ", delay timestamp ".$delay->attrs['stamp'] : ", timestamp ".gmdate("Y-m-dTH:i:sZ")).PHP_EOL;
	}
	else {
		$subject = $stanza->exists('subject');
		if($subject) {
			echo "room subject: ".$subject->text.($delay ? ", delay timestamp ".$delay->attrs['stamp'] : ", timestamp ".gmdate("Y-m-dTH:i:sZ")).PHP_EOL;
		}
	}

	for($i=0; $i<count($stanza->childrens); $i++) {
		$child = new JAXLXml($stanza->childrens[$i]);
		if ($child->name->name === 'x') {
			//var_dump($child->name);
			$awipsid  = '';
			$wfo      = '';
			$prodDate = '';
			$id       = '';
			if (isset($child->name->attrs['awipsid'])) {
				$awipsid = $child->name->attrs['awipsid'];
				echo "**DEBUG** \$awipsid = $awipsid\n";
			}
			if (isset($child->name->attrs['issue'])) {
				$wfo = $child->name->attrs['cccc'];
				echo "**DEBUG** \$wfo = $wfo\n";
			}
			if (isset($child->name->attrs['issue'])) {
				$prodDate = preg_replace('/[\-T\:]/', '', $child->name->attrs['issue']);
				$prodDate = preg_replace('/Z$/', '', $prodDate);
				echo "**DEBUG** \$prodDate = $prodDate\n";
			}
			if (isset($child->name->attrs['id'])) {
				$id = $child->name->attrs['id'];
				echo "**DEBUG** \$id = $id\n";
			}
			// TODO: Write out file to archive directory
			if ($awipsid !== '' && $wfo !== '' && $prodDate !== '' && $id !== '') {
				$prod_contents = preg_split("/\n\n/", $child->name->text);
				for($j=0; $j<count($prod_contents); $j++) {
					print $prod_contents[$j] . "\n";
				}
			}
		}
	}

});

$client->add_cb('on_presence_stanza', function($stanza) {
	/*
	global $client, $room_full_jid;
	
	$from = new XMPPJid($stanza->from);
	
	// self-stanza received, we now have complete room roster
	if(strtolower($from->to_string()) == strtolower($room_full_jid->to_string())) {
		if(($x = $stanza->exists('x', NS_MUC.'#user')) !== false) {
			if(($status = $x->exists('status', null, array('code'=>'110'))) !== false) {
				$item = $x->exists('item');
				_info("xmlns #user exists with x ".$x->ns." status ".$status->attrs['code'].", affiliation:".$item->attrs['affiliation'].", role:".$item->attrs['role']);
			}
			else {
				_info("xmlns #user have no x child element");
			}
		}
		else {
			_warning("=======> odd case 1");
		}
	}
	// stanza from other users received
	else if(strtolower($from->bare) == strtolower($room_full_jid->bare)) {
		if(($x = $stanza->exists('x', NS_MUC.'#user')) !== false) {
			$item = $x->exists('item');
			echo "presence stanza of type ".($stanza->type ? $stanza->type : "available")." received from ".$from->resource.", affiliation:".$item->attrs['affiliation'].", role:".$item->attrs['role'].PHP_EOL;
		}
		else {
			_warning("=======> odd case 2");
		}
	}
	else {
		_warning("=======> odd case 3");
	}
	*/
});

$client->add_cb('on_disconnect', function() {
	_info("got on_disconnect cb");
});

//
// finally start configured xmpp stream
//
$client->start();
echo "done\n";

?>
