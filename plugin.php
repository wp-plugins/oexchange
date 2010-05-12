<?php
/*
Plugin Name: OExchange
Plugin URI: http://wordpress.org/extend/plugins/oexchange/
Description: Adds OExchange support to WordPress' "Press This" bookmarklet
Version: 0.13
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

// Pre-2.6 compatibility
if ( ! defined( 'WP_CONTENT_URL' ) )
    define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
    define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

add_action('parse_request', array('OExchangePlugin', 'parseRequest'));
add_filter('query_vars', array('OExchangePlugin', 'queryVars'));
add_action('host_meta_xrd', array('OExchangePlugin', 'hostMetaXrd'));
add_action('webfinger_xrd', array('OExchangePlugin', 'hostMetaXrd'));
add_action('init', array('OExchangePlugin', 'init'));

/**
 * OExchange class
 *
 * @author Matthias Pfefferle
 * @link http://www.oexchange.org/spec/ OExchange Spec
 */
class OExchangePlugin {
  /**
   * add 'oexchange' as a valid query var.
   *
   * @param array $vars
   * @return array
   */
  function queryVars($vars) {
    $vars[] = 'oexchange';

    return $vars;
  }

  /**
   * Runs after WordPress has finished loading but
   * before any headers are sent. Useful for intercepting $_GET or $_POST triggers.
   */
  function init() {
    $bookmarkletUrl = admin_url('press-this.php');

    $thisUrl = 'http'.(isset($_SERVER["HTTPS"]) ? $_SERVER["HTTPS"] == 'off' ? '' : 's' : '').
                    '://'.
                    $_SERVER['HTTP_HOST'].
                    $_SERVER['REQUEST_URI'];

    if (stristr($thisUrl, $bookmarkletUrl) === false) {
      return;
    }

    // oexchange to wordpress mapping
    if (isset( $_GET['url'] ))
      $_GET['u'] = $_GET['url'];

    if (isset( $_GET['title'] ))
      $_GET['t'] = $_GET['title'];

    if (isset( $_GET['description'] ))
      $_GET['s'] = $_GET['description'];

    if (isset( $_GET['ctype'] )) {
      if ($_GET['ctype'] == "image") {
        $_GET['i'] = $_GET['imageurl'];
      }
    }
  }

  /**
   * parse request and show xrd file
   */
  function parseRequest() {
    global $wp_query, $wp;

    if( array_key_exists('oexchange', $wp->query_vars) ) {
      if ($wp->query_vars['oexchange'] == 'xrd') {
        $xrd = OExchangePlugin::createXrd();
        header('Content-Type: application/xrd+xml; charset=' . get_option('blog_charset'), true);
        echo $xrd;
        exit;
      }
    }
  }

  /**
   * generates the xrd file
   *
   * @link http://www.oexchange.org/spec/#discovery-targetxrd
   * @return string
   */
  function createXrd() {
    $xrd  = "<?xml version='1.0' encoding='UTF-8'?>";
    $xrd .= '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">';
    $xrd .= '  <Subject>'.'</Subject>';
    $xrd .= '  <Property
        type="http://www.oexchange.org/spec/0.8/prop/vendor">'.get_option('blogname').'</Property>';
    $xrd .= '  <Property
        type="http://www.oexchange.org/spec/0.8/prop/title">'.get_option('blogdescription').'</Property>';
    $xrd .= '  <Property
        type="http://www.oexchange.org/spec/0.8/prop/name">"Press This" bookmarklet</Property>';
    $xrd .= '  <Property
        type="http://www.oexchange.org/spec/0.8/prop/prompt">Press This</Property>';

    $xrd .= '  <Link
        rel= "icon"
        href="'.get_option( 'siteurl' ).'/favicon.ico"
        type="image/vnd.microsoft.icon"
        />';

    $xrd .= '  <Link
        rel= "http://www.oexchange.org/spec/0.8/rel/offer"
        href="'.admin_url('press-this.php').'"
        type="text/html"
        />';
    $xrd .= '</XRD>';

    return $xrd;
  }

  /**
   * generates the host-meta/webfinger xrd-link
   *
   * @link http://www.oexchange.org/spec/#discovery-hostmeta
   */
  function hostMetaXrd() {
    echo '<Link
            rel="http://oexchange.org/spec/0.8/rel/resident-target"
            type="application/xrd+xml"
            href="'.get_option( 'siteurl' ).'/?oexchange=xrd" />'."\n";
  }
}
?>