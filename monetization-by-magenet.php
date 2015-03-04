<?php
/*
Plugin Name: Website Monetization by MageNet
Description: Website Monetization by MageNet allows you to sell contextual ads from your pages automatically and receive payments with PayPal. To get started: 1) Click the "Activate" link to the left of this description, 2) <a href="http://magenet.com" target="_blank">Sign up for a MageNet Key</a>, and 3) Go to Settings > "Website Monetization by MageNet" configuration page, and save your MageNet Key.
Version: 1.0.18
Author: MageNet.com
Author URI: http://magenet.com
*/
define("plugin_file",__FILE__);
// Stop direct call
if(preg_match('#' . basename(plugin_file) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

if (!function_exists('json_decode')) {
    function json_decode($json, $assoc) {
        include_once('JSON.php');
        $use = $assoc ? SERVICES_JSON_LOOSE_TYPE : 0;
		$jsonO = new Services_JSON($use);
    	return $jsonO->decode($json);
    }
}

require_once( plugin_dir_path( plugin_file ).'MagenetLinkAutoinstall.php' ); 
global $magenetLinkAutoinstall;
$magenetLinkAutoinstall = new MagenetLinkAutoinstall();
?>