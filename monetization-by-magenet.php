<?php
/*
Plugin Name: Website Monetization by MageNet
Description: Website Monetization by MageNet allows you to sell contextual ads from your pages automatically and receive payments with PayPal. To get started: 1) Click the "Activate" link to the left of this description, 2) <a href="http://magenet.com" target="_blank">Sign up for a MageNet Key</a>, and 3) Go to Settings > "Website Monetization by MageNet" configuration page, and save your MageNet Key.
Version: 1.0.3
Author: MageNet.com
Author URI: http://magenet.com
*/
// Stop direct call
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

if (!function_exists('json_decode')) {
    function json_decode($json, $assoc) {
        include_once('JSON.php');
        $use = $assoc ? SERVICES_JSON_LOOSE_TYPE : 0;
		$jsonO = new Services_JSON($use);
    	return $jsonO->decode($json);
    }
}
 
if (!class_exists('MagenetLinkAutoinstall')) {
    class MagenetLinkAutoinstall {
        
        private $cache_time = 3600;
        private $api_host = "http://api.magenet.com";
        private $api_get = "/wordpress/get";
        private $api_test = "/wordpress/test";
        private $key = 0;
        
        public function MagenetLinkAutoinstall()   {
            global $wpdb;
            define('MagenetLinkAutoinstall', true);
            $this->plugin_name = plugin_basename(__FILE__);
            $this->plugin_url = trailingslashit(WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)));
            $this->tbl_magenet_links = $wpdb->prefix . 'magenet_links';

            register_activation_hook($this->plugin_name, array(&$this, 'activate'));
            register_deactivation_hook($this->plugin_name, array(&$this, 'deactivate'));
            register_uninstall_hook($this->plugin_name, array(&$this, 'uninstall'));
            
            if (is_admin()) {
                add_action('wp_print_scripts', array(&$this, 'admin_load_scripts'));
                add_action('wp_print_styles', array(&$this, 'admin_load_styles'));
                add_action('admin_menu', array(&$this, 'admin_generate_menu'));                
            } else {
                if (!has_filter('the_content', array(&$this, 'add_magenet_links')))
                    add_filter('the_content', array(&$this, 'add_magenet_links'));
            }
        }
        
        public function getKey() {
            if ($this->key == 0) {
                $this->key = get_option("magenet_links_autoinstall_key");
            }
            return $this->key;
        }
        
        public function setKey($key) {
            update_option("magenet_links_autoinstall_key", $key);
            $this->key = $key;
        }
        
        public function activate() {
            global $wpdb;            
            require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
            
            $table = $this->tbl_magenet_links;
            if (version_compare(mysql_get_server_info(), '4.1.0', '>=')) {
                if (!empty($wpdb->charset))
                    $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
                if (!empty($wpdb->collate))
                    $charset_collate .= " COLLATE $wpdb->collate";
            }
            
            $sql_table_magenet_links = "
                CREATE TABLE `".$wpdb->prefix."magenet_links` (
                `ID` INT(10) UNSIGNED NULL AUTO_INCREMENT,
                `page_url` TEXT NOT NULL,
                `link_html` TEXT NOT NULL,
                PRIMARY KEY (`ID`)
                )".$charset_collate.";";
            $sql_add_index = "CREATE INDEX page_url ON `".$wpdb->prefix."magenet_links` (page_url(100));";
            
            // Проверка на существование таблицы
            if ( $wpdb->get_var("show tables like '".$table."'") != $table ) {
                dbDelta($sql_table_magenet_links);
                $wpdb->query($sql_add_index);
            }        
        }
        
        public function deactivate() {
            return true;
        }
        
        public function uninstall() {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}magenet_links");
        }
        
        public function admin_load_scripts() {
            wp_register_script('magenetLinkAutoinstallAdminJs', $this->plugin_url . 'js/admin-scripts.js' );            
            wp_enqueue_script('magenetLinkAutoinstallAdminJs');
        }
        
        public function admin_load_styles() {
            wp_register_style('magenetLinkAutoinstallAdminCss', $this->plugin_url . 'css/admin-style.css' );
            wp_enqueue_style('magenetLinkAutoinstallAdminCss');
        }
        
        public function admin_generate_menu() {
            add_options_page('Website Monetization by MageNet', 'Website Monetization by MageNet', 'manage_options', 'magenet-links-settings', array(&$this, 'admin_magenet_settings'));
        }
        
        public function add_magenet_links($content) {
            global $wpdb;
            $link_data = $this->getLinks();
            $content .= '<div class="mads-block">';
            if (count($link_data) > 0) {
                foreach($link_data as $link) {
                    $content .= "\n".$link['link_html'];
                }
            }
            $content .='</div>';
            return $content;
        }
        
        public function admin_magenet_settings() {
            global $wpdb;
            if (isset($_POST['key']) && !empty($_POST['key'])) {
                $magenet_key = $_POST['key'];
                $test_key = $this->testKey($magenet_key);
                if ($test_key) {
                    $this->setKey($magenet_key);
                    $result_text = "<span style=\"color: #009900;\">Key confirmed</span>";
                } else {
                    $result_text = "<span style=\"color: #ca2222;\">Incorrect Key. Please try again</span>";
                }
            } else {
                $magenet_key = $this->getKey();
            }
            $link_data = $wpdb->get_results("SELECT * FROM `" . $this->tbl_magenet_links . "`", ARRAY_A);
            include_once('view-settings.php');
        }
        
        public function testKey($key) {
            $result = $this->sendRequest($this->api_host.$this->api_test, $key);
            return $result === "1";
        }
        
        public function getLinks() {
            global $wpdb;
            $last_update_time = get_option("magenet_links_last_update");
            if ($last_update_time + $this->cache_time < time()) {
                $key = $this->getKey();
                $result = $this->sendRequest($this->api_host.$this->api_get, $key);
                if ($result) {
                    $wpdb->query("DELETE FROM {$this->tbl_magenet_links} WHERE 1");
                    $new_links = json_decode($result, TRUE);
                    foreach($new_links as $new_link) {
                        if (isset($new_link['page_url']) && isset($new_link['issue_html'])) {
                            $wpdb->query($wpdb->prepare("INSERT INTO {$this->tbl_magenet_links}(page_url, link_html) VALUES (%s, %s)", $new_link['page_url'], $new_link['issue_html']));
                        }
                    }
                    update_option("magenet_links_last_update", time());
                }
            }
            $site_url = str_replace("'", "\'", get_option("siteurl"));
            $page_url = str_replace("'", "\'", $_SERVER["REQUEST_URI"]);
            $link_data = $wpdb->get_results("SELECT * FROM `" . $this->tbl_magenet_links . "` WHERE page_url='".$page_url."' OR page_url='".$site_url.$page_url."'", ARRAY_A);
            return $link_data;
        }
        
        public function sendRequest($url, $key) {            
            $siteurl = get_option("siteurl");
            $params = http_build_query(array(
                'url' => $siteurl,
                'key' => $key
            ));
            if (function_exists('curl_init')) {
                $ch = curl_init();
            	curl_setopt($ch, CURLOPT_URL, $url);
            	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");    
        		curl_setopt($ch, CURLOPT_POST, TRUE);
        		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            	$curl_result = curl_exec($ch);
                
                if (!curl_errno($ch)) {
                    $result = $curl_result;
                } else {
                    $result = false;
                }
                curl_close($ch);  
            } else {
                $url = $url."?".$params;
                $data = file_get_contents($url, false);
                $result = $data;                
            }
            return $result;
        }
    }
}

global $magenetLinkAutoinstall;
$magenetLinkAutoinstall = new MagenetLinkAutoinstall();
?>