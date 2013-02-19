<?php
/*
Version: 1.0.1
*/
// obsolete file

$active_plugins = get_option('active_plugins');

if ( !is_array($active_plugins) )
{
	$active_plugins = array();
}

foreach ( (array) $active_plugins as $key => $plugin )
{
	if ( $plugin == 'search-reloaded.php' )
	{
		unset($active_plugins[$key]);
		break;
	}
}

if ( !in_array('search-reloaded/search-reloaded.php', $active_plugins) )
{
	$active_plugins[] = 'search-reloaded/search-reloaded.php';
}

sort($active_plugins);

update_option('active_plugins', $active_plugins);
?>