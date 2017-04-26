<?php
/*
Plugin Name: Monitoring Connect
Description: This plugin is connector for monitor this site
Author: Eugen Bobrowski
Version: 1.0
Author URI: http://atf.li
*/

/**
 * Created by PhpStorm.
 * User: eugen
 * Date: 28.02.17
 * Time: 15:50
 */
class Monitoring_Connect
{

    protected static $instance;
    protected $connect;

    private function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activation'));
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));
        add_action('admin_notices', array($this, 'notice'));
        add_action('init', array($this, 'rule'));
        add_action('wp', array($this, 'check'));

        add_action('monitoring_connect_connect', array($this, 'action_connect'));
        add_action('monitoring_connect_site_info', array($this, 'action_site_info'));
        add_action('monitoring_connect_plugin_activate', array($this, 'action_plugin_activate'));
        add_action('monitoring_connect_plugin_deactivate', array($this, 'action_plugin_deactivate'));
    }

    private function get_option()
    {
        if ($this->connect === null) $this->connect = get_option('monitoring_connect');
    }

    private function save_option()
    {
        update_option('monitoring_connect', $this->connect);
    }

    public function rule()
    {
        $this->get_option();

        add_rewrite_rule('^' . $this->connect['path'] . '$', 'index.php?monitoring=1', 'top');
        add_rewrite_tag('%monitoring%', '');
    }

    public function check()
    {
        if (empty(get_query_var('monitoring'))) return;

        $this->get_option();
        if (empty($_POST['pass']) || empty($_POST['action']) || $_POST['pass'] != $this->connect['pass']) {
            $this->connect['wrongs']++;
            if ($this->connect['wrongs'] >= $this->connect['max_wrongs']) $this->activation();
            $this->save_option();

            global $wp_query;

            $wp_query->set_404();
        } else {

            do_action('monitoring_connect_' . $_POST['action']);

            echo json_encode(array('notice' => '[No action "' . $_POST['action'] . '"!]'));

            exit();
        }
    }

    public function action_connect()
    {
        $this->connect['connected'] = true;
        $this->save_option();
        wp_send_json($this->connect);
    }

    public function action_site_info()
    {
        ob_start();

        $report = array(
            'ABSPATH' => ABSPATH,
            'active_plugins' => get_option('active_plugins'),
        );

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $report['plugins'] = get_plugins();

        $report['echos'] = ob_get_clean();

        wp_send_json($report);
    }

    public function action_plugin_activate()
    {
        ob_start();

        if (empty($_POST['plugin'])) ;

        $report = array();

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $report['plugins'] = get_plugins();

        $plugin = plugin_basename(trim($_POST['plugin']));

        $error = activate_plugin($plugin);

        $report['active_plugins'] = get_option('active_plugins');

        $report['activation_success'] = !is_wp_error($error);
        $report['plugin'] = $plugin;

        $report['echos'] = ob_get_clean();

        wp_send_json($report);
    }

    public function action_plugin_deactivate()
    {
        ob_start();

        if (empty($_POST['plugin'])) ;

        $report = array(
            'active_plugins' => get_option('active_plugins'),
        );

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $report['plugins'] = get_plugins();

        $plugin = plugin_basename(trim($_POST['plugin']));

        deactivate_plugins(array($plugin));
        $report['active_plugins'] = get_option('active_plugins');

        $report['deactivation_success'] = !in_array($plugin, $report['active_plugins']);
        $report['plugin'] = $plugin;

        $report['echos'] = ob_get_clean();

        wp_send_json($report);
    }

    public function notice()
    {
        $this->get_option();

        if ($this->connect['connected']) return;

        $opt = array();
        $opt['path'] = site_url($this->connect['path']);
        $opt['pass'] = $this->connect['pass'];

        ?>
        <div class="notice updated is-dismissible">
            <p>
                To connect this plugin to monitor you know what to do.
            </p>

            <pre><code><?php echo esc_html(json_encode($opt, true)); ?></code></pre>


            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
        <?php
    }

    public function activation()
    {
        $this->connect = array(
            'connected' => false,
            'path' => wp_generate_password(12, false),
            'pass' => wp_generate_password(12, true, true),
            'wrongs' => 0,
            'max_wrongs' => 3
        );

        $this->save_option();

        $this->rule();

        flush_rewrite_rules();

    }

    public function deactivation()
    {

    }


    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

Monitoring_Connect::get_instance();