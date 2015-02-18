<?php
//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit ();
define("plugin_file",__FILE__);
require_once( plugin_dir_path( plugin_file ).'MagenetLinkAutoinstall.php' ); 
global $magenetLinkAutoinstall;
$magenetLinkAutoinstall = new MagenetLinkAutoinstall();
$magenetLinkAutoinstall->uninstall();
?>