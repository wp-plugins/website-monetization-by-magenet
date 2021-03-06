<?php
if(!function_exists('get_plugin_data'))
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$plugin_data = get_plugin_data(plugin_file);

define("magenet_plugin_version", $plugin_data['Version']);
if (!class_exists('MagenetLinkAutoinstall')) {
    class MagenetLinkAutoinstall {
        
        private $cache_time = 3600;
        private $api_host = "http://api.magenet.com";
        private $api_get = "/wordpress/get";
        private $api_test = "/wordpress/test";
        private $api_activate = "/wordpress/activate";
        private $api_deactivate = "/wordpress/deactivate";
        private $api_uninstall = "/wordpress/uninstall";
        private $is_active_seo_plugin = false;
		private $key = false;
        private $link_shown = 0;
        private $lastError = 0;

        public function MagenetLinkAutoinstall()   {
            global $wpdb;
            define('MagenetLinkAutoinstall', true);
            $this->plugin_name = plugin_basename(plugin_file);
	        $this->plugin_url = trailingslashit(WP_PLUGIN_URL.'/'.dirname(plugin_basename(plugin_file)));
            $this->tbl_magenet_links = $wpdb->prefix . 'magenet_links';

            register_activation_hook($this->plugin_name, array(&$this, 'activate'));
            register_deactivation_hook($this->plugin_name, array(&$this, 'deactivate'));
            //register_uninstall_hook($this->plugin_name, array(&$this, 'uninstall'));
            
            if (is_admin()) {
                add_action('wp_print_scripts', array(&$this, 'admin_load_scripts'));
                add_action('wp_print_styles', array(&$this, 'admin_load_styles'));
                add_action('admin_menu', array(&$this, 'admin_generate_menu'));                
            } else {
		if (!has_filter('the_content', array(&$this, 'add_magenet_links'))) {
		    add_filter('the_content', array(&$this, 'add_magenet_links'));
		}
            }
        }
        
        public function getSeoPluginParam() {
            if (!$this->is_active_seo_plugin) {
                $this->is_active_seo_plugin = get_option("magenet_is_active_seo_plugin");
            }
            return $this->is_active_seo_plugin;
        }
        public function setSeoPluginParam($seoparam) {
            update_option("magenet_is_active_seo_plugin", $seoparam);
            $this->is_active_seo_plugin = $seoparam;
        }
        
	public function getKey() {
            if (!$this->key) {
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
            
            if ( $wpdb->get_var("show tables like '".$table."'") != $table ) {
                dbDelta($sql_table_magenet_links);
                $wpdb->query($sql_add_index);
            }
			$result = $this->sendRequest($this->api_host.$this->api_activate, $this->getKey());
        }
        
        public function deactivate() {
			$result = $this->sendRequest($this->api_host.$this->api_deactivate, $this->getKey());
		    return true;
        }
        
        public function uninstall() {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}magenet_links");
			$result = $this->sendRequest($this->api_host.$this->api_uninstall, $this->getKey());
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
            global $post;
            $link_count = 1;
            $is_active_plugin = $this->getSeoPluginParam();
            if($is_active_plugin=='on' && ($post->post_type == 'page' OR $post->post_type == 'post')) $link_count = 2;

            $this->link_shown++;
            if($this->link_shown <= $link_count) {
			global $wpdb;
            	$link_data = $this->getLinks();
	        $content .= '<div class="mads-block">';
            	if (is_array($link_data) && count($link_data) > 0) {
                    foreach($link_data as $link) {
                    	$content .= "\n".$link['link_html'];
					}
                }
            	$content .='</div>';
            }
    
            return $content;
        }
     
        public function admin_magenet_settings() {
            global $wpdb;
            $magenet_key = $this->getKey();
            $is_active_seo_plugin = $this->getSeoPluginParam();
            $plugin_result_text = '';
            if(isset($_POST['seoplugin']) && !empty($_POST['seoplugin'])) {
                if (isset($_POST['seoparam']) && !empty($_POST['seoparam'])) {
                    $this->setSeoPluginParam($_POST['seoparam']);
                } else {
                    $this->setSeoPluginParam('off');
                }
                $is_active_seo_plugin = $this->getSeoPluginParam();
                $plugin_result_text = "<span style=\"color: #009900;\">Saved</span>";
            }
            if (isset($_POST['key']) && !empty($_POST['key'])) {
                $magenet_key = $_POST['key'];
                $test_key = $this->testKey($magenet_key);
                if ($test_key) {
                    $this->setKey($magenet_key);
                    $result_text = "<span style=\"color: #009900;\">Key confirmed</span>";
                } else {
					if($this -> lastError == 0) {
							$result_text = "<span style=\"color: #ca2222;\">Incorrect Key. Please try again</span>";
					} else {
							$result_text = "<span style=\"color: #ca2222;\">Temporary Error (".$this -> lastError."). Please try again later. If you continue to see this error over an extended period of time, <a href=\"http://www.magenet.com/contact-us/\" target=\"_blank\">please let us know</a> so we can look into the issue.</span>";
					}
                }
            }
            if (isset($_POST['update_data']) && $_POST['update_data'] == 1) {
                $result = $this->sendRequest($this->api_host.$this->api_get, $magenet_key);
                if ($result) {
                    $wpdb->query("DELETE FROM {$this->tbl_magenet_links} WHERE 1");
                    $new_links = json_decode($result, TRUE);
                    if (count($new_links)>0)
                        foreach($new_links as $new_link) {
                            if (isset($new_link['page_url']) && isset($new_link['issue_html'])) {
                                $wpdb->query($wpdb->prepare("INSERT INTO {$this->tbl_magenet_links}(page_url, link_html) VALUES (%s, %s)", $new_link['page_url'], $new_link['issue_html']));
                            }
                        }
                }
                update_option("magenet_links_last_update", time());
                $result_update_text = "<span style=\"color: #009900;\">Ads have been updated.</span>";
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
            $key = $this->getKey();
            if ($key) {
                $last_update_time = get_option("magenet_links_last_update");
                if ($last_update_time + $this->cache_time < time()) {
                    $result = $this->sendRequest($this->api_host.$this->api_get, $key);
                    if ($result) {
                        $wpdb->query("DELETE FROM {$this->tbl_magenet_links} WHERE 1");
                        $new_links = json_decode($result, TRUE);
                        if (count($new_links)>0)
                            foreach($new_links as $new_link) {
                                if (isset($new_link['page_url']) && isset($new_link['issue_html'])) {
                                    $wpdb->query($wpdb->prepare("INSERT INTO {$this->tbl_magenet_links}(page_url, link_html) VALUES (%s, %s)", $new_link['page_url'], $new_link['issue_html']));
                                }
                            }
                    }
                    update_option("magenet_links_last_update", time());
                }
				$site_url = str_replace("'", "\'", get_option("siteurl"));
                $page_url = parse_url($site_url.str_replace("'", "\'", $_SERVER["REQUEST_URI"]));
				$url_for_check = $page_url['scheme'] . "://" . (isset($page_url['host']) ? $page_url['host'] : '') . (isset($page_url['path']) ? $page_url['path'] : '');
                
                $check_page_without_last_slash_query = "";
				if($url_for_check[strlen($url_for_check)-1] == "/") {
					$check_page_without_last_slash_query = " OR page_url='" . substr($url_for_check, 0, -1) . "'";
				}
				$link_data = $wpdb->get_results("SELECT * FROM `" . $this->tbl_magenet_links . "` WHERE page_url='". $url_for_check ."'" . $check_page_without_last_slash_query, ARRAY_A);
				return $link_data;
            }
            return false;
        }
        
        public function sendRequest($url, $key) {            
            $siteurl = get_option("siteurl");
            $params = http_build_query(array(
                'url' => $siteurl,
                'key' => $key,
				'version' => magenet_plugin_version
            ));
            if (function_exists('curl_init') && function_exists('curl_exec')) {
                $ch = curl_init();
            	curl_setopt($ch, CURLOPT_URL, $url);
            	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");    
        		curl_setopt($ch, CURLOPT_POST, TRUE);
        		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            	$curl_result = curl_exec($ch);
                
				$this -> lastError = curl_errno($ch);
                if (!$this -> lastError) {
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
?>