<?php
/*
Plugin Name: Search Reloaded
Plugin URI: http://www.semiologic.com/software/wp-tweaks/search-reloaded/
Description: Replaces the default WordPress search engine with a rudimentary one that orders posts by relevance.
Author: Denis de Bernardy
Version: 3.1.2
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/plugins
Update Tag: search_reloaded
Update Package: http://www.semiologic.com/media/software/wp-tweaks/search-reloaded/search-reloaded.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts  and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


class search_reloaded
{
	#
	# init()
	#
	
	function init()
	{	
		if ( version_compare(mysql_get_server_info(), '4.1', '<') )
		{
			add_action('admin_notices', array('search_reladed', 'mysql_warning'));
			return;
		}
		
		add_action('admin_menu', array('search_reloaded', 'meta_boxes'));
		
		add_filter('sem_api_key_protected', array('search_reloaded', 'sem_api_key_protected'));

		if ( !get_option('search_reloaded_installed') )
		{
			search_reloaded::install();
		}
		
		add_action('save_post', array('search_reloaded', 'index_post'));
		
		if ( !is_admin() )
		{
			$cur_ver = '3.1.2';
			
			if ( !( $ver = get_option('search_reloaded_version') )
				|| version_compare($ver, $cur_ver, '<')
				)
			{
				global $wpdb;

				$wpdb->query("
					UPDATE	$wpdb->posts
					SET		search_title = '',
							search_keywords = '',
							search_content = ''
					");
				update_option('search_reloaded_indexed', 0);
				update_option('search_reloaded_version', $cur_ver);
			}
			
			if ( !get_option('search_reloaded_indexed') )
			{
				add_action('shutdown', array('search_reloaded', 'index_posts'));
			}

			add_filter('posts_fields', array('search_reloaded', 'posts_fields'));
			add_filter('posts_where', array('search_reloaded', 'posts_where'));
			add_filter('posts_orderby', array('search_reloaded', 'posts_orderby'));
			
			#add_filter('posts_request', array('search_reloaded', 'posts_request'));
		}
	} # init()
	
	
	#
	# mysql_warning()
	#
	
	function mysql_warning()
	{
		echo '<div class="error">'
			. '<p><b style="color: firebrick;">Search Reloaded Error</b><br /><b>Your MySQL version is lower than 4.1.</b> It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.</p>'
			. '</div>';
	} # mysql_warning()


	#
	# sem_api_key_protected()
	#
	
	function sem_api_key_protected($array)
	{
		$array[] = 'http://www.semiologic.com/media/software/wp-tweaks/search-reloaded/search-reloaded.zip';
		
		return $array;
	} # sem_api_key_protected()
	
	
	#
	# meta_boxes()
	#
	
	function meta_boxes()
	{
		if ( !class_exists('widget_utils') ) return;
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();

		add_action('post_widget_config_affected', array('search_reloaded', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('search_reloaded', 'widget_config_affected'));
	} # meta_boxes()
	
	
	#
	# widget_config_affected()
	#
	
	function widget_config_affected()
	{
		echo '<li>'
			. 'Search Reloaded (exclude only)'
			. '</li>';
	} # widget_config_affected()
	
	
	#
	# posts_request()
	#
	
	function posts_request($str)
	{
		if ( is_search() )
		{
			global $wpdb;

			dump($str);
			dump($wpdb->get_results($str));
		}
		
		return $str;
	} # posts_request()
	
	
	#
	# posts_fields()
	#
	
	function posts_fields($str)
	{
		if ( !is_search() ) return $str;
		
		global $wpdb;
		global $wp_query;
		
		$qs = implode(' ', $wp_query->query_vars['search_terms']);

		$str = " $wpdb->posts.*,"
			. " ( "
			. " IF ( MATCH ($wpdb->posts.search_title, $wpdb->posts.search_keywords)"
				. " AGAINST ('" . $wpdb->escape($qs) . "'),"
				. " MATCH ($wpdb->posts.search_title, $wpdb->posts.search_keywords)"
				. " AGAINST ('" . $wpdb->escape($qs) . "'),"
				. " 0 )"
			. " + IF ( MATCH ($wpdb->posts.search_title, $wpdb->posts.search_keywords, $wpdb->posts.search_content)"
				. " AGAINST ('" . $wpdb->escape($qs) . "'),"
				. " MATCH ($wpdb->posts.search_title, $wpdb->posts.search_keywords, $wpdb->posts.search_content)"
				. " AGAINST ('" . $wpdb->escape($qs) . "'),"
				. " 0 )"
			. " ) "
			. " * IF ( $wpdb->posts.post_type = 'page',"
				. " 1.5,"
				. " 1 ) as search_score";
		
		return $str;
	} # posts_fields()
	
	
	#
	# posts_where()
	#
	
	function posts_where($str)
	{
		if ( !is_search() ) return $str;
		
		global $wp_query;
		global $wpdb;
		
		$qs = implode(' ', $wp_query->query_vars['search_terms']);
		
		$str = " AND"
			. " MATCH ($wpdb->posts.search_title, $wpdb->posts.search_keywords, $wpdb->posts.search_content)"
			. " AGAINST ('" . $wpdb->escape($qs) . "')"
			. " AND (wp_posts.post_status = 'publish' OR wp_posts.post_author = 1 AND wp_posts.post_status = 'private')";
		
		return $str;
	} # posts_where()
	
	
	#
	# posts_orderby()
	#
	
	function posts_orderby($str)
	{
		if ( !is_search() ) return $str;
		
		global $wpdb;
		
		$str = 'search_score DESC';
		
		return $str;
	} # posts_orderby()
	
	
	#
	# install()
	#
	
	function install()
	{
		global $wpdb;
		
		# enforce MyISAM
		$wpdb->query("ALTER TABLE `$wpdb->posts` ENGINE = MYISAM");
		
		# add three columns
		$wpdb->query("
			ALTER TABLE $wpdb->posts ADD COLUMN `search_title` text NOT NULL DEFAULT '';
			");

		$wpdb->query("
			ALTER TABLE $wpdb->posts ADD COLUMN `search_keywords` text NOT NULL DEFAULT '';
			");

		$wpdb->query("
			ALTER TABLE $wpdb->posts ADD COLUMN `search_content` longtext NOT NULL DEFAULT '';
			");
		
		# and two full text indexes
		$wpdb->query("
			ALTER TABLE $wpdb->posts ADD FULLTEXT `search_title` ( `search_title`, `search_keywords`);
			");

		$wpdb->query("
			ALTER TABLE $wpdb->posts ADD FULLTEXT `search_content` ( `search_title`, `search_keywords`, `search_content`);
			");
		
		update_option('search_reloaded_installed', 1);
	} # install()
	
	
	#
	# index_post()
	#
	
	function index_post($post_id)
	{
		$post_id = intval($post_id);
		
		if ( $post_id <= 0 ) return;
		
		$post = get_post($post_id);
		
		if ( $post->post_type == 'revision' ) return;
		
		if ( !$post
			|| $post->post_status != 'publish'
			|| !in_array($post->post_type, array('post', 'page', 'attachment'))
			)
		{
			return;
		}
		
		global $wpdb;
		
		if ( is_admin() )
		{
			# some plugins purposly skip outputting anything in the admin area
			
			$wpdb->query("
				UPDATE	$wpdb->posts
				SET		search_title = '',
						search_keywords = '',
						search_content = ''
				WHERE	ID = $post_id
				");
			
			update_option('search_reloaded_indexed', 0);
			
			return;
		}
		
		global $wp_query;

		setup_postdata($post);
		$wp_query->in_the_loop = true;
		
		#echo '<pre>';
		#echo "Indexing $post_id...";
		
		$title = $post->post_title;
		$keywords = implode(', ', search_reloaded::get_keywords($post_id, $post->post_type == 'post'));
		
		#$content = $post->post_content;
		
		$content = trim($post->post_content)
			? apply_filters('the_content', $post->post_content)
			: apply_filters('the_content', $post->post_excerpt);
		
		foreach ( array('title', 'keywords', 'content') as $var )
		{
			foreach ( array('script', 'style') as $junk )
			{
				$$var = preg_replace("/
					<\s*$junk\b
					.*
					<\s*\/\s*$junk\s*>
					/isUx", '', $$var);
			}
			
			$$var = strip_tags($$var);
			$$var = html_entity_decode($$var, ENT_NOQUOTES);
			$$var = str_replace("\r", "\n", $$var);
			$$var = trim($$var);
		}
		
		#dump($content);
		
		$wp_query->in_the_loop = false;
		
		$wpdb->query("
			UPDATE	$wpdb->posts
			SET		search_title = '" . $wpdb->escape($title) . "',
					search_keywords = '" . $wpdb->escape($keywords) . "',
					search_content = '" . $wpdb->escape($content) . "'
			WHERE	ID = $post_id
			");
		
		#echo '</pre>';
	} # index_post()
	
	
	#
	# get_keywords()
	#
	
	function get_keywords($post_id = null, $get_categories = false)
	{
		if ( !defined('highlights_cat_id') )
		{
			global $wpdb;
			
			$highlights_cat_id = $wpdb->get_var("
				SELECT
					term_id
				FROM
					$wpdb->terms
				WHERE
					slug = 'highlights'
				");

			define('highlights_cat_id', $highlights_cat_id ? intval($highlights_cat_id) : false);
		}
		
		$keywords = array();
		$exclude = array();
		
		if ( defined('main_cat_id') && main_cat_id )
		{
			$exclude[] = main_cat_id;
		}
		
		if ( defined('highlights_cat_id') && highlights_cat_id )
		{
			$exclude[] = highlights_cat_id;
		}
		
		if ( $get_categories
			&& ( $cats = get_the_category($post_id) )
			)
		{
			foreach ( $cats as $cat )
			{
				if ( !in_array($cat->term_id, $exclude) )
				{
					$keywords[] = $cat->name;
				}
			}
		}

		if ( $tags = get_the_tags($post_id) )
		{
			foreach ( $tags as $tag )
			{
				$keywords[] = $tag->name;
			}
		}
		
		$keywords = array_map('strtolower', $keywords);
		$keywords = array_unique($keywords);

		sort($keywords);
		
		return $keywords;
	} # get_keywords()
	
	
	#
	# index_posts()
	#
	
	function index_posts()
	{
		if ( is_admin()
			|| !( is_front_page() || is_home() || is_single() || is_archive() || is_search() )
			) return;
		
		#dump('index');
		
		global $wpdb;
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		$post_ids = (array) $wpdb->get_col("
			SELECT	ID
			FROM	$wpdb->posts
			WHERE	post_status = 'publish'
			AND		post_type IN ('post', 'page', 'attachment')
			AND		search_title = ''
			AND		search_content = ''
			AND		ID NOT IN ( $exclude_sql )
			LIMIT 50
			;");
		
		if ( $post_ids )
		{
			foreach ( $post_ids as $post_id )
			{
				#dump($post_id);
				search_reloaded::index_post($post_id);
			}
			
			update_option('search_reloaded_indexed', 0);
		}
		else
		{
			update_option('search_reloaded_indexed', 1);
		}
	} # index_posts()
} # search_reloaded

search_reloaded::init();

if ( is_admin() && !class_exists('widget_utils') )
{
	include dirname(__FILE__) . '/widget-utils.php';
}

?>