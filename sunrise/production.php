<?php
//THIS ONLY DEFINES MAPPINGS FOR THE ADMIN PAGE. FRONT-END MAPPING IS DONE IN MU-PLUGINS
//WHY? WHO KNOWS.
if ( !defined( 'SUNRISE_LOADED' ) )
	define( 'SUNRISE_LOADED', 1 );

require( 'bdn-blog-redirects.php' );

if ( defined( 'COOKIE_DOMAIN' ) ) {
	die( 'The constant "COOKIE_DOMAIN" is defined (probably in wp-config.php). Please remove or comment out that define() line.' );
}

//The list of top-level domains we're looking for
$tlds = array( '.com', '.org', '.net', '.me' );

//This is only for the admin side of things. The front-end domain mapping is in mu-plugins
if( !is_admin() )
	return false;

//If the URL doesn't contain bangordailynews.com, redirect to domain.bangordailynews.com
//So, if you go to portlandphoenix.me/wp-admin/, it will replace .me with .bangordailynews.com and redirect you to the correct admin page
if( strpos( $_SERVER[ 'HTTP_HOST' ], 'bangordailynews.com' ) === false ) {
	$scheme = ( force_ssl_admin() ) ? 'https' : 'http';
	//Why are we using header instead of wp_redirect?
	header( 'Location: ' . $scheme . '://' . str_replace( $tlds, '.bangordailynews.com', $_SERVER[ 'HTTP_HOST' ] ) . $_SERVER[ 'REQUEST_URI' ] );
	exit();
}

//Check if this is in the cache. If it is, skip this whole business
if( ( $current_blog === wp_cache_get( 'domain_mapping_blog' . $_SERVER[ 'HTTP_HOST' ] ) )
	&& ( $current_site === wp_cache_get( 'domain_mapping_site' . $_SERVER[ 'HTTP_HOST' ] ) )
	&& is_object( $current_blog )
	&& is_object( $current_site )
) {
	$original_domain = $current_blog->domain;
	$current_blog->domain = $_SERVER[ 'HTTP_HOST' ];
	$current_blog->path = '/';
	$blog_id = $current_blog->blog_id;
	$site_id = $current_blog->site_id;
	define( 'COOKIE_DOMAIN', $_SERVER[ 'HTTP_HOST' ] );
	define( 'DOMAIN_MAPPING', 1 );
	add_filter( 'site_url', 'bdn_siteurl_remap' );
	add_filter( 'plugins_url', 'bdn_siteurl_remap' );
	add_filter( 'content_url', 'bdn_siteurl_remap' );
	return true;
}

//Basically, it goes like this: If the site is mysite.com, the admin site is mysite.bangordailynews.com.
//But we need to get the actual site name in order to select its ID, etc. So query for all sorts of variations
//and to get the correct one.
$possible_domains = array(
	$_SERVER[ 'HTTP_HOST' ]
);

//Go through each of the TLDs and replace .bangordailynews.com with the tlds, to check for a match in the db
// Why are we doing this, you ask? 
foreach( $tlds as $tld ) {
	$possible_domains[] = str_replace( '.bangordailynews.com', $tld, $_SERVER[ 'HTTP_HOST' ] );
}


if( strpos( $_SERVER[ 'HTTP_HOST' ], 'bangordailynews.com' ) === false )
	$possible_domains[] = str_replace( $tlds, '.bangordailynews.com', $_SERVER[ 'HTTP_HOST' ] );

$possible_domains = array_unique( $possible_domains );

//Now our array of possible domains looks like
// array( 'example.com', 'example.net', 'example.org', 'example.me', 'example.bangordailynews.com' );

$implode = array();
foreach( $possible_domains as $key => $value ) {
	$implode[] = '%s';
	$possible_domains[ $key ] = mysql_real_escape_string( $value );
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
}

wp_cache_set( 'domain_mapping_blog' . $_SERVER[ 'HTTP_HOST' ], $current_blog );
wp_cache_set( 'domain_mapping_site' . $_SERVER[ 'HTTP_HOST' ], $current_site );

//Now, filter all the URLs, to ensure there's no cross-domain issues
add_filter( 'pre_option_home', function() {
	global $current_blog;
	return 'http://' . $current_blog->domain;
});
//Now, filter all the URLs, to ensure there's no cross-domain issues
add_filter( 'site_url', 'bdn_siteurl_remap' );
add_filter( 'plugins_url', 'bdn_siteurl_remap' );
add_filter( 'content_url', 'bdn_siteurl_remap' );
function bdn_siteurl_remap( $url ) {
	global $current_blog, $original_domain;
	return str_replace( $original_domain, $current_blog->domain, $url );
}
