<?php
/*
Plugin Name: Search Reloaded
Plugin URI: http://www.semiologic.com/software/search-reloaded/
Description: RETIRED - Replaces the default WordPress search engine with Yahoo! search.
Version: 4.2.2
Author: Denis de Bernardy & Mike Koepke
Author URI: https://www.semiologic.com
Text Domain: search-reloaded
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts  and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/

/*
 * This plugin has been retired.  No further development will occur on it.
 * */

// Disable the plugin

$active_plugins = get_option('active_plugins');

if ( !is_array($active_plugins) )
{
	$active_plugins = array();
}

foreach ( (array) $active_plugins as $key => $plugin )
{
	if ( $plugin == 'search-reloaded/search-reloaded.php' )
	{
		unset($active_plugins[$key]);
		break;
	}
}

sort($active_plugins);

update_option('active_plugins', $active_plugins);

/**
 * search_reloaded
 *
 * @package Search Reloaded
 **/

class search_reloaded {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			$this->plugin_path . 'lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */
	public function __construct() {
	    $this->plugin_url    = plugins_url( '/', __FILE__ );
        $this->plugin_path   = plugin_dir_path( __FILE__ );
        $this->load_language( 'search-reloaded' );

		add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }


	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		if ( get_site_option('ysearch') === false ) {
			update_site_option('ysearch', get_option('ysearch') ? get_option('ysearch') : '');
		}

		$o = search_reloaded::get_options();

		register_activation_hook(__FILE__, array($this, 'activate'));
		add_action('admin_menu', array($this, 'admin_menu'));

		if ( !extension_loaded('simplexml') || !function_exists('get_transient') || !$o['api_key'] ) {
			add_action('admin_notices', array($this, 'admin_notices'));
		} elseif ( !is_admin() ) {
			add_action('loop_start', array($this, 'loop_start'));
		}

		unset($o);
	}


    /**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( !extension_loaded('simplexml') )
			return;
		
		load_ysearch();
		ysearch::activate();
	} # activate()
	
	
	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
		if ( function_exists('is_super_admin') && !is_super_admin() )
			return;
		
		add_options_page(
			__('Search Reloaded', 'search-reloaded'),
			__('Search Reloaded', 'search-reloaded'),
			'manage_options',
			'search-reloaded',
			array('search_reloaded_admin', 'edit_options')
			);
	} # admin_menu()
	
	
	/**
	 * admin_notices()
	 *
	 * @return void
	 **/

	static function admin_notices() {
		$o = search_reloaded::get_options();
		
		if ( !current_user_can('manage_options') ||
			function_exists('is_super_admin') && !is_super_admin() ) {
			return;
		} elseif ( !extension_loaded('simplexml') ) {
			echo '<div class="error">'
				. '<p>'
				. __('Search Reloaded requires the Simple XML extension to query Yahoo!\'s web services. Please contact your host and request that your server be configured accordingly.', 'search-reloaded')
				. '</p>'
				. '</div>' . "\n";
		} elseif ( !function_exists('get_transient') ) {
			echo '<div class="error">'
				. '<p>'
				. __('Search Reloaded requires WordPress 2.8 or later. Please upgrade your site.', 'search-reloaded')
				. '</p>'
				. '</div>' . "\n";
		} elseif ( empty($o['api_key']) || (isset($o['api_key']) && !$o['api_key']) ) {
			echo '<div class="error">'
				. '<p>'
				. __('The Search Reloaded plugin is almost ready. Please configure it under <a href="options-general.php?page=search-reloaded">Settings / Search Reloaded</a>. This will allow you to use Yahoo\'s search API in place of WordPress to search your site.', 'seach-reloaded')
				. '</p>'
				. '</div>' . "\n";
		}
	} # admin_notices()
	
	
	/**
	 * loop_start()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/
	
	function loop_start(&$wp_query) {
		global $wp_the_query;
		
		$s = isset($_GET['s']) ? trim(stripslashes($_GET['s'])) : '';
		
		if ( $wp_the_query !== $wp_query || !is_search() || !$s )
			return;
		
		static $done = false;
		
		if ( $done )
			return;
		
		add_action('loop_end', array($this, 'loop_end'));
		ob_start();
		$done = true;
	} # loop_start()
	
	
	/**
	 * loop_end()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/

	function loop_end(&$wp_query) {
		global $wp_the_query;
		
		if ( $wp_the_query !== $wp_query )
			return;
		
		static $done = false;
		
		if ( $done )
			return;
		
		ob_get_clean();
		$done = true;
		
		$start = (isset($wp_query->query['paged']) && intval($wp_query->query['paged'])) ? ( 10 * ( intval($wp_query->query['paged']) - 1 ) ) : 0;
		
		# build search query
		$o = search_reloaded::get_options();
		
		$s = trim(stripslashes($_GET['s']));
		
		if ( $o['site_wide'] ) {
			$s .= ' sites:' . search_reloaded::get_domain();
		} else {
			$s .= ' sites:' . get_option('home');
		}
		
		load_ysearch();
		$res = ysearch::query($s, $start);
		
		if ( $res === false ) {
			search_reloaded::display_posts($wp_query->posts);
		} else {
			$obj = $res->attributes();
			$max_num_pages = intval(ceil($obj->totalhits / 10));
			$wp_query->max_num_pages = min($wp_query->max_num_pages, $max_num_pages);
			search_reloaded::display_results($res);
		}
	} # loop_end()
	
	
	/**
	 * display_posts
	 *
	 * @param array $posts
	 * @return void
	 **/

	function display_posts($posts) {
		global $wp_query;
		$wp_query->rewind_posts();
		
		$count = sizeof($wp_query->posts);
		$start = intval($wp_query->query_vars['paged'])
			? ( ( intval($wp_query->query_vars['paged']) - 1 ) * $wp_query->query_vars['posts_per_page'] )
			: 0;
		$total = $wp_query->found_posts;
		
		$first = $total ? ( $start + 1 ) : 0;
		$last = $start + $count;
		
		echo '<div class="entry">' . "\n"
			. '<div class="entry_top"><div class="hidden"></div></div>' . "\n"
			. '<div class="post_list">' . "\n";
		
		echo '<p class="search_count">'
			. sprintf(__('%1$d-%2$d of %3$d results', 'search-reloaded'), $first, $last, $total)
			. '</p>' . "\n";
		
		echo '<ul>' . "\n";
		
		while ( have_posts() ) {
			the_post();
			
			echo '<li class="search_result">' . "\n"
				. '<h3 class="search_title">'
				. '<a href="' . esc_url(apply_filters('the_permalink', get_permalink())) . '">' . get_the_title() . '</a>'
				. '</h3>' . "\n";
			
			echo '<div class="search_content">' . apply_filters('the_excerpt', get_the_excerpt())  . '</div>' . "\n";
			
			echo '<p class="search_url">'
				. preg_replace("#https?://#", '', apply_filters('the_permalink', get_permalink()))
				. '</p>' . "\n";
			
			echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '</div>' . "\n"
			. '<div class="entry_bottom"><div class="hidden"></div></div>' . "\n"
			. '</div>' . "\n";
	} # display_posts()
	
	
	/**
	 * display_results()
	 *
	 * @param object $resultset
	 * @return void
	 **/

	function display_results($resultset) {
		global $wp_query;
		
		$options = search_reloaded::get_options();
		
		$obj = $resultset->attributes();
		$start = intval($obj->start);
		$count = intval($obj->count);
		$total = intval($obj->totalhits);
		
		$first = $total ? ( $start + 1 ) : 0;
		$last = $start + $count;
		
		$total = min($total, $wp_query->found_posts * 10);
		
		echo '<div class="entry">' . "\n"
			. '<div class="entry_top"><div class="hidden"></div></div>' . "\n"
			. '<div class="post_list">' . "\n";
		
		echo '<p class="search_count">';
		
		if ( $options['add_credits'] ) {
			echo sprintf(__('%1$d-%2$d of %3$d results &bull; Powered by <a href="%4$s">Search Reloaded</a>', 'search-reloaded'), $first, $last, $total, 'http://www.semiologic.com/software/search-reloaded/');
		} else {
			echo sprintf(__('%1$d-%2$d of %3$d results', 'search-reloaded'), $first, $last, $total);
		}
		
		echo '</p>' . "\n";
		
		if ( $total ) {
			$has_permalinks = get_option('permalink_structure');
			
			echo '<ul>' . "\n";

			foreach ( $resultset->children() as $result ) {
				echo '<li class="search_result">' . "\n"
					. '<h3 class="search_title">'
					. '<a href="' . esc_url($result->clickurl) . '">' . $result->title . '</a>'
					. '</h3>' . "\n";

				echo '<div class="search_content">' . $result->abstract . '</div>' . "\n";
				
				# strip bold from site domain
				$dispurl = preg_replace("|^([^/]*)<b>([^/]+)</b>([^/]*)|", "$1$2$3", $result->dispurl);
				
				echo '<p class="search_url">'
					. ( $has_permalinks
						? user_trailingslashit($dispurl)
						: $dispurl
						)
					. '</p>' . "\n";

				echo '</li>' . "\n";
			}

			echo '</ul>' . "\n";
		}
		
		echo '</div>' . "\n"
			. '<div class="entry_bottom"><div class="hidden"></div></div>' . "\n"
			. '</div>' . "\n";
	} # display_results()
	
	
	/**
	 * get_options
	 *
	 * @return array $options
	 **/

	static function get_options() {
		static $o;
		
		if ( isset($o) && !is_admin() )
			return $o;
		
		$o = get_site_option('search_reloaded');
		
		if ( $o === false )
			$o = search_reloaded::init_options();
		
		$api_key = get_site_option('ysearch');
		$o['api_key'] = $api_key;
		
		$o['site_wide'] &= !( function_exists('is_multisite') && is_multisite() );
		
		return $o;
	} # get_options()
	
	
	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	function init_options() {
		$o = array(
			'site_wide' => !( function_exists('is_multisite') && is_multisite() ),
			'add_credits' => true,
			);
		
		update_site_option('search_reloaded', $o);
		
		if ( get_option('search_reloaded_version') || get_option('search_reloaded_installed') ) {
			global $wpdb;
			
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_title;");
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_keywords;");
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_content;");
			
			delete_option('search_reloaded_version');
			delete_option('search_reloaded_installed');
		}
		
		return $o;
	} # init_options()
	
	
	/**
	 * get_domain()
	 *
	 * @return string $domain
	 **/

	static function get_domain() {
		static $site_domain;
		
		if ( isset($site_domain) )
			return $site_domain;
		
		$site_domain = get_option('home');
		$site_domain = parse_url($site_domain);
        if ($site_domain == false)
            return false;
        elseif (is_array($site_domain)) {
            if (isset($site_domain['host']))
                $site_domain = $site_domain['host'];
            else
                return false;
        }
		$site_domain = preg_replace("/^www\./i", '', $site_domain);
		
		# The following is not bullet proof, but it's good enough for a WP site
		if ( $site_domain != 'localhost' && !preg_match("/\d+(\.\d+){3}/", $site_domain) ) {
			if ( preg_match("/\.([^.]+)$/", $site_domain, $tld) ) {
				$tld = end($tld);
			} else {
				$site_domain = false;
				return false;
			}
			
			$site_domain = substr($site_domain, 0, strlen($site_domain) - 1 - strlen($tld));
			
			if ( preg_match("/\.([^.]+)$/", $site_domain, $subtld) ) {
				$subtld = end($subtld);
				if ( strlen($subtld) <= 4 ) {
					$site_domain = substr($site_domain, 0, strlen($site_domain) - 1 - strlen($subtld));
					$site_domain = explode('.', $site_domain);
					$site_domain = array_pop($site_domain);
					$site_domain .= ".$subtld";
				} else {
					$site_domain = $subtld;
				}
			}
			
			$site_domain = ".$site_domain.$tld";
		}
		
		return $site_domain;
	} # get_domain()
} # search_reloaded


function search_reloaded_admin() {
	if ( !extension_loaded('simplexml') )
		return;
	
	include_once dirname(__FILE__) . '/search-reloaded-admin.php';
	remove_action('admin_notices', array('search_reloaded', 'admin_notices'));
} # search_reloaded_admin()

add_action('load-settings_page_search-reloaded', 'search_reloaded_admin');

if ( !function_exists('load_ysearch') ) :
function load_ysearch() {
	if ( !class_exists('ysearch') )
		include dirname(__FILE__) . '/ysearch/ysearch.php';
} # load_ysearch()
endif;

$search_reloaded = search_reloaded::get_instance();