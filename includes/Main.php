<?php
namespace Brxmailer;

/**
 * Main class
 *
 * @class Main The class that holds initialize this plugin
 */
final class Main
{
    /**
     * A unique plugin prefix/token to use throughout your plugin.
     * This is also your application domain.
     *
     * @var string
     */
    const PREFIX = 'brxmailer';

    /**
     * Holds various class instances.
     *
     * @var array
     * @access  private
     * @since   1.0.0
     */
    private $container = array();

    /**
     * The single instance of Main.
     *
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null; //phpcs:ignore

    /**
     * The version number.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $VERSION;

    /**
     * The plugin filename.
     *
     * @var string
     */
    public static $PLUGINFILE = '';

    /**
     * The base url, default '.'
     *
     * @var string
     */
    public static $BASEURL = '.';

    /**
     * The plugin dir, default to empty string
     * @var string
     */
    public static $PLUGINDIR = '';

    /**
     * Constructor for the Main class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @param string $filepath the plugin file path
     * @param string $version Plugin version.
     */
    private function __construct($filepath, $version = '1.0.0')
    {
        self::$PLUGINFILE = $filepath;
        self::$PLUGINDIR  = dirname($filepath);
        $this->VERSION    = $version;
    }

    /**
     * Get a singleton instance of this plugin.
     *
     * Usage: Main::get_instance()
     *
     * @param string $filepath the plugin file path
     * @param string $version Plugin version.
     *
     * @return Main the singleton instance
     */
    public static function get_instance($filepath, $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($filepath, $version);
        }

        return self::$_instance;
    }

    /**
     * Do stuff during plugin uninstall.
     *
     */
    public static function uninstall_plugin()
    {
        flush_rewrite_rules();

        $setting_key = self::PREFIX . '_settings';
        $settings    = get_option($setting_key, []);
        (new \Brxmailer\Migrations())->cleanUp(self::PREFIX, $settings);
    }

    /**
     * Activate and initialize the plugin.
     *
     */
    public function run()
    {
        // set base url from plugin file name
        self::$BASEURL = plugins_url('', self::$PLUGINFILE);

        register_activation_hook(self::$PLUGINFILE, array($this, 'activate_plugin'));
        register_deactivation_hook(self::$PLUGINFILE, array($this, 'deactivate_plugin'));
        register_uninstall_hook(self::$PLUGINFILE, array(__CLASS__, 'uninstall_plugin'));

        // delay loading of our plugin last, making sure that other plugins are loaded first
        add_action('plugins_loaded', array($this, 'plugins_loaded'), PHP_INT_MAX);

        // setup cli
        if (defined('WP_CLI') && \WP_CLI) {
            $this->container['cli'] = new \Brxmailer\CliLoader(self::PREFIX);
        }

        // this is to register an action link from the Plugin manager page to our settings page
        $plugin = plugin_basename(self::$PLUGINFILE);
        add_filter("plugin_action_links_$plugin", array($this, 'register_settings_link'));

        // Additional thing you can do: register post type, taxonomy, etc...
        return $this;
    }

    /**
     * Magic getter to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __get($prop)
    {
        if (array_key_exists($prop, $this->container)) {
            return $this->container[$prop];
        }

        return $this->{$prop};
    }

    /**
     * Magic isset to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __isset($prop)
    {
        return isset($this->{$prop}) || isset($this->container[$prop]);
    }

    /**
     * Register hooks after all plugins are loaded
     *
     * @return void
     */
    public function plugins_loaded()
    {
        add_action('init', array($this, 'init_hook_handler'));

        // load the Mailer and Options if plugin is defined
        if (function_exists('wp_mail_smtp')) {
            require_once self::$PLUGINDIR . '/Mailer.php';
            require_once self::$PLUGINDIR . '/Options.php';

            add_filter( 'wp_mail_smtp_options_get', array($this, 'my_options_get'), 10, 3 );
            add_filter( 'wp_mail_smtp_providers_loader_get_providers', array($this, 'my_get_providers'), 10, 3 );
        }
    }

    /**
     * Enable brickinc mailer by returning 'brickinc' for default mail
     *
     * @param  string $value
     * @param  string $group
     * @param  string $key
     * @return string
     */
    public function my_options_get($value, $group, $key) {
        if ($group === 'mail' && $key === 'mailer') {
            $value = 'brickinc';
        }

        return $value;
    }

    /**
     * Enable brickinc mailer by returning the namespace path
     *
     * @param  array $providers
     * @return array
     */
    public function my_get_providers($providers) {

        if (!isset($providers['brickinc'])) {
            $providers['brickinc'] = 'WPMailSMTP\Providers\BrickINC\\';
        }

        return $providers;
    }

    /**
     * Plugin activation function.
     *
     */
    public function activate_plugin()
    {
        (new \Brxmailer\Migrations())->run(self::PREFIX, $this->VERSION);

        // set the current version to activate plugin
        update_option(self::PREFIX . '_version', $this->VERSION);
    }

    /**
     * Do stuff during plugin deactivation.
     *
     */
    public function deactivate_plugin()
    {
        flush_rewrite_rules();

        // do stuff such as: shut off cron tasks, etc...

        // remove version number to deactivate plugin
        delete_option(self::PREFIX . '_version');
    }

    /**
     * Register settings link that display on the plugins listing page.
     *
     * @param  array $links
     * @return array
     */
    public function register_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=' . self::PREFIX . '#/settings">';
        $settings_link .= esc_html(__('Settings', self::PREFIX)) . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Handler for init_hook
     *
     * @return void
     */
    public function init_hook_handler()
    {
        // initialize assets
        $this->container['assets'] = new \Brxmailer\Assets(self::PREFIX);

        // initialize the various loader classes
        if ($this->is_request('admin')) {
            $ctx                      = new \Brxmailer\AdminLoader(self::PREFIX);
            $this->container['admin'] = $ctx;
        }

        if ($this->is_request('frontend')) {
            $this->container['frontend'] = new \Brxmailer\FrontendLoader(self::PREFIX);
        }

        if ($this->is_request('ajax')) {
            // $this->container['ajax'] =  new \Brxmailer\AjaxLoader(self::PREFIX);
        }

        // finally load api routes
        $this->container['api'] = new \Brxmailer\ApiRoutes(self::PREFIX);
    }

    /**
     * Initialize plugin for localization.
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup()
    {
        load_plugin_textdomain(self::PREFIX,
            false, dirname(plugin_basename(self::PLUGINFILE)) . '/languages/');
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();

            case 'ajax':
                return defined('DOING_AJAX');

            case 'rest':
                return defined('REST_REQUEST');

            case 'cron':
                return defined('DOING_CRON');

            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
        }
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of Main is forbidden')), esc_attr($this->VERSION));

    } // End __clone ()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of Main is forbidden')), esc_attr($this->VERSION));
    } // End __wakeup ()
}
