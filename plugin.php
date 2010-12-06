<?php
/*
Plugin Name: OExchange
Plugin URI: http://wordpress.org/extend/plugins/oexchange/
Description: Adds OExchange support to WordPress' "Press This" bookmarklet
Version: 0.5
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
add_action('admin_menu', array('OExchangePlugin', 'addMenuItem'));

if (is_admin() && $_GET['page'] == 'oexchange') {
  require_once(ABSPATH . 'wp-admin/admin.php');
  require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
  wp_enqueue_style( 'plugin-install' );
  wp_enqueue_script( 'plugin-install' );
  add_thickbox();
}

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
        href="'.OExchangePlugin::getIconUrl(16).'"
        type="image/png"
        />';

    $xrd .= '  <Link
        rel= "icon32"
        href="'.OExchangePlugin::getIconUrl(32).'"
        type="image/png"
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
  
  /**
   * adds the yiid-items to the admin-menu
   */
  function addMenuItem() {
    add_options_page('OExchange', 'OExchange Settings', 10, 'oexchange', array('OExchangePlugin', 'showSettings'));
  }
  
  /**
   * returns different sized icons
   *
   * @param string $size
   * @return string
   */
  function getIconUrl($size) {
    $default = "http://www.oexchange.org/images/logo_".$size."x".$size.".png";

    $grav_url = "http://www.gravatar.com/avatar/" . 
         md5(strtolower(get_bloginfo("admin_email"))) . "?d=" . urlencode($default) . "&amp;s=" . $size;
    
    return $grav_url;
  }
  
    /**
   * displays the yiid settings page
   */
  function showSettings() {
?>
  <div class="wrap">
    <img src="<?php echo WP_PLUGIN_URL ?>/oexchange/logo_32x32.png" alt="OSstatus for WordPress" class="icon32" />
    
    <h2>OExchange</h2>
    
    <p>OExchange is an open protocol for sharing any URL with any service on the web. -- <a href="http://www.oexchange.org/">oexchange.org</a></p>
    
    
    <h3>Settings</h3>
    
    <table class="form-table">
      <tbody>
        <tr valign="top">
        <th scope="row">OExchange URL</th>
        <td class="defaultavatarpicker"><fieldset><legend class="screen-reader-text"><span>Default Avatar</span></legend>
          Your Blogs discovery-url: <a href="<?php echo get_option( 'siteurl' ).'/?oexchange=xrd'; ?>" target="_blank"><?php echo get_option( 'siteurl' ).'/?oexchange=xrd'; ?></a>
        </fieldset>
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">OExchange Icon</th>
        <td class="defaultavatarpicker"><fieldset><legend class="screen-reader-text"><span>Default Avatar</span></legend>
          This Plugin uses the Gravatar of the admin-email: <strong><?php bloginfo("admin_email"); ?></strong> as OExchange icons.
          Visit <a href="http://gravatar.com" target"_blank">gravatar.com</a> to customize yours.
          <br />
          <?php echo get_avatar(get_bloginfo("admin_email"), 32, "http://www.oexchange.org/images/logo_32x32.png"); ?> 32x32<br />
          <?php echo get_avatar(get_bloginfo("admin_email"), 16, "http://www.oexchange.org/images/logo_16x16.png"); ?> 16x16<br />
        </fieldset>
        </td>
        </tr>
      </tbody>
    </table>
    
    <h3>Plugin dependencies</h3>

    <p>OExchange works perfect with the following plugins:</p>
<?php  
  $plugins = array();
  $plugins[] = plugins_api('plugin_information', array('slug' => 'host-meta'));
  $plugins[] = plugins_api('plugin_information', array('slug' => 'well-known'));
  $plugins[] = plugins_api('plugin_information', array('slug' => 'webfinger'));
  
  display_plugins_table($plugins);
 ?>
  </div>    
<?php
  }
}
?>