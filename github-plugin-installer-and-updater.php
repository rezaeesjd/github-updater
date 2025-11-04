<?php
/**
 * Plugin Name: Github Plugin Installer and Updater
 * Plugin URI: https://github.com/hiteshbarot07/github-updater
 * Description: Install or update any WordPress plugin directly from GitHub repositories, including this helper plugin itself.
 * Version: 2.0.0
 * Author: Hitesh (HWT)
 * Author URI: https://github.com/hiteshbarot07
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-plugin-installer-and-updater
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Github_Plugin_Installer_And_Updater_Addon' ) ) {
    /**
     * GitHub installer and updater integration for WordPress plugins.
     */
    class Github_Plugin_Installer_And_Updater_Addon {
        const OPTION_KEY = 'github_plugin_installer_and_updater_settings';
        const ADMIN_SLUG = 'github-plugin-installer-and-updater';
        const NOTICE_KEY          = 'github_plugin_installer_and_updater_notice';
        const VERSION             = '2.0.0';
        const SELF_UPDATE_ACTION  = 'github_plugin_installer_and_updater_self_update';
        const MANAGED_OPTION_NAME = 'managed_plugins';

        /**
         * Cached settings.
         *
         * @var array
         */
        private $settings = array();

        /**
         * Constructor.
         */
        public function __construct() {
            $this->settings = $this->get_settings();

            add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_post_github_plugin_installer_and_updater', array( $this, 'handle_update_request' ) );
            add_action( 'admin_post_' . self::SELF_UPDATE_ACTION, array( $this, 'handle_self_update_request' ) );
            add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_items' ), 120 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'maybe_inject_self_update' ) );
            add_filter( 'plugins_api', array( $this, 'maybe_provide_self_update_details' ), 10, 3 );
            add_filter( 'http_request_args', array( $this, 'maybe_authorize_github_download' ), 10, 2 );
            add_action( 'wp_clean_plugins_cache', array( $this, 'handle_plugins_cache_cleared' ) );
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_action_links' ) );
        }

        /**
         * Register the settings page under the Tools menu.
         */
        public function register_settings_page() {
            add_management_page(
                __( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                __( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                'manage_options',
                self::ADMIN_SLUG,
                array( $this, 'render_settings_page' )
            );
        }

        /**
         * Register settings for the updater.
         */
        public function register_settings() {
            register_setting(
                'github_plugin_installer_and_updater',
                self::OPTION_KEY,
                array( $this, 'sanitize_settings' )
            );

            add_settings_section(
                'github_plugin_installer_and_updater_repo',
                __( 'Repository Settings', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repo_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_field(
                'repository_url',
                __( 'Repository URL', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repository_url_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_field(
                'repository_branch',
                __( 'Branch or Tag', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repository_branch_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_field(
                'github_token',
                __( 'GitHub Token (optional)', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_github_token_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_section(
                'github_plugin_installer_and_updater_account',
                __( 'Repositories From Your GitHub Account', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_account_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_section(
                'github_plugin_installer_and_updater_managed_plugins',
                __( 'Managed Plugins', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_managed_plugins_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_field(
                self::MANAGED_OPTION_NAME,
                __( 'Plugins and Repositories', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_managed_plugins_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_managed_plugins'
            );

            add_settings_section(
                'github_plugin_installer_and_updater_self_update',
                __( 'Self Update Settings', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_field(
                'self_update_repository_url',
                __( 'Plugin Repository URL', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_repository_url_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_self_update'
            );

            add_settings_field(
                'self_update_repository_branch',
                __( 'Branch or Tag', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_repository_branch_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_self_update'
            );
        }

        /**
         * Sanitize settings input.
         *
         * @param array $input Raw input from the form.
         *
         * @return array
         */
        public function sanitize_settings( $input ) {
            $sanitized = $this->get_default_settings();

            if ( isset( $input['repository_url'] ) ) {
                $sanitized['repository_url'] = esc_url_raw( trim( $input['repository_url'] ) );
            }

            if ( isset( $input['repository_branch'] ) ) {
                $sanitized['repository_branch'] = sanitize_text_field( $input['repository_branch'] );
            }

            if ( isset( $input['github_token'] ) ) {
                $sanitized['github_token'] = sanitize_text_field( $input['github_token'] );
            }

            $sanitized[ self::MANAGED_OPTION_NAME ] = array();

            if ( isset( $input[ self::MANAGED_OPTION_NAME ] ) && is_array( $input[ self::MANAGED_OPTION_NAME ] ) ) {
                if ( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $installed_plugins = get_plugins();

                foreach ( $input[ self::MANAGED_OPTION_NAME ] as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }

                    $plugin_file = isset( $row['plugin_file'] ) ? sanitize_text_field( $row['plugin_file'] ) : '';

                    if ( empty( $plugin_file ) || ! isset( $installed_plugins[ $plugin_file ] ) ) {
                        continue;
                    }

                    $repository_url = isset( $row['repository_url'] ) ? esc_url_raw( trim( $row['repository_url'] ) ) : '';

                    if ( empty( $repository_url ) ) {
                        continue;
                    }

                    $branch = isset( $row['repository_branch'] ) ? sanitize_text_field( $row['repository_branch'] ) : 'main';
                    $token  = isset( $row['token'] ) ? sanitize_text_field( $row['token'] ) : '';

                    $sanitized[ self::MANAGED_OPTION_NAME ][] = array(
                        'plugin_file'       => $plugin_file,
                        'slug'              => $this->derive_plugin_slug( $plugin_file ),
                        'repository_url'    => $repository_url,
                        'repository_branch' => $branch,
                        'token'             => $token,
                    );
                }
            }

            if ( isset( $input['self_update_repository_url'] ) ) {
                $sanitized['self_update_repository_url'] = esc_url_raw( trim( $input['self_update_repository_url'] ) );
            }

            if ( isset( $input['self_update_repository_branch'] ) ) {
                $sanitized['self_update_repository_branch'] = sanitize_text_field( $input['self_update_repository_branch'] );
            }

            $this->maybe_clear_self_update_cache( $sanitized );

            $this->settings = $sanitized;

            return $sanitized;
        }

        /**
         * Render repository section description.
         */
        public function render_repo_section_description() {
            $token_link = sprintf(
                '<a target="_blank" rel="noopener noreferrer" href="%1$s">%2$s</a>',
                esc_url( 'https://github.com/settings/personal-access-tokens' ),
                esc_html__( 'GitHub Token', 'github-plugin-installer-and-updater' )
            );

            $description = sprintf(
                /* translators: %s: GitHub token URL. */
                __( 'You can get it from here: %s. Provide details about the GitHub repository that hosts the plugin. For public repositories you only need the URL and branch. For private repositories supply a personal access token with the <code>repo</code> scope.', 'github-plugin-installer-and-updater' ),
                $token_link
            );

            printf( '<p>%s</p>', wp_kses_post( $description ) );
        }

        /**
         * Render the repository URL field.
         */
        public function render_repository_url_field() {
            printf(
                '<input type="url" class="regular-text" name="%1$s[repository_url]" id="bokun_repository_url" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['repository_url'] ),
                esc_attr__( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the repository branch field.
         */
        public function render_repository_branch_field() {
            printf(
                '<input type="text" class="regular-text" name="%1$s[repository_branch]" id="bokun_repository_branch" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['repository_branch'] ),
                esc_attr__( 'main', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the GitHub token field.
         */
        public function render_github_token_field() {
            printf(
                '<input type="password" class="regular-text" name="%1$s[github_token]" id="bokun_github_token" value="%2$s" autocomplete="off" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['github_token'] )
            );

            echo '<p class="description">' . esc_html__( 'Token is optional for public repositories but required for private repositories. Token will be stored in plain text inside the WordPress options table; avoid reusing sensitive tokens.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update section description.
         */
        public function render_self_update_section_description() {
            echo '<p>' . esc_html__( 'Provide the GitHub repository where this plugin is hosted to enable automatic updates for the installer itself.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update repository URL field.
         */
        public function render_self_update_repository_url_field() {
            printf(
                '<input type="url" class="regular-text" name="%1$s[self_update_repository_url]" id="bokun_self_update_repository_url" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['self_update_repository_url'] ),
                esc_attr__( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' )
            );

            echo '<p class="description">' . esc_html__( 'The plugin will check this repository for new versions of itself.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update repository branch field.
         */
        public function render_self_update_repository_branch_field() {
            printf(
                '<input type="text" class="regular-text" name="%1$s[self_update_repository_branch]" id="bokun_self_update_repository_branch" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['self_update_repository_branch'] ),
                esc_attr__( 'main', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the account section description and repository list.
         */
        public function render_account_section_description() {
            echo '<p>' . esc_html__( 'When a GitHub personal access token is provided, repositories from that account can be listed here to quickly select one for updates.', 'github-plugin-installer-and-updater' ) . '</p>';

            $token = $this->settings['github_token'];

            if ( empty( $token ) ) {
                echo '<p>' . esc_html__( 'Provide a token above and save changes to view available repositories.', 'github-plugin-installer-and-updater' ) . '</p>';
                return;
            }

            $repositories = $this->get_cached_repositories( $token );

            if ( is_wp_error( $repositories ) ) {
                printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $repositories->get_error_message() ) );
                return;
            }

            if ( empty( $repositories ) ) {
                echo '<p>' . esc_html__( 'No repositories were found for the authenticated user.', 'github-plugin-installer-and-updater' ) . '</p>';
                return;
            }

            echo '<p>' . esc_html__( 'Click a repository to fill in the repository URL and default branch automatically.', 'github-plugin-installer-and-updater' ) . '</p>';
            echo '<ul class="bokun-github-repo-list">';

            foreach ( $repositories as $repository ) {
                printf(
                    '<li><button type="button" class="button bokun-select-repo" data-url="%1$s" data-branch="%2$s">%3$s</button></li>',
                    esc_attr( $repository['html_url'] ),
                    esc_attr( $repository['default_branch'] ),
                    esc_html( $repository['full_name'] )
                );
            }

            echo '</ul>';

            $refresh_url = add_query_arg(
                array(
                    'page'          => self::ADMIN_SLUG,
                    'refresh_repos' => 1,
                ),
                admin_url( 'tools.php' )
            );

            printf(
                '<p><a class="button" href="%1$s">%2$s</a></p>',
                esc_url( $refresh_url ),
                esc_html__( 'Refresh repository list', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the managed plugins section description.
         */
        public function render_managed_plugins_section_description() {
            echo '<p>' . esc_html__( 'Link any installed plugin to a GitHub repository so you can fetch updates directly from the repository zipball.', 'github-plugin-installer-and-updater' ) . '</p>';
            echo '<p>' . esc_html__( 'Provide plugin-specific tokens only when a repository differs from the main token above.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the managed plugins field table.
         */
        public function render_managed_plugins_field() {
            $installed_plugins = $this->get_installed_plugins();

            if ( empty( $installed_plugins ) ) {
                echo '<p>' . esc_html__( 'No plugins are currently installed on this site.', 'github-plugin-installer-and-updater' ) . '</p>';
                return;
            }

            $managed_plugins = $this->get_managed_plugins();

            if ( empty( $managed_plugins ) ) {
                $managed_plugins = array(
                    array(
                        'plugin_file'       => '',
                        'repository_url'    => '',
                        'repository_branch' => 'main',
                        'token'             => '',
                        'slug'              => '',
                    ),
                );
            }

            $repositories       = array();
            $repositories_error = '';
            $has_repositories   = false;

            if ( ! empty( $this->settings['github_token'] ) ) {
                $repositories = $this->get_cached_repositories( $this->settings['github_token'] );

                if ( is_wp_error( $repositories ) ) {
                    $repositories_error = $repositories->get_error_message();
                    $repositories       = array();
                } else {
                    $has_repositories = ! empty( $repositories );
                }
            }

            $option_base                 = self::OPTION_KEY . '[' . self::MANAGED_OPTION_NAME . ']';
            $plugin_options_template     = $this->render_plugin_options_markup( $installed_plugins, '' );
            $repository_options_template = $has_repositories ? $this->render_repository_options_markup( $repositories, '' ) : '';

            echo '<table class="widefat striped github-managed-plugins-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Plugin', 'github-plugin-installer-and-updater' ) . '</th>';
            echo '<th>' . esc_html__( 'Repository', 'github-plugin-installer-and-updater' ) . '</th>';
            echo '<th>' . esc_html__( 'Branch', 'github-plugin-installer-and-updater' ) . '</th>';
            echo '<th>' . esc_html__( 'Token (optional)', 'github-plugin-installer-and-updater' ) . '</th>';
            echo '<th class="github-managed-plugins-actions">' . esc_html__( 'Actions', 'github-plugin-installer-and-updater' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody class="github-managed-plugins-rows">';

            foreach ( $managed_plugins as $index => $managed_plugin ) {
                $plugin_file = isset( $managed_plugin['plugin_file'] ) ? $managed_plugin['plugin_file'] : '';
                $repo_url    = isset( $managed_plugin['repository_url'] ) ? $managed_plugin['repository_url'] : '';
                $branch      = isset( $managed_plugin['repository_branch'] ) ? $managed_plugin['repository_branch'] : 'main';
                $token       = isset( $managed_plugin['token'] ) ? $managed_plugin['token'] : '';

                $plugin_field_id = 'github-managed-plugin-' . $index;
                $repo_input_id   = 'github-managed-plugin-' . $index . '-repo';
                $repo_select_id  = 'github-managed-plugin-' . $index . '-repo-select';
                $branch_input_id = 'github-managed-plugin-' . $index . '-branch';
                $token_input_id  = 'github-managed-plugin-' . $index . '-token';

                echo '<tr class="github-managed-plugin-row">';
                echo '<td class="github-managed-plugin-column">';
                printf(
                    '<label class="screen-reader-text" for="%1$s">%2$s</label>',
                    esc_attr( $plugin_field_id ),
                    esc_html__( 'Plugin', 'github-plugin-installer-and-updater' )
                );
                printf(
                    '<select name="%1$s[%2$d][plugin_file]" id="%3$s" class="github-managed-plugin-select">%4$s</select>',
                    esc_attr( $option_base ),
                    absint( $index ),
                    esc_attr( $plugin_field_id ),
                    $this->render_plugin_options_markup( $installed_plugins, $plugin_file )
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                if ( $plugin_file ) {
                    printf( '<p class="description">%s</p>', esc_html( $plugin_file ) );
                }

                echo '</td>';

                echo '<td class="github-managed-repo-column">';

                if ( $has_repositories ) {
                    printf(
                        '<label class="screen-reader-text" for="%1$s">%2$s</label>',
                        esc_attr( $repo_select_id ),
                        esc_html__( 'Repository', 'github-plugin-installer-and-updater' )
                    );
                    printf(
                        '<select id="%1$s" class="github-managed-repo-select" data-url-target="%2$s" data-branch-target="%3$s">%4$s</select>',
                        esc_attr( $repo_select_id ),
                        esc_attr( $repo_input_id ),
                        esc_attr( $branch_input_id ),
                        $this->render_repository_options_markup( $repositories, $repo_url )
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                printf(
                    '<input type="url" class="regular-text" name="%1$s[%2$d][repository_url]" id="%3$s" value="%4$s" placeholder="%5$s" />',
                    esc_attr( $option_base ),
                    absint( $index ),
                    esc_attr( $repo_input_id ),
                    esc_attr( $repo_url ),
                    esc_attr__( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' )
                );
                echo '</td>';

                echo '<td class="github-managed-branch-column">';
                printf(
                    '<label class="screen-reader-text" for="%1$s">%2$s</label>',
                    esc_attr( $branch_input_id ),
                    esc_html__( 'Branch or tag', 'github-plugin-installer-and-updater' )
                );
                printf(
                    '<input type="text" class="regular-text" name="%1$s[%2$d][repository_branch]" id="%3$s" value="%4$s" placeholder="%5$s" />',
                    esc_attr( $option_base ),
                    absint( $index ),
                    esc_attr( $branch_input_id ),
                    esc_attr( $branch ),
                    esc_attr__( 'main', 'github-plugin-installer-and-updater' )
                );
                echo '</td>';

                echo '<td class="github-managed-token-column">';
                printf(
                    '<label class="screen-reader-text" for="%1$s">%2$s</label>',
                    esc_attr( $token_input_id ),
                    esc_html__( 'Plugin token', 'github-plugin-installer-and-updater' )
                );
                printf(
                    '<input type="password" class="regular-text" name="%1$s[%2$d][token]" id="%3$s" value="%4$s" autocomplete="off" placeholder="%5$s" />',
                    esc_attr( $option_base ),
                    absint( $index ),
                    esc_attr( $token_input_id ),
                    esc_attr( $token ),
                    esc_attr__( 'Optional per-plugin token', 'github-plugin-installer-and-updater' )
                );
                echo '</td>';

                echo '<td class="github-managed-plugins-actions">';
                echo '<button type="button" class="button-link delete github-remove-managed-plugin">' . esc_html__( 'Remove', 'github-plugin-installer-and-updater' ) . '</button>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            if ( $repositories_error ) {
                printf( '<p class="description">%s</p>', esc_html( $repositories_error ) );
            } elseif ( ! $has_repositories ) {
                echo '<p class="description">' . esc_html__( 'Provide a GitHub token above and save changes to browse your repositories from a dropdown.', 'github-plugin-installer-and-updater' ) . '</p>';
            }

            echo '<p><button type="button" class="button" id="github-add-managed-plugin">' . esc_html__( 'Add another plugin', 'github-plugin-installer-and-updater' ) . '</button></p>';

            ob_start();
            ?>
            <tr class="github-managed-plugin-row" data-template="true">
                <td class="github-managed-plugin-column">
                    <label class="screen-reader-text" for="github-managed-plugin-__index__"><?php esc_html_e( 'Plugin', 'github-plugin-installer-and-updater' ); ?></label>
                    <select name="<?php echo esc_attr( $option_base ); ?>[__index__][plugin_file]" id="github-managed-plugin-__index__" class="github-managed-plugin-select">
                        <?php echo $plugin_options_template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </td>
                <td class="github-managed-repo-column">
                    <?php if ( $repository_options_template ) : ?>
                        <select id="github-managed-plugin-__index__-repo-select" class="github-managed-repo-select" data-url-target="github-managed-plugin-__index__-repo" data-branch-target="github-managed-plugin-__index__-branch">
                            <?php echo $repository_options_template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    <?php endif; ?>
                    <input type="url" class="regular-text" name="<?php echo esc_attr( $option_base ); ?>[__index__][repository_url]" id="github-managed-plugin-__index__-repo" value="" placeholder="<?php esc_attr_e( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' ); ?>" />
                </td>
                <td class="github-managed-branch-column">
                    <input type="text" class="regular-text" name="<?php echo esc_attr( $option_base ); ?>[__index__][repository_branch]" id="github-managed-plugin-__index__-branch" value="main" placeholder="<?php esc_attr_e( 'main', 'github-plugin-installer-and-updater' ); ?>" />
                </td>
                <td class="github-managed-token-column">
                    <input type="password" class="regular-text" name="<?php echo esc_attr( $option_base ); ?>[__index__][token]" id="github-managed-plugin-__index__-token" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional per-plugin token', 'github-plugin-installer-and-updater' ); ?>" />
                </td>
                <td class="github-managed-plugins-actions"><button type="button" class="button-link delete github-remove-managed-plugin"><?php esc_html_e( 'Remove', 'github-plugin-installer-and-updater' ); ?></button></td>
            </tr>
            <?php
            $template = trim( ob_get_clean() );

            printf( '<script type="text/template" id="github-managed-plugin-row-template">%s</script>', $template ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Enqueue assets required for the settings screen.
         *
         * @param string $hook_suffix Current admin page hook suffix.
         */
        public function enqueue_admin_assets( $hook_suffix ) {
            if ( 'tools_page_' . self::ADMIN_SLUG !== $hook_suffix ) {
                return;
            }

            $handle = 'github-plugin-installer-and-updater-settings';

            wp_register_script(
                $handle,
                plugins_url( 'assets/js/github-plugin-installer-and-updater-settings.js', __FILE__ ),
                array(),
                self::VERSION,
                true
            );

            wp_enqueue_script( $handle );

            wp_register_style( $handle, false, array(), self::VERSION );
            wp_enqueue_style( $handle );
            wp_add_inline_style(
                $handle,
                '.bokun-github-repo-list {display:flex;flex-wrap:wrap;gap:8px;padding-left:0;list-style:none;}' .
                '.bokun-github-repo-list li {margin:0;}' .
                '.github-managed-plugins-table th,.github-managed-plugins-table td{vertical-align:top;}' .
                '.github-managed-plugin-column select,.github-managed-repo-column select{width:100%;max-width:100%;}' .
                '.github-managed-plugins-actions{text-align:right;white-space:nowrap;}' .
                '.github-managed-plugin-update-form select{min-width:260px;margin-right:8px;}'
            );
        }

        /**
         * Render settings page markup.
         */
        public function render_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $notice        = $this->consume_notice();
            $refresh_repos = filter_input( INPUT_GET, 'refresh_repos', FILTER_SANITIZE_NUMBER_INT );

            if ( $refresh_repos && ! empty( $this->settings['github_token'] ) ) {
                delete_transient( $this->get_repo_cache_key( $this->settings['github_token'] ) );
            }

            $this->settings = $this->get_settings( true );

            $managed_plugins       = $this->get_managed_plugins( true );
            $installed_plugins     = $this->get_installed_plugins();
            $managed_plugin_labels = array();

            foreach ( $managed_plugins as $managed_plugin ) {
                if ( empty( $managed_plugin['plugin_file'] ) ) {
                    continue;
                }

                $plugin_file = $managed_plugin['plugin_file'];
                $label       = isset( $installed_plugins[ $plugin_file ]['Name'] ) ? $installed_plugins[ $plugin_file ]['Name'] : $plugin_file;

                $managed_plugin_labels[ $plugin_file ] = $label;
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ); ?></h1>

                <?php if ( $notice ) : ?>
                    <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'github_plugin_installer_and_updater' );
                    do_settings_sections( self::ADMIN_SLUG );
                    submit_button( __( 'Save Settings', 'github-plugin-installer-and-updater' ) );
                    ?>
                </form>

                <hr />

                <h2><?php esc_html_e( 'Install or Update From GitHub', 'github-plugin-installer-and-updater' ); ?></h2>
                <p><?php esc_html_e( 'Select a managed plugin and fetch the latest code from its GitHub repository.', 'github-plugin-installer-and-updater' ); ?></p>

                <?php if ( ! empty( $managed_plugin_labels ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="github-managed-plugin-update-form">
                        <?php wp_nonce_field( 'github_plugin_installer_and_updater_action' ); ?>
                        <input type="hidden" name="action" value="github_plugin_installer_and_updater" />
                        <label for="github-managed-plugin-select" class="screen-reader-text"><?php esc_html_e( 'Managed plugin', 'github-plugin-installer-and-updater' ); ?></label>
                        <select name="managed_plugin" id="github-managed-plugin-select" class="regular-text">
                            <?php foreach ( $managed_plugin_labels as $plugin_file => $label ) : ?>
                                <option value="<?php echo esc_attr( $plugin_file ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php submit_button( __( 'Run GitHub Update', 'github-plugin-installer-and-updater' ), 'primary', 'submit', false ); ?>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e( 'Add at least one managed plugin above to enable manual GitHub updates.', 'github-plugin-installer-and-updater' ); ?></p>
                <?php endif; ?>

                <h2><?php esc_html_e( 'Update This Helper Plugin', 'github-plugin-installer-and-updater' ); ?></h2>
                <p><?php esc_html_e( 'Configure the self-update repository in the settings above to allow one-click updates for this plugin.', 'github-plugin-installer-and-updater' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="github-self-update-form">
                    <?php wp_nonce_field( self::SELF_UPDATE_ACTION ); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::SELF_UPDATE_ACTION ); ?>" />
                    <?php submit_button( __( 'Update Helper Plugin from GitHub', 'github-plugin-installer-and-updater' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>
            <?php
        }

        /**
         * Handle the GitHub update request.
         */
        public function handle_update_request() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-plugin-installer-and-updater' ) );
            }

            check_admin_referer( 'github_plugin_installer_and_updater_action' );

            $plugin_file = isset( $_POST['managed_plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['managed_plugin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            if ( empty( $plugin_file ) ) {
                $this->persist_notice( __( 'Select a plugin to update before running the GitHub installer.', 'github-plugin-installer-and-updater' ), 'error' );
                wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
                exit;
            }

            $managed_plugin = $this->find_managed_plugin( $plugin_file );

            if ( ! $managed_plugin ) {
                $this->persist_notice( __( 'The selected plugin is not configured for GitHub updates.', 'github-plugin-installer-and-updater' ), 'error' );
                wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
                exit;
            }

            $installed_plugins = $this->get_installed_plugins();
            $plugin_label      = isset( $installed_plugins[ $plugin_file ]['Name'] ) ? $installed_plugins[ $plugin_file ]['Name'] : $plugin_file;

            $global_token = isset( $this->settings['github_token'] ) ? $this->settings['github_token'] : '';
            $result       = $this->update_plugin_from_github( $managed_plugin, $global_token );

            if ( is_wp_error( $result ) ) {
                $this->persist_notice( $result->get_error_message(), 'error' );
            } else {
                $message = sprintf(
                    /* translators: %s: Plugin name. */
                    __( '%s was updated successfully from GitHub.', 'github-plugin-installer-and-updater' ),
                    $plugin_label
                );
                $this->persist_notice( $message, 'success' );
            }

            wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
            exit;
        }

        /**
         * Handle the self-update request for this helper plugin.
         */
        public function handle_self_update_request() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-plugin-installer-and-updater' ) );
            }

            check_admin_referer( self::SELF_UPDATE_ACTION );

            $settings = $this->get_settings();

            if ( empty( $settings['self_update_repository_url'] ) ) {
                $this->persist_notice( __( 'Configure the self-update repository before attempting to update this plugin.', 'github-plugin-installer-and-updater' ), 'error' );
                wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
                exit;
            }

            $plugin_file = plugin_basename( __FILE__ );
            $managed_plugin = array(
                'plugin_file'       => $plugin_file,
                'repository_url'    => $settings['self_update_repository_url'],
                'repository_branch' => ! empty( $settings['self_update_repository_branch'] ) ? $settings['self_update_repository_branch'] : 'main',
                'token'             => '',
                'slug'              => $this->derive_plugin_slug( $plugin_file ),
            );

            $global_token = isset( $settings['github_token'] ) ? $settings['github_token'] : '';
            $result       = $this->update_plugin_from_github( $managed_plugin, $global_token );

            if ( is_wp_error( $result ) ) {
                $this->persist_notice( $result->get_error_message(), 'error' );
            } else {
                if ( ! function_exists( 'get_plugin_data' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $plugin_data  = get_plugin_data( __FILE__, false, false );
                $plugin_label = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : __( 'GitHub Plugin Installer and Updater', 'github-plugin-installer-and-updater' );

                $message = sprintf(
                    /* translators: %s: Plugin name. */
                    __( '%s was updated successfully from GitHub.', 'github-plugin-installer-and-updater' ),
                    $plugin_label
                );

                $this->persist_notice( $message, 'success' );
                $this->maybe_clear_self_update_cache( $settings );
                delete_site_transient( 'update_plugins' );
            }

            wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
            exit;
        }

        /**
         * Run update for a managed plugin from GitHub.
         *
         * @param array  $managed_plugin Managed plugin settings.
         * @param string $global_token   Global GitHub token.
         *
         * @return string|WP_Error
         */
        private function update_plugin_from_github( $managed_plugin, $global_token ) {
            if ( empty( $managed_plugin['repository_url'] ) ) {
                return new WP_Error( 'missing_repo', __( 'Please provide a repository URL before running the update.', 'github-plugin-installer-and-updater' ) );
            }

            $parsed_repo = $this->parse_repository_url( $managed_plugin['repository_url'] );

            if ( is_wp_error( $parsed_repo ) ) {
                return $parsed_repo;
            }

            $branch = ! empty( $managed_plugin['repository_branch'] ) ? $managed_plugin['repository_branch'] : 'main';
            $token  = ! empty( $managed_plugin['token'] ) ? $managed_plugin['token'] : $global_token;

            $download_url = sprintf( 'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) );
            $zip_file     = $this->download_package( $download_url, $token );

            if ( is_wp_error( $zip_file ) ) {
                return $zip_file;
            }

            $destination_slug = isset( $managed_plugin['slug'] ) ? $managed_plugin['slug'] : $this->derive_plugin_slug( $managed_plugin['plugin_file'] );

            if ( empty( $destination_slug ) ) {
                return new WP_Error( 'invalid_slug', __( 'Unable to determine where the plugin should be installed.', 'github-plugin-installer-and-updater' ) );
            }

            $result = $this->install_package( $zip_file, $destination_slug, $managed_plugin['plugin_file'] );

            if ( file_exists( $zip_file ) ) {
                @unlink( $zip_file );
            }

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return __( 'GitHub package downloaded and installed successfully.', 'github-plugin-installer-and-updater' );
        }

        /**
         * Download GitHub package to a temporary file.
         *
         * @param string $download_url URL for zipball.
         * @param string $token        Optional GitHub token.
         *
         * @return string|WP_Error Path to downloaded file or WP_Error.
         */
        private function download_package( $download_url, $token = '' ) {
            if ( ! function_exists( 'wp_tempnam' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $tmp = wp_tempnam( $download_url );

            if ( ! $tmp ) {
                return new WP_Error( 'temp_file', __( 'Unable to create a temporary file for the download.', 'github-plugin-installer-and-updater' ) );
            }

            $headers = array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            );

            if ( ! empty( $token ) ) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $response = wp_remote_get(
                $download_url,
                array(
                    'timeout'  => 60,
                    'stream'   => true,
                    'filename' => $tmp,
                    'headers'  => $headers,
                )
            );

            if ( is_wp_error( $response ) ) {
                @unlink( $tmp );
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code >= 400 ) {
                $message = wp_remote_retrieve_body( $response );
                @unlink( $tmp );

                if ( empty( $message ) ) {
                    $message = sprintf( __( 'Unexpected response from GitHub (HTTP %d).', 'github-plugin-installer-and-updater' ), $code );
                }

                return new WP_Error( 'download_failed', $message );
            }

            return $tmp;
        }

        /**
         * Install package from downloaded zip file.
         *
         * @param string $zip_file Path to zip file.
         *
         * @return true|WP_Error
         */
        private function install_package( $zip_file, $destination_slug, $plugin_file ) {
            if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            global $wp_filesystem;

            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }

            if ( ! $wp_filesystem ) {
                return new WP_Error( 'filesystem', __( 'The WordPress filesystem could not be initialized.', 'github-plugin-installer-and-updater' ) );
            }

            $working_dir = trailingslashit( get_temp_dir() ) . 'bokun-github-' . wp_generate_password( 8, false );

            if ( ! wp_mkdir_p( $working_dir ) ) {
                return new WP_Error( 'temp_dir', __( 'Unable to create a temporary extraction folder.', 'github-plugin-installer-and-updater' ) );
            }

            $unzipped = unzip_file( $zip_file, $working_dir );

            if ( is_wp_error( $unzipped ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return $unzipped;
            }

            $directories = glob( trailingslashit( $working_dir ) . '*', GLOB_ONLYDIR );

            if ( empty( $directories ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'extraction_failed', __( 'The downloaded archive did not contain any directories.', 'github-plugin-installer-and-updater' ) );
            }

            $package_root = trailingslashit( $directories[0] );
            $is_single    = false === strpos( $plugin_file, '/' );

            if ( $is_single ) {
                $target_file = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;

                if ( $wp_filesystem->exists( $target_file ) ) {
                    $wp_filesystem->delete( $target_file );
                }

                if ( ! wp_mkdir_p( WP_PLUGIN_DIR ) ) {
                    $wp_filesystem->delete( $working_dir, true );
                    return new WP_Error( 'destination', __( 'Unable to access the plugins directory.', 'github-plugin-installer-and-updater' ) );
                }

                $source_file = $this->locate_plugin_file( $package_root, basename( $plugin_file ) );

                if ( ! $source_file ) {
                    $wp_filesystem->delete( $working_dir, true );
                    return new WP_Error( 'plugin_main_file', __( 'Unable to locate the plugin main file inside the downloaded package.', 'github-plugin-installer-and-updater' ) );
                }

                if ( ! @copy( $source_file, $target_file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    $wp_filesystem->delete( $working_dir, true );
                    return new WP_Error( 'copy_failed', __( 'Unable to copy the plugin file into the plugins directory.', 'github-plugin-installer-and-updater' ) );
                }

                $wp_filesystem->delete( $working_dir, true );

                return true;
            }

            $destination = trailingslashit( WP_PLUGIN_DIR ) . $destination_slug;

            if ( $wp_filesystem->is_dir( $destination ) ) {
                $wp_filesystem->delete( $destination, true );
            } elseif ( $wp_filesystem->exists( $destination ) ) {
                $wp_filesystem->delete( $destination );
            }

            if ( ! wp_mkdir_p( WP_PLUGIN_DIR ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'destination', __( 'Unable to access the plugins directory.', 'github-plugin-installer-and-updater' ) );
            }

            $source = rtrim( $package_root, '/' );
            $moved  = $wp_filesystem->move( $source, $destination, true );

            if ( ! $moved ) {
                $php_moved = @rename( $source, $destination );

                if ( ! $php_moved ) {
                    $copied = copy_dir( $source, $destination );

                    if ( is_wp_error( $copied ) ) {
                        $wp_filesystem->delete( $working_dir, true );
                        return $copied;
                    }

                    $moved = true;
                } else {
                    $moved = true;
                }
            }

            if ( ! $moved ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'move_failed', __( 'Unable to move the extracted plugin into the plugins directory.', 'github-plugin-installer-and-updater' ) );
            }

            $wp_filesystem->delete( $working_dir, true );

            return true;
        }

        /**
         * Locate a file inside the extracted package.
         *
         * @param string $package_root   Root directory of the extracted package.
         * @param string $expected_file  Filename to search for.
         *
         * @return string|false
         */
        private function locate_plugin_file( $package_root, $expected_file ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $package_root, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $expected_file === $file->getFilename() ) {
                    return $file->getPathname();
                }
            }

            return false;
        }

        /**
         * Parse repository URL into owner/repo.
         *
         * @param string $url GitHub repository URL.
         *
         * @return array|WP_Error
         */
        private function parse_repository_url( $url ) {
            $parts = wp_parse_url( $url );

            if ( empty( $parts['host'] ) || false === stripos( $parts['host'], 'github.com' ) ) {
                return new WP_Error( 'invalid_repo', __( 'Please provide a valid GitHub repository URL.', 'github-plugin-installer-and-updater' ) );
            }

            $path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

            if ( empty( $path ) ) {
                return new WP_Error( 'invalid_repo', __( 'The repository URL must include the owner and repository name.', 'github-plugin-installer-and-updater' ) );
            }

            $segments = explode( '/', $path );

            if ( count( $segments ) < 2 ) {
                return new WP_Error( 'invalid_repo', __( 'Unable to detect repository owner and name from the provided URL.', 'github-plugin-installer-and-updater' ) );
            }

            $owner = $segments[0];
            $repo  = preg_replace( '#\.git$#', '', $segments[1] );

            return array(
                'owner' => $owner,
                'repo'  => $repo,
            );
        }

        /**
         * Add the admin bar menu entry for the updater.
         *
         * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
         */
        public function register_admin_bar_items( $wp_admin_bar ) {
            if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
                return;
            }

            $wp_admin_bar->add_node(
                array(
                    'id'     => 'github-plugin-installer-and-updater',
                    'parent' => 'top-secondary',
                    'title'  => __( 'GitHub Updater', 'github-plugin-installer-and-updater' ),
                    'href'   => add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ),
                )
            );
        }

        /**
         * Retrieve settings with defaults.
         *
         * @param bool $force_refresh Whether to bypass the cached property.
         *
         * @return array
         */
        private function get_settings( $force_refresh = false ) {
            if ( $force_refresh || empty( $this->settings ) ) {
                $this->settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), $this->get_default_settings() );
            }

            return $this->settings;
        }

        /**
         * Default settings.
         *
         * @return array
         */
        private function get_default_settings() {
            return array(
                'repository_url'    => '',
                'repository_branch' => 'main',
                'github_token'      => '',
                self::MANAGED_OPTION_NAME         => array(),
                'self_update_repository_url'    => '',
                'self_update_repository_branch' => 'main',
            );
        }

        /**
         * Derive the plugin slug from a plugin file reference.
         *
         * @param string $plugin_file Plugin file path relative to the plugins directory.
         *
         * @return string
         */
        private function derive_plugin_slug( $plugin_file ) {
            $slug = dirname( $plugin_file );

            if ( '.' === $slug || empty( $slug ) ) {
                $slug = basename( $plugin_file );
                $slug = preg_replace( '/\.php$/i', '', $slug );
            }

            return $slug;
        }

        /**
         * Retrieve all installed plugins.
         *
         * @return array
         */
        private function get_installed_plugins() {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            return get_plugins();
        }

        /**
         * Return the configured managed plugins.
         *
         * @param bool $force_refresh Whether to bypass the cached settings property.
         *
         * @return array
         */
        private function get_managed_plugins( $force_refresh = false ) {
            $settings = $this->get_settings( $force_refresh );

            if ( isset( $settings[ self::MANAGED_OPTION_NAME ] ) && is_array( $settings[ self::MANAGED_OPTION_NAME ] ) ) {
                return $settings[ self::MANAGED_OPTION_NAME ];
            }

            return array();
        }

        /**
         * Locate a managed plugin entry by plugin file.
         *
         * @param string $plugin_file Plugin file path.
         *
         * @return array|null
         */
        private function find_managed_plugin( $plugin_file ) {
            $managed_plugins = $this->get_managed_plugins();

            foreach ( $managed_plugins as $managed_plugin ) {
                if ( isset( $managed_plugin['plugin_file'] ) && $plugin_file === $managed_plugin['plugin_file'] ) {
                    return $managed_plugin;
                }
            }

            return null;
        }

        /**
         * Build the markup for a plugin select control.
         *
         * @param array  $installed_plugins Installed plugins.
         * @param string $selected_plugin   Selected plugin file.
         *
         * @return string
         */
        private function render_plugin_options_markup( $installed_plugins, $selected_plugin ) {
            $options = '<option value="">' . esc_html__( 'Select a plugin…', 'github-plugin-installer-and-updater' ) . '</option>';

            foreach ( $installed_plugins as $file => $data ) {
                $label   = isset( $data['Name'] ) ? $data['Name'] : $file;
                $options .= sprintf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr( $file ),
                    selected( $file, $selected_plugin, false ),
                    esc_html( $label )
                );
            }

            return $options;
        }

        /**
         * Build the markup for the repository select control.
         *
         * @param array  $repositories  List of repositories.
         * @param string $selected_repo Selected repository URL.
         *
         * @return string
         */
        private function render_repository_options_markup( $repositories, $selected_repo ) {
            $options = '<option value="">' . esc_html__( 'Select from GitHub…', 'github-plugin-installer-and-updater' ) . '</option>';

            foreach ( $repositories as $repository ) {
                $options .= sprintf(
                    '<option value="%1$s" data-default-branch="%2$s" %4$s>%3$s</option>',
                    esc_attr( $repository['html_url'] ),
                    esc_attr( $repository['default_branch'] ),
                    esc_html( $repository['full_name'] ),
                    selected( $repository['html_url'], $selected_repo, false )
                );
            }

            return $options;
        }

        /**
         * Add action links to the plugin listing row.
         *
         * @param array $links Existing links.
         *
         * @return array
         */
        public function add_plugin_action_links( $links ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return $links;
            }

            $settings_link = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) ),
                esc_html__( 'Settings', 'github-plugin-installer-and-updater' )
            );

            array_unshift( $links, $settings_link );

            $update_url = wp_nonce_url(
                add_query_arg( array( 'action' => self::SELF_UPDATE_ACTION ), admin_url( 'admin-post.php' ) ),
                self::SELF_UPDATE_ACTION
            );

            $links[] = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( $update_url ),
                esc_html__( 'Update from GitHub', 'github-plugin-installer-and-updater' )
            );

            return $links;
        }

        /**
         * Persist an admin notice for later display.
         *
         * @param string $message Notice message.
         * @param string $type    Notice type.
         */
        private function persist_notice( $message, $type = 'success' ) {
            set_transient(
                $this->get_notice_key(),
                array(
                    'message' => $message,
                    'type'    => $type,
                ),
                MINUTE_IN_SECONDS
            );
        }

        /**
         * Consume persisted notice.
         *
         * @return array|null
         */
        private function consume_notice() {
            $key    = $this->get_notice_key();
            $notice = get_transient( $key );

            if ( $notice ) {
                delete_transient( $key );
            }

            return $notice;
        }

        /**
         * Build notice cache key.
         *
         * @return string
         */
        private function get_notice_key() {
            return self::NOTICE_KEY . '_' . get_current_user_id();
        }

        /**
         * Fetch repositories using GitHub API and cache the results.
         *
         * @param string $token GitHub token.
         *
         * @return array|WP_Error
         */
        private function get_cached_repositories( $token ) {
            $cache_key = $this->get_repo_cache_key( $token );
            $cached    = get_transient( $cache_key );
            $refresh   = filter_input( INPUT_GET, 'refresh_repos', FILTER_SANITIZE_NUMBER_INT );

            if ( false !== $cached && ! $refresh ) {
                return $cached;
            }

            $repositories = $this->fetch_repositories( $token );

            if ( is_wp_error( $repositories ) ) {
                return $repositories;
            }

            set_transient( $cache_key, $repositories, HOUR_IN_SECONDS );

            return $repositories;
        }

        /**
         * Build cache key for repository listings.
         *
         * @param string $token GitHub token.
         *
         * @return string
         */
        private function get_repo_cache_key( $token ) {
            return 'bokun_github_repos_' . md5( $token );
        }

        /**
         * Maybe add a self update entry to the plugin update transient.
         *
         * @param stdClass $transient Update transient.
         *
         * @return stdClass
         */
        public function maybe_inject_self_update( $transient ) {
            if ( ! is_object( $transient ) ) {
                $transient = new stdClass();
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = plugin_basename( __FILE__ );

            $settings = $this->get_settings();

            if ( empty( $settings['self_update_repository_url'] ) ) {
                return $transient;
            }

            $remote = $this->get_self_update_remote_info( $settings );

            if ( is_wp_error( $remote ) ) {
                return $transient;
            }

            $plugin_data     = get_plugin_data( __FILE__, false, false );
            $current_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0';

            if ( version_compare( $remote['version'], $current_version, '>' ) ) {
                $update = (object) array(
                    'slug'        => 'github-plugin-installer-and-updater',
                    'plugin'      => $plugin_file,
                    'new_version' => $remote['version'],
                    'package'     => $remote['package'],
                    'url'         => $remote['homepage'],
                );

                $transient->response[ $plugin_file ] = $update;
            } else {
                if ( isset( $transient->response[ $plugin_file ] ) ) {
                    unset( $transient->response[ $plugin_file ] );
                }

                if ( ! isset( $transient->no_update ) ) {
                    $transient->no_update = array();
                }

                $transient->no_update[ $plugin_file ] = (object) array(
                    'slug'        => 'github-plugin-installer-and-updater',
                    'plugin'      => $plugin_file,
                    'new_version' => $current_version,
                    'package'     => '',
                    'url'         => $remote['homepage'],
                );
            }

            if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
                $transient->checked = array();
            }

            $transient->checked[ $plugin_file ] = $current_version;

            return $transient;
        }

        /**
         * Provide plugin information for the self update dialog.
         *
         * @param false|object|array $result Existing result.
         * @param string             $action Requested action.
         * @param object             $args   Arguments.
         *
         * @return false|object|array
         */
        public function maybe_provide_self_update_details( $result, $action, $args ) {
            if ( 'plugin_information' !== $action || empty( $args->slug ) || 'github-plugin-installer-and-updater' !== $args->slug ) {
                return $result;
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $settings = $this->get_settings();

            if ( empty( $settings['self_update_repository_url'] ) ) {
                return $result;
            }

            $remote = $this->get_self_update_remote_info( $settings );

            if ( is_wp_error( $remote ) ) {
                return $result;
            }

            $plugin_data = get_plugin_data( __FILE__, false, false );

            $info = (object) array(
                'name'          => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : __( 'GitHub Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                'slug'          => 'github-plugin-installer-and-updater',
                'version'       => $remote['version'],
                'author'        => isset( $plugin_data['AuthorName'] ) ? $plugin_data['AuthorName'] : '',
                'homepage'      => $remote['homepage'],
                'download_link' => $remote['package'],
                'sections'      => array(
                    'description' => wp_kses_post( __( 'Installs and updates Bokun plugins directly from GitHub repositories.', 'github-plugin-installer-and-updater' ) ),
                ),
            );

            return $info;
        }

        /**
         * Inject authorization headers for GitHub downloads when required.
         *
         * @param array  $args Request arguments.
         * @param string $url  Request URL.
         *
         * @return array
         */
        public function maybe_authorize_github_download( $args, $url ) {
            $settings = $this->get_settings();

            if ( empty( $settings['github_token'] ) ) {
                return $args;
            }

            if ( false === stripos( $url, 'api.github.com/repos' ) ) {
                return $args;
            }

            if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
                $args['headers'] = array();
            }

            if ( empty( $args['headers']['Authorization'] ) ) {
                $args['headers']['Authorization'] = 'Bearer ' . $settings['github_token'];
            }

            if ( empty( $args['headers']['User-Agent'] ) ) {
                $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url();
            }

            return $args;
        }

        /**
         * Retrieve remote information required for self updates.
         *
         * @param array $settings Current plugin settings.
         *
         * @return array|WP_Error
         */
        private function get_self_update_remote_info( $settings ) {
            $parsed_repo = $this->parse_repository_url( $settings['self_update_repository_url'] );

            if ( is_wp_error( $parsed_repo ) ) {
                return $parsed_repo;
            }

            $branch          = $this->resolve_self_update_branch( $settings, $parsed_repo );
            $explicit_branch = ! empty( $settings['self_update_repository_branch'] );
            $token           = $settings['github_token'];

            $cache_key = $this->get_self_update_cache_key( $settings['self_update_repository_url'], $branch );

            if ( $this->should_bypass_self_update_cache() ) {
                delete_transient( $cache_key );
            } else {
                $cached = get_transient( $cache_key );

                if ( false !== $cached ) {
                    return $cached;
                }
            }

            $response = $this->request_self_update_file( $parsed_repo, $branch, $token );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( 404 === $code && ! $explicit_branch && 'master' !== $branch ) {
                $branch    = 'master';
                $cache_key = $this->get_self_update_cache_key( $settings['self_update_repository_url'], $branch );

                if ( $this->should_bypass_self_update_cache() ) {
                    delete_transient( $cache_key );
                } else {
                    $cached = get_transient( $cache_key );

                    if ( false !== $cached ) {
                        return $cached;
                    }
                }

                $response = $this->request_self_update_file( $parsed_repo, $branch, $token );

                if ( is_wp_error( $response ) ) {
                    return $response;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );

                if ( 200 === $code ) {
                    $this->maybe_cache_default_branch( $parsed_repo, $branch );
                }
            }

            if ( 200 !== $code ) {
                $message = wp_remote_retrieve_body( $response );

                if ( empty( $message ) ) {
                    $message = __( 'Unable to fetch plugin details from GitHub for self updates.', 'github-plugin-installer-and-updater' );
                }

                return new WP_Error( 'self_update_remote', $message );
            }

            $body = wp_remote_retrieve_body( $response );

            if ( ! $body ) {
                return new WP_Error( 'self_update_remote', __( 'The GitHub response did not contain plugin metadata.', 'github-plugin-installer-and-updater' ) );
            }

            $version = $this->extract_version_from_plugin_header( $body );

            if ( empty( $version ) ) {
                return new WP_Error( 'self_update_version', __( 'Unable to determine the remote plugin version for self updates.', 'github-plugin-installer-and-updater' ) );
            }

            $remote = array(
                'version'  => $version,
                'package'  => sprintf( 'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) ),
                'homepage' => sprintf( 'https://github.com/%1$s/%2$s', $parsed_repo['owner'], $parsed_repo['repo'] ),
            );

            set_transient( $cache_key, $remote, 5 * MINUTE_IN_SECONDS );

            return $remote;
        }

        /**
         * Flush the cached self-update lookup when WordPress clears plugin updates.
         *
         * @param bool $clear_update_cache Whether to clear update caches.
         */
        public function handle_plugins_cache_cleared( $clear_update_cache ) {
            if ( ! $clear_update_cache ) {
                return;
            }

            $settings = $this->get_settings();

            $this->maybe_clear_self_update_cache( $settings );
        }

        /**
         * Determine whether the remote self-update cache should be bypassed.
         *
         * @return bool
         */
        private function should_bypass_self_update_cache() {
            if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return true;
            }

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                return true;
            }

            return false;
        }

        /**
         * Derive the cache key used for self update lookups.
         *
         * @param string $repository_url Repository URL.
         * @param string $branch         Branch.
         *
         * @return string
         */
        private function get_self_update_cache_key( $repository_url, $branch ) {
            return 'github_plugin_installer_self_update_' . md5( $repository_url . '|' . $branch );
        }

        /**
         * Resolve the branch that should be used for self-update lookups.
         *
         * @param array $settings    Current settings.
         * @param array $parsed_repo Parsed repository information.
         *
         * @return string
         */
        private function resolve_self_update_branch( $settings, $parsed_repo ) {
            if ( ! empty( $settings['self_update_repository_branch'] ) ) {
                return $settings['self_update_repository_branch'];
            }

            $cache_key = $this->get_default_branch_cache_key( $parsed_repo['owner'], $parsed_repo['repo'] );

            if ( $this->should_bypass_self_update_cache() ) {
                delete_transient( $cache_key );
            } else {
                $cached = get_transient( $cache_key );

                if ( false !== $cached ) {
                    return $cached;
                }
            }

            $headers = array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            );

            if ( ! empty( $settings['github_token'] ) ) {
                $headers['Authorization'] = 'Bearer ' . $settings['github_token'];
            }

            $response = wp_remote_get(
                sprintf( 'https://api.github.com/repos/%1$s/%2$s', $parsed_repo['owner'], $parsed_repo['repo'] ),
                array(
                    'headers' => $headers,
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return 'main';
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( 200 !== $code ) {
                return 'main';
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['default_branch'] ) && $body['default_branch'] ) {
                $branch = sanitize_text_field( $body['default_branch'] );

                set_transient( $cache_key, $branch, DAY_IN_SECONDS );

                return $branch;
            }

            return 'main';
        }

        /**
         * Fetch the plugin file contents from GitHub for update comparisons.
         *
         * @param array  $parsed_repo Parsed repository information.
         * @param string $branch      Branch name.
         * @param string $token       GitHub token.
         *
         * @return array|WP_Error
         */
        private function request_self_update_file( $parsed_repo, $branch, $token ) {
            $file_url = sprintf( 'https://raw.githubusercontent.com/%1$s/%2$s/%3$s/github-plugin-installer-and-updater.php', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) );

            $headers = array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            );

            if ( ! empty( $token ) ) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            return wp_remote_get(
                $file_url,
                array(
                    'headers' => $headers,
                    'timeout' => 30,
                )
            );
        }

        /**
         * Cache the repository default branch after a successful fallback lookup.
         *
         * @param array  $parsed_repo Parsed repository information.
         * @param string $branch      Branch name.
         */
        private function maybe_cache_default_branch( $parsed_repo, $branch ) {
            $cache_key = $this->get_default_branch_cache_key( $parsed_repo['owner'], $parsed_repo['repo'] );

            set_transient( $cache_key, $branch, DAY_IN_SECONDS );
        }

        /**
         * Derive the cache key used to store the repository default branch.
         *
         * @param string $owner Repository owner.
         * @param string $repo  Repository name.
         *
         * @return string
         */
        private function get_default_branch_cache_key( $owner, $repo ) {
            return 'github_plugin_installer_default_branch_' . md5( $owner . '/' . $repo );
        }

        /**
         * Clear the cached self update information when settings change.
         *
         * @param array $settings Current settings.
         */
        private function maybe_clear_self_update_cache( $settings ) {
            if ( empty( $settings['self_update_repository_url'] ) ) {
                return;
            }

            $branch         = isset( $settings['self_update_repository_branch'] ) ? $settings['self_update_repository_branch'] : '';
            $repository_url = $settings['self_update_repository_url'];

            delete_transient( $this->get_self_update_cache_key( $repository_url, $branch ) );

            if ( empty( $branch ) ) {
                delete_transient( $this->get_self_update_cache_key( $repository_url, 'main' ) );
                delete_transient( $this->get_self_update_cache_key( $repository_url, 'master' ) );
            }

            $parsed_repo = $this->parse_repository_url( $repository_url );

            if ( is_wp_error( $parsed_repo ) ) {
                return;
            }

            delete_transient( $this->get_default_branch_cache_key( $parsed_repo['owner'], $parsed_repo['repo'] ) );
        }

        /**
         * Extract the version string from a plugin file header.
         *
         * @param string $plugin_contents Plugin file contents.
         *
         * @return string
         */
        private function extract_version_from_plugin_header( $plugin_contents ) {
            if ( ! preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_contents, $matches ) ) {
                return '';
            }

            return trim( $matches[1] );
        }

        /**
         * Fetch repositories from GitHub API using provided token.
         *
         * @param string $token GitHub token.
         *
         * @return array|WP_Error
         */
        private function fetch_repositories( $token ) {
            $response = wp_remote_get(
                'https://api.github.com/user/repos?per_page=100',
                array(
                    'headers' => array(
                        'Accept'        => 'application/vnd.github+json',
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                    ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( 200 !== $code ) {
                $message = wp_remote_retrieve_body( $response );

                if ( empty( $message ) ) {
                    $message = sprintf( __( 'Unable to fetch repositories. GitHub returned HTTP %d.', 'github-plugin-installer-and-updater' ), $code );
                }

                return new WP_Error( 'github_repos', $message );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $body ) ) {
                return new WP_Error( 'github_repos', __( 'Invalid response received from GitHub.', 'github-plugin-installer-and-updater' ) );
            }

            $repositories = array();

            foreach ( $body as $repo ) {
                if ( empty( $repo['full_name'] ) || empty( $repo['html_url'] ) ) {
                    continue;
                }

                $repositories[] = array(
                    'full_name'      => $repo['full_name'],
                    'html_url'       => $repo['html_url'],
                    'default_branch' => isset( $repo['default_branch'] ) ? $repo['default_branch'] : 'main',
                );
            }

            return $repositories;
        }

    }
}

new Github_Plugin_Installer_And_Updater_Addon();
