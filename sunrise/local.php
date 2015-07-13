<?php
//FOR MAKE GLORIOUS GOOD OF KAZAHKSTAN

if ( !defined( 'SUNRISE_LOADED' ) )
	define( 'SUNRISE_LOADED', 1 );

if ( defined( 'COOKIE_DOMAIN' ) ) {
	die( 'The constant "COOKIE_DOMAIN" is defined (probably in wp-config.php). Please remove or comment out that define() line.' );
}

//The list of top-level domains we're looking for
$tlds = array( 'bangordailynews.com' );

//Basically, it goes like this: If the site is mysite.com, the admin site is mysite.bangordailynews.com.
//But we need to get the actual site name in order to select its ID, etc. So query for all sorts of variations
//and to get the correct one.
$possible_domains = array(
	$_SERVER[ 'HTTP_HOST' ]
);

//Go through each of the TLDs and replace .bangordailynews.com with the tlds, to check for a match in the db
// Why are we doing this, you ask? 
foreach( $tlds as $tld ) {
	$possible_domains[] = str_replace( array( 'bdn.com', 'bdnstage.com' ), $tld, $_SERVER[ 'HTTP_HOST' ] );
}

$possible_domains = array_unique( $possible_domains );

//Now our array of possible domains looks like
// array( 'example.com', 'example.net', 'example.org', 'example.me', 'example.bangordailynews.com' );

$implode = array();
foreach( $possible_domains as $key => $value ) {
	$implode[] = '%s';
}

//Now we're going to get any websites that match these URLs.
$blog = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->blogs . ' WHERE domain IN (' . implode( ',', $implode ) . ') ORDER BY CHAR_LENGTH(domain) DESC', $possible_domains ) );

if( $blog ) {
	
	
	//We found a blog! Set it up and cache it
	$current_blog = $blog;
	$original_domain = $current_blog->domain;
	$current_blog->domain = $_SERVER[ 'HTTP_HOST' ];
	$current_blog->path = '/';
	$blog_id = $current_blog->blog_id;
	$site_id = $current_blog->site_id;

	define( 'COOKIE_DOMAIN', $_SERVER[ 'HTTP_HOST' ] );

	$current_site = $wpdb->get_row( "SELECT * from {$wpdb->site} WHERE id = '{$current_blog->site_id}' LIMIT 0,1" );
	$current_site->blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain='{$current_site->domain}' AND path='{$current_site->path}'" );
	define( 'DOMAIN_MAPPING', 1 );

	wp_cache_set( 'domain_mapping_blog' . $_SERVER[ 'HTTP_HOST' ], $current_blog );
	wp_cache_set( 'domain_mapping_site' . $_SERVER[ 'HTTP_HOST' ], $current_site );

	//Now, filter all the URLs, to ensure there's no cross-domain issues
	add_filter( 'pre_option_home', function() {
		global $current_blog;
		return 'http://' . $current_blog->domain;
	});
	add_filter( 'site_url', 'bdn_siteurl_remap' );
	add_filter( 'plugins_url', 'bdn_siteurl_remap' );
	add_filter( 'content_url', 'bdn_siteurl_remap' );

}	

function bdn_siteurl_remap( $url ) {
	global $current_blog, $original_domain;
	return str_replace( $original_domain, $current_blog->domain, $url );
}
