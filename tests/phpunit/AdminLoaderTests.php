<?php

namespace Tests;

defined('ABSPATH') or die();

class AdminLoaderTests extends PluginTestCase
{
    public function test_construct()
    {
        new \Brxmailer\AdminLoader('test');

        $this->assertTrue(has_action('admin_menu', '\Brxmailer\AdminLoader->admin_menu()') > 0);
    }
}
