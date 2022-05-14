<?php

namespace WPMailSMTP\Providers\BrickInc;

use WPMailSMTP\Providers\OptionsAbstract;
use WPMailSMTP\Options as PluginOptions;

/**
 * Class Options.
 *
 */
class Options extends OptionsAbstract {

    /**
     * Mailer slug.
     *
     */
    const SLUG = 'brickinc';

    /**
     * Options constructor.
     *
     */
    public function __construct() {

        $description = '';
        $api_key = PluginOptions::init()->get( self::SLUG, 'api_key' );

        parent::__construct(
            [
                'logo_url'    => wp_mail_smtp()->assets_url . '/images/logo.svg',
                'slug'        => self::SLUG,
                'title'       => esc_html__( 'BrickInc', 'wp-mail-smtp' ),
                'description' => $description,
                'php'         => '5.3',
                'supports'    => [
                    'from_email'       => true,
                    'from_name'        => true,
                    'return_path'      => false,
                    'from_email_force' => true,
                    'from_name_force'  => true,
                ],
            ]
        );
    }

    /**
     * Output the mailer provider options.
     *
     * @since 1.8.0
     */
    public function display_options() {

        // Do not display options if PHP version is not correct.
        if (! $this->is_php_correct()) {
            $this->display_php_warning();

            return;
        }
        ?>

        <!-- API Key -->
        <div id="wp-mail-smtp-setting-row-<?php echo esc_attr( $this->get_slug() ); ?>-client_id"
            class="wp-mail-smtp-setting-row wp-mail-smtp-setting-row-text wp-mail-smtp-clear">
            <div class="wp-mail-smtp-setting-label">
                <label for="wp-mail-smtp-setting-<?php echo esc_attr( $this->get_slug() ); ?>-api_key"><?php esc_html_e( 'API Key', 'wp-mail-smtp' ); ?></label>
            </div>
            <div class="wp-mail-smtp-setting-field">
                <?php if ($this->options->is_const_defined( $this->get_slug(), 'api_key' )) : ?>
                    <input type="text" disabled value="****************************************"
                        id="wp-mail-smtp-setting-<?php echo esc_attr( $this->get_slug() ); ?>-api_key"
                    />
                    <?php $this->display_const_set_message( 'WPMS_BRICKINC_API_KEY' ); ?>
                <?php else : ?>
                    <input type="password" spellcheck="false"
                        name="wp-mail-smtp[<?php echo esc_attr( $this->get_slug() ); ?>][api_key]"
                        value="<?php echo esc_attr( $this->options->get( $this->get_slug(), 'api_key' ) ); ?>"
                        id="wp-mail-smtp-setting-<?php echo esc_attr( $this->get_slug() ); ?>-api_key"
                    />
                <?php endif; ?>

                <p class="desc">
                    Contact Brick, Inc. to get an API Key.
                </p>
            </div>
        </div>

        <?php
    }
}
