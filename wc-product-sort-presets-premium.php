<?php
/**
 * Plugin Name: WC Product Sort Presets Premium
 * Description: Save WooCommerce sorting presets with category/tag scope, custom meta sorting, and automatic shop sorting.
 * Version: 1.0.0
 * Author: GitHub Copilot
 * Text Domain: wc-product-sort-presets-premium
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WC_Product_Sort_Presets_Premium {
    const OPTION_PRESETS = 'wcpsp_presets';
    const OPTION_DEFAULT = 'wcpsp_default_preset';

    public static function init() {
        $instance = new self();

        add_action( 'admin_menu', array( $instance, 'register_admin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_frontend_assets' ) );

        add_action( 'wp_ajax_wcpsp_save_preset', array( $instance, 'ajax_save_preset' ) );
        add_action( 'wp_ajax_wcpsp_get_preset', array( $instance, 'ajax_get_preset' ) );
        add_action( 'wp_ajax_wcpsp_delete_preset', array( $instance, 'ajax_delete_preset' ) );
        add_action( 'wp_ajax_wcpsp_set_default_preset', array( $instance, 'ajax_set_default_preset' ) );

        add_filter( 'woocommerce_default_catalog_orderby', array( $instance, 'default_catalog_orderby' ), 100 );
        add_filter( 'woocommerce_get_catalog_ordering_args', array( $instance, 'catalog_ordering_args' ), 100, 2 );

        add_action( 'woocommerce_before_shop_loop', array( $instance, 'render_frontend_selector' ), 15 );
        add_shortcode( 'wcpsp_selector', array( $instance, 'render_selector_shortcode' ) );
    }

    public function register_admin_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Sort Presets', 'wc-product-sort-presets-premium' ),
            __( 'Product Sort Presets', 'wc-product-sort-presets-premium' ),
            'manage_woocommerce',
            'wc-product-sort-presets-premium',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_wc-product-sort-presets-premium' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'wcpsp-admin-js',
            plugins_url( 'assets/admin.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'wcpsp-admin-css',
            plugins_url( 'assets/admin.css', __FILE__ ),
            array(),
            '1.0.0'
        );

        wp_localize_script(
            'wcpsp-admin-js',
            'wcpsp',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wcpsp_nonce' ),
            )
        );
    }

    public function enqueue_frontend_assets() {
        if ( ! is_shop() && ! is_product_taxonomy() ) {
            return;
        }

        wp_enqueue_script(
            'wcpsp-front-js',
            plugins_url( 'assets/frontend.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'wcpsp-front-css',
            plugins_url( 'assets/frontend.css', __FILE__ ),
            array(),
            '1.0.0'
        );

        wp_localize_script(
            'wcpsp-front-js',
            'wcpsp_frontend',
            array(
                'presets' => $this->get_presets(),
                'current_url' => remove_query_arg( array( 'wcpsp_preset', 'orderby' ), wc_get_current_page_url() ),
            )
        );
    }

    public function render_admin_page() {
        $presets = $this->get_presets();
        $default_id = get_option( self::OPTION_DEFAULT, '' );
        $categories = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            )
        );
        $tags = get_terms(
            array(
                'taxonomy'   => 'product_tag',
                'hide_empty' => false,
            )
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Product Sort Presets', 'wc-product-sort-presets-premium' ); ?></h1>

            <div class="wcpsp-admin-panel">
                <form id="wcpsp-form">
                    <input type="hidden" name="preset_id" id="wcpsp_preset_id" value="" />

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wcpsp_preset_name"><?php esc_html_e( 'Preset name', 'wc-product-sort-presets-premium' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="wcpsp_preset_name" name="preset_name" class="regular-text" required />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wcpsp_sort_type"><?php esc_html_e( 'Sort type', 'wc-product-sort-presets-premium' ); ?></label>
                            </th>
                            <td>
                                <select id="wcpsp_sort_type" name="sort_type">
                                    <?php foreach ( $this->get_sort_options() as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wcpsp_order"><?php esc_html_e( 'Direction', 'wc-product-sort-presets-premium' ); ?></label>
                            </th>
                            <td>
                                <select id="wcpsp_order" name="order">
                                    <option value="ASC"><?php esc_html_e( 'Ascending', 'wc-product-sort-presets-premium' ); ?></option>
                                    <option value="DESC"><?php esc_html_e( 'Descending', 'wc-product-sort-presets-premium' ); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr id="wcpsp_custom_meta_row" style="display:none;">
                            <th scope="row">
                                <label for="wcpsp_meta_key"><?php esc_html_e( 'Custom meta key', 'wc-product-sort-presets-premium' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="wcpsp_meta_key" name="meta_key" class="regular-text" placeholder="_brand" />
                                <p class="description">
                                    <?php esc_html_e( 'Examples: _brand, _sku, _stock_status, total_sales, _price', 'wc-product-sort-presets-premium' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr id="wcpsp_meta_type_row" style="display:none;">
                            <th scope="row">
                                <label for="wcpsp_meta_type"><?php esc_html_e( 'Meta type', 'wc-product-sort-presets-premium' ); ?></label>
                            </th>
                            <td>
                                <select id="wcpsp_meta_type" name="meta_type">
                                    <option value="NUMERIC"><?php esc_html_e( 'Numeric', 'wc-product-sort-presets-premium' ); ?></option>
                                    <option value="CHAR"><?php esc_html_e( 'String', 'wc-product-sort-presets-premium' ); ?></option>
                                    <option value="DATE"><?php esc_html_e( 'Date', 'wc-product-sort-presets-premium' ); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Apply to categories', 'wc-product-sort-presets-premium' ); ?></th>
                            <td>
                                <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
                                    <div class="wcpsp-checkbox-list">
                                        <?php foreach ( $categories as $category ) : ?>
                                            <label>
                                                <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr( $category->term_id ); ?>" />
                                                <?php echo esc_html( $category->name ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p><?php esc_html_e( 'No product categories found.', 'wc-product-sort-presets-premium' ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Apply to tags', 'wc-product-sort-presets-premium' ); ?></th>
                            <td>
                                <?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
                                    <div class="wcpsp-checkbox-list">
                                        <?php foreach ( $tags as $tag ) : ?>
                                            <label>
                                                <input type="checkbox" name="tag_ids[]" value="<?php echo esc_attr( $tag->term_id ); ?>" />
                                                <?php echo esc_html( $tag->name ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p><?php esc_html_e( 'No product tags found.', 'wc-product-sort-presets-premium' ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Set as default', 'wc-product-sort-presets-premium' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="set_default" value="1" />
                                    <?php esc_html_e( 'Use this as the global default preset', 'wc-product-sort-presets-premium' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary" id="wcpsp_submit">
                            <?php esc_html_e( 'Save preset', 'wc-product-sort-presets-premium' ); ?>
                        </button>
                        <button type="button" class="button" id="wcpsp_cancel_edit" style="display:none;">
                            <?php esc_html_e( 'Cancel edit', 'wc-product-sort-presets-premium' ); ?>
                        </button>
                    </p>
                </form>

                <hr />

                <h2><?php esc_html_e( 'Saved presets', 'wc-product-sort-presets-premium' ); ?></h2>
                <div id="wcpsp_presets_list">
                    <?php echo $this->render_presets_markup( $presets, $default_id ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_presets_markup( $presets, $default_id ) {
        if ( empty( $presets ) ) {
            return '<p>' . esc_html__( 'No presets yet.', 'wc-product-sort-presets-premium' ) . '</p>';
        }

        $sort_options = $this->get_sort_options();

        ob_start();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Preset', 'wc-product-sort-presets-premium' ); ?></th>
                    <th><?php esc_html_e( 'Sort type', 'wc-product-sort-presets-premium' ); ?></th>
                    <th><?php esc_html_e( 'Direction', 'wc-product-sort-presets-premium' ); ?></th>
                    <th><?php esc_html_e( 'Scope', 'wc-product-sort-presets-premium' ); ?></th>
                    <th><?php esc_html_e( 'Default', 'wc-product-sort-presets-premium' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wc-product-sort-presets-premium' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $presets as $preset ) : ?>
                    <?php
                    $preset_id = isset( $preset['id'] ) ? $preset['id'] : '';
                    $sort_type = isset( $preset['sort_type'] ) ? $preset['sort_type'] : 'menu_order';
                    $order = isset( $preset['order'] ) ? strtoupper( $preset['order'] ) : 'ASC';
                    $scope = $this->preset_scope_label( $preset );
                    $is_default = ( $default_id === $preset_id );
                    ?>
                    <tr data-preset-id="<?php echo esc_attr( $preset_id ); ?>">
                        <td><?php echo esc_html( $preset['name'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $sort_options[ $sort_type ] ?? ucfirst( str_replace( '-', ' ', $sort_type ) ) ); ?></td>
                        <td><?php echo esc_html( $order ); ?></td>
                        <td><?php echo esc_html( $scope ); ?></td>
                        <td><?php echo $is_default ? esc_html__( 'Yes', 'wc-product-sort-presets-premium' ) : esc_html__( 'No', 'wc-product-sort-presets-premium' ); ?></td>
                        <td>
                            <button type="button" class="button button-small wcpsp-edit" data-preset-id="<?php echo esc_attr( $preset_id ); ?>">
                                <?php esc_html_e( 'Edit', 'wc-product-sort-presets-premium' ); ?>
                            </button>

                            <button type="button" class="button button-small wcpsp-default" data-preset-id="<?php echo esc_attr( $preset_id ); ?>">
                                <?php esc_html_e( 'Default', 'wc-product-sort-presets-premium' ); ?>
                            </button>

                            <button type="button" class="button button-small button-link-delete wcpsp-delete" data-preset-id="<?php echo esc_attr( $preset_id ); ?>">
                                <?php esc_html_e( 'Delete', 'wc-product-sort-presets-premium' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private function preset_scope_label( $preset ) {
        $category_count = ! empty( $preset['category_ids'] ) ? count( $preset['category_ids'] ) : 0;
        $tag_count = ! empty( $preset['tag_ids'] ) ? count( $preset['tag_ids'] ) : 0;

        if ( $category_count > 0 || $tag_count > 0 ) {
            $parts = array();

            if ( $category_count > 0 ) {
                $parts[] = sprintf( _n( '%s category', '%s categories', $category_count, 'wc-product-sort-presets-premium' ), $category_count );
            }

            if ( $tag_count > 0 ) {
                $parts[] = sprintf( _n( '%s tag', '%s tags', $tag_count, 'wc-product-sort-presets-premium' ), $tag_count );
            }

            return implode( ', ', $parts );
        }

        return __( 'Global', 'wc-product-sort-presets-premium' );
    }

    private function get_sort_options() {
        return array(
            'menu_order'  => __( 'Default ordering', 'wc-product-sort-presets-premium' ),
            'popularity'  => __( 'Popularity', 'wc-product-sort-presets-premium' ),
            'rating'      => __( 'Average rating', 'wc-product-sort-presets-premium' ),
            'price'       => __( 'Price: low to high', 'wc-product-sort-presets-premium' ),
            'price-desc'  => __( 'Price: high to low', 'wc-product-sort-presets-premium' ),
            'date'        => __( 'Newest first', 'wc-product-sort-presets-premium' ),
            'title'       => __( 'Title', 'wc-product-sort-presets-premium' ),
            'rand'        => __( 'Random', 'wc-product-sort-presets-premium' ),
            'stock_status'=> __( 'Stock status', 'wc-product-sort-presets-premium' ),
            'custom_meta' => __( 'Custom meta field', 'wc-product-sort-presets-premium' ),
        );
    }

    private function get_presets() {
        $presets = get_option( self::OPTION_PRESETS, array() );
        return is_array( $presets ) ? $presets : array();
    }

    private function get_default_preset() {
        $default_id = get_option( self::OPTION_DEFAULT, '' );
        if ( empty( $default_id ) ) {
            return false;
        }

        $presets = $this->get_presets();
        return isset( $presets[ $default_id ] ) ? $presets[ $default_id ] : false;
    }

    public function render_selector_shortcode( $atts = array() ) {
        $presets = $this->get_presets();
        if ( empty( $presets ) ) {
            return '';
        }

        $current = $this->get_requested_preset_id();

        ob_start();
        ?>
        <div class="wcpsp-selector-wrap">
            <label for="wcpsp-selector"><?php esc_html_e( 'Sort by preset', 'wc-product-sort-presets-premium' ); ?></label>
            <select id="wcpsp-selector" class="wcpsp-selector">
                <option value=""><?php esc_html_e( 'Select preset', 'wc-product-sort-presets-premium' ); ?></option>
                <?php foreach ( $presets as $preset ) : ?>
                    <option value="<?php echo esc_attr( $preset['id'] ); ?>" <?php selected( $current, $preset['id'] ); ?>>
                        <?php echo esc_html( $preset['name'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_frontend_selector() {
        if ( ! ( is_shop() || is_product_taxonomy() ) ) {
            return;
        }

        $presets = $this->get_presets();
        if ( empty( $presets ) ) {
            return;
        }

        $current = $this->get_requested_preset_id();

        echo '<div class="wcpsp-selector-wrap">';
        echo '<label for="wcpsp-selector">' . esc_html__( 'Sort by preset', 'wc-product-sort-presets-premium' ) . '</label>';
        echo '<select id="wcpsp-selector" class="wcpsp-selector">';
        echo '<option value="">' . esc_html__( 'Select preset', 'wc-product-sort-presets-premium' ) . '</option>';

        foreach ( $presets as $preset ) {
            $selected = ( $current === (string) $preset['id'] ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $preset['id'] ) . '"' . $selected . '>' . esc_html( $preset['name'] ) . '</option>';
        }

        echo '</select>';
        echo '</div>';
    }

    private function get_requested_preset_id() {
        if ( isset( $_GET['wcpsp_preset'] ) ) {
            return sanitize_key( wp_unslash( $_GET['wcpsp_preset'] ) );
        }

        return '';
    }

    private function determine_active_preset() {
        $preset_id = $this->get_requested_preset_id();

        if ( ! empty( $preset_id ) ) {
            $presets = $this->get_presets();
            if ( isset( $presets[ $preset_id ] ) ) {
                return $presets[ $preset_id ];
            }
        }

        $presets = $this->get_presets();
        $current_category_ids = $this->get_current_product_scope_ids( 'product_cat' );
        $current_tag_ids = $this->get_current_product_scope_ids( 'product_tag' );

        foreach ( $presets as $preset ) {
            $category_ids = ! empty( $preset['category_ids'] ) ? array_map( 'absint', (array) $preset['category_ids'] ) : array();
            $tag_ids = ! empty( $preset['tag_ids'] ) ? array_map( 'absint', (array) $preset['tag_ids'] ) : array();

            if ( ! empty( $category_ids ) && ! empty( $current_category_ids ) && array_intersect( $current_category_ids, $category_ids ) ) {
                return $preset;
            }

            if ( ! empty( $tag_ids ) && ! empty( $current_tag_ids ) && array_intersect( $current_tag_ids, $tag_ids ) ) {
                return $preset;
            }
        }

        $default = $this->get_default_preset();
        return $default ? $default : false;
    }

    private function get_current_product_scope_ids( $taxonomy ) {
        $ids = array();

        if ( is_tax( $taxonomy ) ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (int) $term->term_id;
            }
        }

        return $ids;
    }

    public function default_catalog_orderby( $default ) {
        if ( is_admin() ) {
            return $default;
        }

        if ( isset( $_GET['orderby'] ) ) {
            return $default;
        }

        $preset = $this->determine_active_preset();
        if ( ! $preset ) {
            return $default;
        }

        return $this->preset_to_orderby_string( $preset );
    }

    public function catalog_ordering_args( $args, $orderby ) {
        if ( is_admin() ) {
            return $args;
        }

        $preset = $this->determine_active_preset();
        if ( ! $preset ) {
            return $args;
        }

        return $this->build_ordering_args( $preset );
    }

    private function preset_to_orderby_string( $preset ) {
        $sort_type = isset( $preset['sort_type'] ) ? $preset['sort_type'] : 'menu_order';

        switch ( $sort_type ) {
            case 'price':
                return 'price';
            case 'price-desc':
                return 'price';
            case 'popularity':
                return 'popularity';
            case 'rating':
                return 'rating';
            case 'date':
                return 'date';
            case 'title':
                return 'title';
            case 'rand':
                return 'rand';
            case 'stock_status':
                return 'menu_order';
            case 'custom_meta':
                return 'menu_order';
            default:
                return 'menu_order';
        }
    }

    private function build_ordering_args( $preset ) {
        $sort_type = isset( $preset['sort_type'] ) ? $preset['sort_type'] : 'menu_order';
        $order = isset( $preset['order'] ) ? strtoupper( $preset['order'] ) : 'ASC';

        switch ( $sort_type ) {
            case 'price':
                return array(
                    'meta_key' => '_price',
                    'orderby'  => 'meta_value_num',
                    'order'    => $order,
                );

            case 'price-desc':
                return array(
                    'meta_key' => '_price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'DESC',
                );

            case 'popularity':
                return array(
                    'meta_key' => 'total_sales',
                    'orderby'  => 'meta_value_num',
                    'order'    => $order,
                );

            case 'rating':
                return array(
                    'meta_key' => '_wc_average_rating',
                    'orderby'  => 'meta_value_num',
                    'order'    => $order,
                );

            case 'stock_status':
                return array(
                    'meta_key' => '_stock_status',
                    'orderby'  => 'meta_value',
                    'order'    => $order,
                );

            case 'custom_meta':
                $meta_key = ! empty( $preset['meta_key'] ) ? $preset['meta_key'] : '_price';
                $meta_type = ! empty( $preset['meta_type'] ) ? strtoupper( $preset['meta_type'] ) : 'NUMERIC';

                if ( 'NUMERIC' === $meta_type ) {
                    return array(
                        'meta_key' => $meta_key,
                        'orderby'  => 'meta_value_num',
                        'order'    => $order,
                    );
                }

                if ( 'DATE' === $meta_type ) {
                    return array(
                        'meta_key' => $meta_key,
                        'orderby'  => 'meta_value',
                        'order'    => $order,
                    );
                }

                return array(
                    'meta_key' => $meta_key,
                    'orderby'  => 'meta_value',
                    'order'    => $order,
                );

            case 'date':
            case 'title':
            case 'menu_order':
            case 'rand':
                return array(
                    'orderby' => $sort_type,
                    'order'   => $order,
                );

            default:
                return array(
                    'orderby' => 'menu_order',
                    'order'   => 'ASC',
                );
        }
    }

    public function ajax_get_preset() {
        check_ajax_referer( 'wcpsp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-product-sort-presets-premium' ) ) );
        }

        $preset_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
        $presets = $this->get_presets();

        if ( empty( $preset_id ) || ! isset( $presets[ $preset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset not found.', 'wc-product-sort-presets-premium' ) ) );
        }

        wp_send_json_success( array( 'preset' => $presets[ $preset_id ] ) );
    }

    public function ajax_save_preset() {
        check_ajax_referer( 'wcpsp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-product-sort-presets-premium' ) ) );
        }

        $preset_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
        $name = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';
        $sort_type = isset( $_POST['sort_type'] ) ? sanitize_key( wp_unslash( $_POST['sort_type'] ) ) : 'menu_order';
        $order = isset( $_POST['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['order'] ) ) ) : 'ASC';
        $meta_key = isset( $_POST['meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
        $meta_type = isset( $_POST['meta_type'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['meta_type'] ) ) ) : 'NUMERIC';
        $category_ids = isset( $_POST['category_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['category_ids'] ) ) : array();
        $tag_ids = isset( $_POST['tag_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tag_ids'] ) ) : array();
        $set_default = ! empty( $_POST['set_default'] );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset name is required.', 'wc-product-sort-presets-premium' ) ) );
        }

        $allowed = array_keys( $this->get_sort_options() );
        if ( ! in_array( $sort_type, $allowed, true ) ) {
            $sort_type = 'menu_order';
        }

        if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
            $order = 'ASC';
        }

        if ( empty( $preset_id ) ) {
            $preset_id = wp_generate_uuid4();
        }

        $preset = array(
            'id'          => $preset_id,
            'name'        => $name,
            'sort_type'   => $sort_type,
            'order'       => $order,
            'meta_key'    => $meta_key,
            'meta_type'   => in_array( $meta_type, array( 'NUMERIC', 'CHAR', 'DATE' ), true ) ? $meta_type : 'NUMERIC',
            'category_ids'=> $category_ids,
            'tag_ids'     => $tag_ids,
        );

        $presets = $this->get_presets();
        $presets[ $preset_id ] = $preset;
        update_option( self::OPTION_PRESETS, $presets );

        if ( $set_default ) {
            update_option( self::OPTION_DEFAULT, $preset_id );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Preset saved.', 'wc-product-sort-presets-premium' ),
                'markup'  => $this->render_presets_markup( $presets, get_option( self::OPTION_DEFAULT, '' ) ),
            )
        );
    }

    public function ajax_delete_preset() {
        check_ajax_referer( 'wcpsp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-product-sort-presets-premium' ) ) );
        }

        $preset_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
        $presets = $this->get_presets();

        if ( empty( $preset_id ) || ! isset( $presets[ $preset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset not found.', 'wc-product-sort-presets-premium' ) ) );
        }

        unset( $presets[ $preset_id ] );
        update_option( self::OPTION_PRESETS, $presets );

        $default_id = get_option( self::OPTION_DEFAULT, '' );
        if ( $default_id === $preset_id ) {
            delete_option( self::OPTION_DEFAULT );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Preset deleted.', 'wc-product-sort-presets-premium' ),
                'markup'  => $this->render_presets_markup( $presets, get_option( self::OPTION_DEFAULT, '' ) ),
            )
        );
    }

    public function ajax_set_default_preset() {
        check_ajax_referer( 'wcpsp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-product-sort-presets-premium' ) ) );
        }

        $preset_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
        $presets = $this->get_presets();

        if ( empty( $preset_id ) || ! isset( $presets[ $preset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset not found.', 'wc-product-sort-presets-premium' ) ) );
        }

        update_option( self::OPTION_DEFAULT, $preset_id );

        wp_send_json_success(
            array(
                'message' => __( 'Default preset updated.', 'wc-product-sort-presets-premium' ),
                'markup'  => $this->render_presets_markup( $presets, $preset_id ),
            )
        );
    }
}

add_action(
    'plugins_loaded',
    function () {
        if ( class_exists( 'WooCommerce' ) ) {
            WC_Product_Sort_Presets_Premium::init();
        }
    }
);
