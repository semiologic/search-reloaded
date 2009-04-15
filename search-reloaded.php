<?php
/*
Plugin Name: Search Reloaded
Plugin URI: http://www.semiologic.com/software/search-reloaded/
Description: Replaces the default WordPress search engine with a rudimentary one that orders posts by relevance.
Version: 4.0 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts  and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('search-reloaded', null, basename(dirname(__FILE__)) . '/lang');


/**
 * search_reloaded
 *
 * @package Search Reloaded
 **/

$o = search_reloaded::get_options();

register_activation_hook(__FILE__, array('search_reloaded', 'activate'));
add_action('admin_menu', array('search_reloaded', 'admin_menu'));

if ( !extension_loaded('simplexml') || !class_exists('WP_Widget') || !$o['api_key'] ) {
	add_action('admin_notices', array('search_reloaded', 'admin_notices'));
} elseif ( !is_admin() ) {
	add_action('loop_start', array('search_reloaded', 'loop_start'));
}

unset($o);

class search_reloaded {
	/**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( !class_exists('ysearch') ) {
			include dirname(__FILE__) . '/ysearch/ysearch.php';
			ysearch::activate();
		}
	} # activate()
	
	
	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
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

	function admin_notices() {
		if ( !extension_loaded('simplexml') ) {
			echo '<div class="error">'
				. '<p>'
				. __('Search Reloaded requires the Simple XML extension to query Yahoo!\'s web services. Please contact your host and request that your server be configured accordingly.', 'search-reloaded')
				. '</p>'
				. '</div>' . "\n";
		} elseif ( !class_exists('WP_Widget') ) {
			echo '<div class="error">'
				. '<p>'
				. __('Search Reloaded requires WordPress 2.8 or later. Please upgrade your site.', 'search-reloaded')
				. '</p>'
				. '</div>' . "\n";
		} elseif ( !$o['api_key'] ) {
			echo '<div class="error">'
				. '<p>'
				. __('Search Reloaded is almost ready to be used on your site. Please browse <a href="options-general.php?page=search-reloaded">Settings / Search Reloaded</a> and configure it as needed.', 'seach-reloaded')
				. '</p>'
				. '</div>' . "\n";
		}
	} # admin_notices()
	
	
	/**
	 * loop_start()
	 *
	 * @return void
	 **/

	function loop_start() {
		static $did_search = false;
		
		if ( !is_search() || !in_the_loop() || is_feed() || $did_search ) return;
		
		$did_search = true;
		
		if ( !class_exists('ysearch') )
			include dirname(__FILE__) . '/ysearch/ysearch.php';
		
		$s = trim(stripslashes($_GET['s']));
		
		if ( !$s ) return;
		
		global $wp_query;
		if ( $wp_query->post_count ) // fast forward loop
			$wp_query->current_post = $wp_query->post_count - 1;
		
		remove_action('loop_start', array('search_reloaded', 'loop_start'));
		add_action('loop_end', array('search_reloaded', 'loop_end'));
		ob_start();
	} # loop_start()
	
	
	/**
	 * loop_end()
	 *
	 * @return void
	 **/

	function loop_end() {
		ob_get_clean();
		remove_action('loop_end', array('search_reloaded', 'loop_end'));
		
		global $wp_query;
		$start = intval($wp_query->query['paged']) ? ( 10 * ( intval($wp_query->query['paged']) - 1 ) ) : 0;
		
		# build search query
		$o = search_reloaded::get_options();
		
		$s = stripslashes($_GET['s']);
		
		if ( $o['site_wide'] ) {
			$s .= ' site:' . search_reloaded::get_domain();
		} else {
			$s .= ' site:' . get_option('home');
		}
		
		$res = ysearch::query($s, $start);
		
		global $wp_query;
		
		if ( $res === false ) {
			search_reloaded::display_posts($wp_query->posts);
		} else {
			$wp_query->max_num_pages = intval(ceil($res->attributes()->totalhits / 10));
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
		
		echo '<div class="post_list">' . "\n";
		
		echo '<p class="search_count">'
			. sprintf(__('%d-%d of %d results', 'search-reloaded'), $first, $last, $total)
			. '</p>' . "\n";
		
		echo '<ul>' . "\n";
		
		while ( have_posts() ) {
			the_post();
			
			echo '<li class="search_result">' . "\n"
				. '<h3 class="search_title">'
				. '<a href="' . get_permalink() . '">' . get_the_title() . '</a>'
				. '</h3>' . "\n";
			
			echo '<p class="search_content">' . apply_filters('the_excerpt', get_the_excerpt())  . '</p>' . "\n";
			
			echo '<p class="search_url">'
				. preg_replace("#https?://#", '', get_permalink())
				. '</p>' . "\n";
			
			echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '</div>' . "\n";
	} # display_posts()
	
	
	/**
	 * display_results()
	 *
	 * @param object $resultset
	 * @return void
	 **/

	function display_results($resultset) {
		$options = search_reloaded::get_options();
		
		if ( $options['site_wide'] ) {
			$repl = search_reloaded::get_domain();
		} else {
			$repl = get_option('home');
		}
		
		$find = "<b>$repl</b>";
		
		$start = intval($resultset->attributes()->start);
		$count = intval($resultset->attributes()->count);
		$total = intval($resultset->attributes()->totalhits);
		
		$first = $total ? ( $start + 1 ) : 0;
		$last = $start + $count;
		
		echo '<div class="post_list">' . "\n";
		
		echo '<p class="search_count">';
		
		if ( $options['add_credits'] ) {
			echo sprintf(__('%d-%d of %d results &bull; Powered by <a href="http://www.semiologic.com/software/search-reloaded/">Search Reloaded</a>', 'search-reloaded'), $first, $last, $total);
		} else {
			echo sprintf(__('%d-%d of %d results', 'search-reloaded'), $first, $last, $total);
		}
		
		echo '</p>' . "\n";
		
		echo '<ul>' . "\n";
		
		foreach ( $resultset->children() as $result ) {
			echo '<li class="search_result">' . "\n"
				. '<h3 class="search_title">'
				. '<a href="' . $result->clickurl . '">' . $result->title . '</a>'
				. '</h3>' . "\n";
			
			echo '<p class="search_content">' . str_replace($find, $repl, $result->abstract) . '</p>' . "\n";
			
			echo '<p class="search_url">'
				. user_trailingslashit(str_replace($find, $repl, $result->dispurl))
				. '</p>' . "\n";
			
			echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '</div>' . "\n";
	} # display_results()
	
	
	/**
	 * get_options
	 *
	 * @return array $options
	 **/

	function get_options() {
		static $o;
		
		if ( isset($o) && !is_admin() )
			return $o;
		
		$o = get_option('search_reloaded');
		
		if ( $o === false )
			$o = search_reloaded::init_options();
		
		$api_key = get_option('ysearch');
		$o['api_key'] = $api_key;
		
		return $o;
	} # get_options()
	
	
	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	function init_options() {
		$o = array(
			'site_wide' => '',
			'add_credits' => true,
			);
		
		update_option('search_reloaded', $o);
		
		if ( get_option('search_reloaded_version') || get_option('search_reloaded_installed') ) {
			global $wpdb;
			
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_title;");
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_keywords;");
			$wpdb->query("ALTER TABLE $wpdb->posts DROP COLUMN search_content;");
			
			delete_option('search_reloaded_version');
			delete_option('search_reloaded_installed');
			
			if ( !class_exists('ysearch') ) {
				include dirname(__FILE__) . '/ysearch/ysearch.php';
				ysearch::activate();
			}
		}
		
		return $o;
	} # init_options()
	
	
	/**
	 * get_domain()
	 *
	 * @return string $domain
	 **/

	function get_domain() {
		static $site_domain;
		
		if ( isset($site_domain) )
			return $site_domain;
		
		$site_domain = get_option('home');
		$site_domain = preg_replace("|^[^/]+://(?:www\.)?|i", '', $site_domain);
		$site_domain = preg_replace("|[/?#].*$|i", '', $site_domain);
		
		if ( $site_domain != 'localhost' && !preg_match("/\d+(\.\d+){3}/", $site_domain) ) {
			$tlds = array('wattle.id.au', 'emu.id.au', 'csiro.au', 'name.tr', 'conf.au', 'info.tr', 'info.au', 'gov.au', 'k12.tr', 'lel.br', 'ltd.uk', 'mat.br', 'jor.br', 'med.br', 'net.hk', 'net.eg', 'net.cn', 'net.br', 'net.au', 'mus.br', 'mil.tr', 'mil.br', 'net.lu', 'inf.br', 'fnd.br', 'fot.br', 'fst.br', 'g12.br', 'gb.com', 'gb.net', 'gen.tr', 'ggf.br', 'gob.mx', 'gov.br', 'gov.cn', 'gov.hk', 'gov.tr', 'idv.tw', 'imb.br', 'ind.br', 'far.br', 'net.mx', 'se.com', 'rec.br', 'qsl.br', 'psi.br', 'psc.br', 'pro.br', 'ppg.br', 'pol.tr', 'se.net', 'slg.br', 'vet.br', 'uk.net', 'uk.com', 'tur.br', 'trd.br', 'tmp.br', 'tel.tr', 'srv.br', 'plc.uk', 'org.uk', 'ntr.br', 'not.br', 'nom.br', 'no.com', 'net.uk', 'net.tw', 'net.tr', 'net.ru', 'odo.br', 'oop.br', 'org.tw', 'org.tr', 'org.ru', 'org.lu', 'org.hk', 'org.cn', 'org.br', 'org.au', 'web.tr', 'eun.eg', 'zlg.br', 'cng.br', 'com.eg', 'bio.br', 'agr.br', 'biz.tr', 'cnt.br', 'art.br', 'com.hk', 'adv.br', 'cim.br', 'com.mx', 'arq.br', 'com.ru', 'com.tr', 'bmd.br', 'com.tw', 'adm.br', 'ecn.br', 'edu.br', 'etc.br', 'eng.br', 'esp.br', 'com.au', 'com.br', 'ato.br', 'com.cn', 'eti.br', 'edu.au', 'bel.tr', 'edu.tr', 'asn.au', 'jl.cn', 'mo.cn', 'sh.cn', 'nm.cn', 'js.cn', 'jx.cn', 'am.br', 'sc.cn', 'sn.cn', 'me.uk', 'co.jp', 'ne.jp', 'sx.cn', 'ln.cn', 'co.uk', 'co.at', 'sd.cn', 'tj.cn', 'cq.cn', 'qh.cn', 'gs.cn', 'gr.jp', 'dr.tr', 'ac.jp', 'hb.cn', 'ac.cn', 'gd.cn', 'pp.ru', 'xj.cn', 'xz.cn', 'yn.cn', 'av.tr', 'fm.br', 'fj.cn', 'zj.cn', 'gx.cn', 'gz.cn', 'ha.cn', 'ah.cn', 'nx.cn', 'tv.br', 'tw.cn', 'bj.cn', 'id.au', 'or.at', 'hn.cn', 'ad.jp', 'hl.cn', 'hk.cn', 'ac.uk', 'hi.cn', 'he.cn', 'or.jp', 'name', 'info', 'aero', 'com', 'net', 'org', 'biz', 'edu', 'int', 'mil', 'ua', 'st', 'tw', 'sg', 'uk', 'au', 'za', 'yu', 'ws', 'at', 'us', 'vg', 'as', 'va', 'tv', 'pt', 'si', 'sk', 'ag', 'sm', 'ca', 'su', 'al', 'am', 'tc', 'th', 'tm', 'ro', 'tn', 'to', 'ru', 'se', 'sh', 'eu', 'dk', 'ie', 'il', 'de', 'cz', 'cy', 'cx', 'is', 'it', 'jp', 'ke', 'kr', 'la', 'hu', 'hm', 'hk', 'fi', 'fj', 'fo', 'fr', 'es', 'gb', 'eg', 'ge', 'ee', 'gl', 'ac', 'gr', 'gs', 'li', 'lk', 'cd', 'nl', 'no', 'cc', 'by', 'br', 'nu', 'nz', 'bg', 'be', 'ba', 'az', 'pk', 'ch', 'ck', 'cl', 'lt', 'lu', 'lv', 'ma', 'mc', 'md', 'mk', 'mn', 'ms', 'mt', 'mx', 'dz', 'cn', 'pl');
			
			$site_len = strlen($site_domain);
			
			for ( $i = 0; $i < count($tlds); $i++ ) {
				$tld = $tlds[$i];
				$tld_len = strlen($tld);
				
				# drop stuff that's too short
				if ( $site_len < $tld_len + 2 ) continue;
				
				# catch stuff like blahco.uk
				if ( substr($site_domain, -1 * $tld_len - 1, 1) != '.' ) continue;
				
				# match?
				if ( substr($site_domain, -1 * $tld_len) != $tld ) continue;
				
				# extract domain
				$site_domain = substr($site_domain, 0, $site_len - $tld_len - 1);
				$site_domain = explode('.', $site_domain);
				$site_domain = array_pop($site_domain);
				$site_domain = $site_domain . '.' . $tld;
			}
		}
	
		return $site_domain;
	} # get_domain()
} # search_reloaded


function search_reloaded_admin() {
	include_once dirname(__FILE__) . '/search-reloaded-admin.php';
	remove_action('admin_notices', array('search_reloaded', 'admin_notices'));
} # search_reloaded_admin()

add_action('load-settings_page_search-reloaded', 'search_reloaded_admin');

if ( !function_exists('load_ysearch') ) :
function load_ysearch() {
	include_once dirname(__FILE__) . '/ysearch/ysearch.php';
} # load_ysearch()
endif;
?>