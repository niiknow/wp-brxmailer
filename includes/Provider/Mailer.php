<?php

namespace WPMailSMTP\Providers\BrickInc;

use WPMailSMTP\MailCatcherInterface;
use WPMailSMTP\Options as PluginOptions;
use WPMailSMTP\Providers\MailerAbstract;
use WPMailSMTP\WP;

/**
 * BrickInc API mailer.
 *
 */
class Mailer extends MailerAbstract {

    /**
     * Which response code from HTTP provider is considered to be successful?
     *
     * @since 1.8.0
     *
     * @var int
     */
    protected $email_sent_code = 202;

    /**
     * URL to make an API request to.
     *
     * @var string
     */
    protected $url = 'https://console.brickinc.net/api/v1/sendmail/send';

    /**
     * Mailer constructor.
     *
     * @param MailCatcherInterface $phpmailer The MailCatcher instance.
     */
    public function __construct( $phpmailer ) {

        // We want to prefill everything from MailCatcher class, which extends PHPMailer.
        parent::__construct( $phpmailer );

        $this->set_header( 'x-api-key', $this->options->get( $this->mailer, 'api_key' ) );
        $this->set_header( 'content-type', 'application/json' );
    }

    /**
     * Redefine the way email body is returned.
     * By default we are sending an array of data.
     * Pepipost requires a JSON, so we encode the body.
     *
     * @return string
     */
    public function get_body() {

        $body = parent::get_body();

        return wp_json_encode( $body );
    }

    /**
     * Redefine the way custom headers are processed for this mailer - they should be in body.
     *
     * @param array $headers Headers array.
     */
    public function set_headers( $headers ) {

        foreach ( $headers as $header ) {
            $name  = isset( $header[0] ) ? $header[0] : false;
            $value = isset( $header[1] ) ? $header[1] : false;

            $this->set_body_header( $name, $value );
        }

        // Add custom PHPMailer-specific header.
        $this->set_body_header( 'X-Mailer', 'WPMailSMTP/Mailer/' . $this->mailer . ' ' . WPMS_PLUGIN_VER );
        $this->set_body_header( 'Message-ID', $this->phpmailer->getLastMessageID() );
    }

    /**
     * This mailer supports email-related custom headers inside a body of the message.
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     */
    public function set_body_header( $name, $value ) {

        $name = sanitize_text_field( $name );

        if ( empty( $name ) ) {
            return;
        }

        $headers = isset( $this->body['headers'] ) ? (array) $this->body['headers'] : [];

        if ( $name !== 'Message-ID' ) {
            $value = WP::sanitize_value( $value );
        }

        // Prevent duplicates.
        $key = array_search( $name, array_column( $headers, 'name' ), true );

        if ( $key !== false ) {
            unset( $headers[ $key ] );
        }

        $headers[] = [
            'name'  => $name,
            'value' => $value,
        ];

        $this->body['headers'] = array_values( $headers );
    }

    /**
     * Set the FROM header of the email.
     *
     * @param string $email From mail.
     * @param string $name  From name.
     */
    public function set_from( $email, $name = '' ) {

        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return;
        }

        $from['from'] = $email;

        if ( ! empty( $name ) ) {
            $from['from_name'] = $name;
        }

        $this->set_body_param($from);
    }

    /**
     * Set the names/emails of people who will receive the email.
     *
     * @param array $recipients List of recipients: cc/bcc/to.
     */
    public function set_recipients( $recipients ) {


        if ( empty( $recipients ) ) {
            return;
        }

        $default = [ 'to', 'cc', 'bcc' ];

        foreach ( $recipients as $type => $emails ) {
            if (
                ! in_array( $type, $default, true ) ||
                empty( $emails ) ||
                ! is_array( $emails )
            ) {
                continue;
            }

            $data = [];

            foreach ( $emails as $email ) {
                $addr = isset( $email[0] ) ? $email[0] : false;

                if ( ! filter_var( $addr, FILTER_VALIDATE_EMAIL ) ) {
                    continue;
                }

                $data[] = $addr;
            }

            if ( ! empty( $data ) ) {
                if ($type === 'to') {
                    $this->set_body_param(
                        [
                            $type => implode( ',', $data ),
                        ]
                    );
                } else {
                    // API only support single email for cc and bcc
                    $this->set_body_param(
                        [
                            $type => $data[0]
                        ]
                    );
                }
            }
        }
    }

    /**
     * Set the email content.
     *
     * @param array|string $content Email content.
     */
    public function set_content( $content ) {

        if ( empty( $content ) ) {
            return;
        }

        $bodyContent = array();
        if ( is_array( $content ) ) {
            if ( ! empty( $content['text'] ) ) {
                $bodyContent[] = array(
                    'type' => 'text',
                    'value' => $content['text']
                );
            }

            if ( ! empty( $content['html'] ) ) {
                $bodyContent[] = array(
                    'type' => 'html',
                    'value' => $content['html']
                );
            }
        } else {

            $bodyContent[] = array(
                'type' => ( $this->phpmailer->ContentType === 'text/plain' ) ? 'text' : 'html',
                'value' => $content
            );
        }

        $this->set_body_param(
            [
                'content' => $bodyContent
            ]
        );
    }

    /**
     * Pepipost API accepts an array of files content in body, so we will include all files and send.
     * Doesn't handle exceeding the limits etc, as this will be reported by the API response.
     *
     * @param array $attachments The list of attachments data.
     */
    public function set_attachments( $attachments ) {

        if ( empty( $attachments ) ) {
            return;
        }

        $data = $this->prepare_attachments( $attachments );

        if ( ! empty( $data ) ) {
            $this->set_body_param(
                [
                    'attachments' => $data,
                ]
            );
        }
    }

    /**
     * Prepare the attachments data for Pepipost API.
     *
     * @param array $attachments Array of attachments.
     *
     * @return array
     */
    protected function prepare_attachments( $attachments ) {

        $data = [];

        foreach ( $attachments as $attachment ) {
            $file = false;

            /*
             * We are not using WP_Filesystem API as we can't reliably work with it.
             * It is not always available, same as credentials for FTP.
             */
            try {
                if ( is_file( $attachment[0] ) && is_readable( $attachment[0] ) ) {
                    $file = file_get_contents( $attachment[0] );
                }
            } catch ( \Exception $e ) {
                $file = false;
            }

            if ( $file === false ) {
                continue;
            }

            $data[] = [
                'content' => base64_encode( $file ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                'name'    => $attachment[2],
            ];
        }

        return $data;
    }

    /**
     * BrickInc API do not support reply to, it's actually using from email.
     *
     * @param array $reply_to Name/email for reply-to feature.
     */
    public function set_reply_to( $reply_to ) {}

    /**
     * Pepipost API doesn't support sender or return_path params.
     * So we do nothing.
     *
     * @param string $from_email The from email address.
     */
    public function set_return_path( $from_email ) {}

    /**
     * Get a BrickInc-specific response with a helpful error.
     *
     * @return string
     */
    public function get_response_error() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

        $body = (array) wp_remote_retrieve_body( $this->response );

        $error   = ! empty( $body['error'] ) ? $body['error'] : '';
        $info    = ! empty( $body['info'] ) ? $body['info'] : '';
        $message = '';

        if ( ! empty( $this->error_message ) ) {
            $message = $this->error_message;
        } elseif ( is_string( $error ) ) {
            $message = $error . ( ( ! empty( $info ) ) ? ' - ' . $info : '' );
        } elseif ( is_array( $error ) ) {
            $message = '';

            foreach ( $error as $item ) {
                $message .= sprintf(
                    '%1$s (%2$s - %3$s)',
                    ! empty( $item->description ) ? $item->description : esc_html__( 'General error', 'wp-mail-smtp' ),
                    ! empty( $item->message ) ? $item->message : esc_html__( 'Error', 'wp-mail-smtp' ),
                    ! empty( $item->field ) ? $item->field : ''
                ) . PHP_EOL;
            }
        }

        return $message;
    }

    /**
     * Get mailer debug information, that is helpful during support.
     *
     * @return string
     */
    public function get_debug_info() {

        $debug_text[] = '<strong>Api Key:</strong> ' . ( $this->is_mailer_complete() ? 'Yes' : 'No' );

        return implode( '<br>', $debug_text );
    }

    /**
     * Whether the mailer has all its settings correctly set up and saved.
     *
     * @return bool
     */
    public function is_mailer_complete() {

        $options = $this->options->get_group( $this->mailer );

        // API key is the only required option.
        if ( ! empty( $options['api_key'] ) ) {
            return true;
        }

        return false;
    }
}
