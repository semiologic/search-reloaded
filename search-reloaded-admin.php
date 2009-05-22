<?php
/**
 * search_reloaded_admin
 *
 * @package Search Reloaded
 **/

add_action('settings_page_search-reloaded', array('search_reloaded_admin', 'save_options'), 0);

class search_reloaded_admin {
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST )
			return;
		
		check_admin_referer('search_reloaded');
		
		$api_key = stripslashes($_POST['api_key']);
		$site_wide = isset($_POST['site_wide']);
		$add_credits = isset($_POST['add_credits']);
		
		$options = compact('site_wide', 'add_credits');
		update_option('search_reloaded', $options);
		update_option('ysearch', $api_key);
		
		echo '<div class="updated fade">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'search-reloaded')
				. '</strong>'
			. '</p>' . "\n"
			. '</div>' . "\n";
	} # save_options()
	
	
	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	function edit_options() {
		echo '<div class="wrap">' . "\n"
			. '<form action="" method="post">' . "\n";
		
		wp_nonce_field('search_reloaded');
		
		echo '<h2>' . __('Search Reloaded Settings', 'search-reloaded') . '</h2>' . "\n";
		
		extract(search_reloaded::get_options());
		
		echo '<table class="form-table">' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Yahoo! BOSS AppID', 'search-reloaded')
			. '</th>' . "\n"
			. '<td>' . "\n"
			. '<input type="text" name="api_key" class="widefat"'
				. ' value="' . esc_attr($api_key) . '"'
				. ' />'
			. '<p>' . __('This is the <a href="http://developer.yahoo.com/search/boss/"><em>Build your Own Search Service</em></a> Application ID that you\'ll be using on this site. You can <a href="https://developer.yahoo.com/wsregapp/">get one for free</a> from Yahoo!.', 'search-reloaded') . '</p>' . "\n"
			. '<p>' . __('More often than not, a single AppID will be enough for all of your sites. In Yahoo\'s application registration form, enter the url of any of them as the application\'s location, and paste the AppID that was assigned to you in the above field. Keep Yahoo\'s <a href="http://developer.yahoo.com/search/boss/fees.html">fee structure</a> in mind if you\'ve heavily searched sites, however. In that case, you may want to get a separate AppID for each of latter.', 'search-reloaded') . '</p>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Site to Search', 'search-reloaded')
			. '</th>' . "\n"
			. '<td>' . "\n"
			. '<label>'
			. '<input name="site_wide" type="checkbox"'
				. ( $site_wide
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. "&nbsp;"
			. sprintf(__('Return search results from this entire domain (<em>%s</em>) rather than from this WordPress installation only.', 'search-reloaded'), search_reloaded::get_domain())
			. '</label>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Display Credits', 'search-reloaded')
			. '</th>' . "\n"
			. '<td>' . "\n"
			. '<label>'
			. '<input name="add_credits" type="checkbox"'
				. ( $add_credits
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. "&nbsp;"
			. __('Display <em>Powered by <a href="http://www.semiologic.com/software/search-reloaded/">Search Reloaded</a></em> on search results pages. (Combine this with the <a href="http://www.semiologic.com/software/sem-affiliate/">Semiologic Affiliate plugin</a>.)', 'search-reloaded')
			. '</label>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '</table>' . "\n";

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'search-reloaded')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n"
			. '</div>' . "\n";
	} # edit_options()
} # search_reloaded_admin
?>