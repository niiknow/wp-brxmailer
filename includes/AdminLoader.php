<?php

namespace Brxmailer;

/**
 * Admin pages loader.
 */
class AdminLoader
{
    /**
     * The application domain.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Initialize this class.
     */
    public function __construct($prefix)
    {
        $this->prefix = $prefix;
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /**
     * Register our menu page.
     *
     * @return void
     */
    public function admin_menu()
    {
        global $submenu;

        $capability = 'manage_options';
        $slug = $this->prefix;

        $hook = add_menu_page(
            esc_html(__('BrickInc Mailer', $this->prefix)),
            esc_html(__('BrickInc Mailer', $this->prefix)),
            $capability,
            $slug,
            [$this, 'plugin_page'],
            'dashicons-text' // tip: https://developer.wordpress.org/resource/dashicons
        );

        if (current_user_can($capability)) {
            add_submenu_page(
                $slug,
                esc_html(__('Dashboard', $this->prefix)),
                esc_html(__('Dashboard', $this->prefix)),
                $capability,
                $slug,
                [$this, 'plugin_page']
            );
        }
    }

    /**
     * Load scripts and styles for the app.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style($this->prefix.'-bootstrap');
        wp_enqueue_style($this->prefix.'-admin');
        wp_enqueue_script($this->prefix.'-admin');
    }

    /**
     * Render our admin page.
     *
     * @return void
     */
    public function plugin_page()
    {
        $this->enqueue_scripts();

        $plugUrl = '';
        if (! function_exists('wp_mail_smtp')) {
            $action = 'install-plugin';
            $slug = 'wp-mail-smtp';
            $plugUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => $action,
                        'plugin' => $slug,
                    ],
                    admin_url('update.php')
                ),
                $action.'_'.$slug
            );
        }

        $settingController = new Api\SettingController();

        // output data for use on client-side
        // https://wordpress.stackexchange.com/questions/344537/authenticating-with-rest-api
        $appVars = apply_filters('brxmailer/admin_app_vars', [
            'rest'             => [
                'endpoints' => [
                    'settings' => esc_url_raw(rest_url($settingController->get_endpoint())),
                ],
                'nonce'     => wp_create_nonce('wp_rest'),
            ],
            'nonce'            => wp_create_nonce('wp_rest'),
            'settings'         => $settingController->get_settings_raw(),
            'settingStructure' => $settingController->get_settings_structure(true),
            'prefix'           => $this->prefix,
            'adminUrl'         => admin_url('/'),
            'pluginUrl'        => rtrim(\Brxmailer\Main::$BASEURL, '/'),
            'wp_mail_smtp_url' => $plugUrl,
        ]);

        wp_localize_script($this->prefix.'-admin', 'vue_wp_plugin_config_admin', $appVars);

        $content = '<div class="admin-app-wrapper"><div id="vue-admin-app"></div></div>';
        echo $content;
    }
}
