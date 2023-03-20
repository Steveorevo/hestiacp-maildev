<?php
/**
 * Extend the HestiaCP Pluginable object with our MailDev object for
 * our system-wide MailDev service.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/steveorevo/hestiacp-maildev
 * 
 */

 if ( ! class_exists( 'MailDev') ) {
    class MailDev {
        /**
         * Constructor, listen for the render events
         */
        public function __construct() {
            global $hcpp;
            $hcpp->maildev = $this;
            $hcpp->add_action( 'render_page', [ $this, 'render_page' ] );
            $hcpp->add_action( 'priv_unsuspend_domain', [ $this, 'priv_unsuspend_domain' ] );
            $hcpp->add_action( 'hcpp_plugin_installed', [ $this, 'hcpp_plugin_installed' ] );
            $hcpp->add_action( 'new_web_domain_ready', [ $this, 'new_web_domain_ready' ] );
        }

        // Ensure private/smtp.json is present the domain
        public function create_smtp_json( $user, $domain ) {
            $file = "/home/$user/web/$domain/private/smtp.json";
            if ( file_exists( $file ) ) return;
            global $hcpp;
            $password = $hcpp->nodeapp->random_chars( 16 );
            $content = "{\n  \"username\": \"$domain\",\n  \"password\": \"$password\",\n  \"port\": 2525\n}";
            file_put_contents( $file, $content );
            shell_exec( "chown $user:maildev $file && chmod 640 $file" );
        }
        public function priv_unsuspend_domain( $args ) {
            $user = $args[0];
            $domain = $args[1];
            $this->create_smtp_json( $user, $domain );
            return $args;
        }
        public function new_web_domain_ready( $args ) {
            $user = $args[0];
            $domain = $args[1];
            $this->create_smtp_json( $user, $domain );
            return $args;
        }

        // Add MailDev icon next to our web domain list and domain edit pages.
        public function render_page( $args ) {
            if ( $args['page'] == 'list_web' ) {
                $args = $this->render_list_web( $args );
            }
            if ( $args['page'] == 'edit_web' ) {
                $args = $this->render_edit_web( $args );
            }
            return $args;
       }

       // Add MailDev icon to our web domain edit page.
       public function render_edit_web( $args ) {
            global $hcpp;
            $domain = $_GET['domain'];
            $content = $args['content'];

            // Create blue code icon button to appear before Quick Installer button
            $code = '<a href="https://' . $domain . '/maildev" target="_blank" class="ui-button cancel" ';
            $code .= 'dir="ltr"><i class="fas fa-envelope status-icon blue">';
            $code .= '</i> MailDev</a>';

            // Inject the button into the page's toolbar buttonstrip
            $quick = '"fas fa-magic status-icon blue';
            $before = $hcpp->getLeftMost( $content, $quick );
            $after = $quick . $hcpp->delLeftMost( $content, $quick );
            $after = '<a href' . $hcpp->getRightMost( $before, '<a href' ) . $after;
            $before = $hcpp->delRightMost( $before, '<a href' );
            $content = $before . $code . $after;
            $args['content'] = $content;
            return $args;
       }

       // Add MailDev icon to our web domain list page.
       public function render_list_web( $args ) {
            global $hcpp;
            $content = $args['content'];

            // Create blue code icon before pencil/edit icon
            $div = '<div class="actions-panel__col actions-panel__edit shortcut-enter" key-action="href">';
            $code = '<div class="actions-panel__col actions-panel__code" key-action="href">
            <a href="https://%domain%/maildev" rel="noopener" target="_blank" title="Open MailDev">
                <i class="fas fa-envelope status-icon blue status-icon dim"></i>
            </a></div>&nbsp;';
            $new = '';

            // Inject the code icon for each domain
            while( false !== strpos( $content, $div ) ) {
                $new .= $hcpp->getLeftMost( $content, $div );
                $domain = $hcpp->getRightMost( $new, 'sort-name="' );
                $domain = $hcpp->getLeftMost( $domain, '"' );
                $content = $hcpp->delLeftMost( $content, $div );
                $new .= str_replace( '%domain%', $domain, $code ) . $div . $hcpp->getLeftMost( $content, '</div>' ) . "</div>";
                $content = $hcpp->delLeftMost( $content, '</div>' );
            }
            $new .= $content;
            $args['content'] = $new . '<style>.l-unit-toolbar__col{min-width: 200px;}</style>';
            return $args;
       }

        // Allocate port on and start server on install
        public function hcpp_plugin_installed( $plugin_name ) {
            if ( $plugin_name != 'maildev' ) return $plugin_name;
            global $hcpp;
            $port = $hcpp->allocate_port( 'maildev_port' );
            return $plugin_name;
        }
    }
    new MailDev();
}