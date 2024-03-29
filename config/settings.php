<?php

// don't call the file directly
if (! defined('ABSPATH')) {
    exit;
}

// this allow for using wordpress server-side translation
return [
    'sections' => [
        'general'   => __('General', \Brxmailer\Main::PREFIX),
        'advanced'  => __('Advanced', \Brxmailer\Main::PREFIX),
        'debugging' => __('Debugging', \Brxmailer\Main::PREFIX),
    ],
    'options'  => [
        'enable_debug_messages'          => [
            'name'        => __('Enable Debug Messages', \Brxmailer\Main::PREFIX),
            'description' => __('When enabled the plugin will output debug messages in the JavaScript console.', \Brxmailer\Main::PREFIX),
            'section'     => 'debugging',
            'type'        => 'toggle',
            'default'     => false,
        ],
        'cleanup_db_on_plugin_uninstall' => [
            'name'        => __('Cleanup database upon plugin uninstall', \Brxmailer\Main::PREFIX),
            'description' => __('When enabled the plugin will remove any database data upon plugin uninstall.', \Brxmailer\Main::PREFIX),
            'section'     => 'advanced',
            'type'        => 'toggle',
            'default'     => false,
        ],
    ],
];
