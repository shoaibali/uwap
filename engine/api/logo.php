<?php

/*
 * This API is reached through
 * 
 * 		appid.uwap.org/_/api/data.php
 *
 * This is used only indirectly through the UWAP core js API.
 * Data is fetched from a remote source and returned, using the specified handler, 
 * such as OAuth1, basic auth or similar
 */

require_once('../../lib/autoload.php');


$default = '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';


try {

	if (empty($_REQUEST['app'])) {
		throw new Exception("Missing parameter [url]");
	}

	$c = new Config($_REQUEST['app']);
	$ac = $c->getConfig();
	
	if (!empty($ac["logo"])) {

		header('Content-Type: image/png');
		echo base64_decode($ac["logo"]);
	} else {

		header('Content-Type: image/png');
		echo base64_decode($default);
	}


} catch(Exception $error) {


}


