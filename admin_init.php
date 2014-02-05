<?php

add_action('admin_menu', 'novaksolutions_upsell_add_admin_menu');
add_action('admin_init', 'novaksolutions_admin_init');

function novaksolutions_upsell_plugin_action_links( $links, $file ) {
    if ( $file == plugin_basename( dirname(__FILE__).'/infusionsoft-one-click-upsell.php' ) ) {
        $links[] = '<a href="' . admin_url( 'admin.php?page=novaksolutions_upsell_admin_menu' ) . '">'.__( 'Settings' ).'</a>';
    }

    return $links;
}
add_filter('plugin_action_links', 'novaksolutions_upsell_plugin_action_links', 10, 2);

function novaksolutions_admin_init(){
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_default_success_url');
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_default_failure_url');
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_merchantaccount_id');
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_test_merchantaccount_id');
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_default_action_set_id');
    register_setting('novaksolutions_upsell', 'novaksolutions_upsell_default_class');

    // Add the section to reading settings so we can add our
    // fields to it
    add_settings_section('novaksolutions_upsell_setting_section_app',
        'Infusionsoft App',
        null,
        'novaksolutions-upsell-settings');

    add_settings_section('novaksolutions_upsell_setting_section_defaults',
        'Upsell Defaults',
        null,
        'novaksolutions-upsell-settings');

    // Add the field with the names and function to use for our new
    // settings, put it in our new section
    add_settings_field('novaksolutions_upsell_merchantaccount_id',
        'Merchant Account ID',
        'novaksolutions_upsell_callback_function_merchantaccount_id',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_app');

    add_settings_field('novaksolutions_upsell_test_merchantaccount_id',
        'Test Merchant Account ID',
        'novaksolutions_upsell_callback_function_test_merchantaccount_id',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_app');

    add_settings_field('novaksolutions_upsell_default_success_url',
        'Default Success URL',
        'novaksolutions_upsell_callback_function_default_success_url',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_defaults');

    add_settings_field('novaksolutions_upsell_default_failure_url',
        'Default Failure URL',
        'novaksolutions_upsell_callback_function_default_failure_url',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_defaults');

    add_settings_field('novaksolutions_upsell_default_action_set_id',
        'Default Action Set ID',
        'novaksolutions_upsell_callback_function_default_action_set_id',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_defaults');

    add_settings_field('novaksolutions_upsell_default_class',
        'Default Button Class',
        'novaksolutions_upsell_callback_function_default_class',
        'novaksolutions-upsell-settings',
        'novaksolutions_upsell_setting_section_defaults');

    // Register our setting so that $_POST handling is done for us and
    // our callback function just has to echo the <input>
    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_merchantaccount_id', 'novaksolutions_upsell_sanitize_absint');
    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_test_merchantaccount_id', 'novaksolutions_upsell_sanitize_absint');

    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_success_url', 'esc_url');
    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_failure_url', 'esc_url');
    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_action_set_id', 'novaksolutions_upsell_sanitize_absint');
    register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_class', 'sanitize_html_class');

}

function novaksolutions_upsell_sanitize_absint($value) {
    $value = absint($value);
    return $value === 0 ? '' : $value;
}

function novaksolutions_upsell_callback_function_default_success_url() {
    echo '<input type="text" name="novaksolutions_upsell_default_success_url" value="' . get_option('novaksolutions_upsell_default_success_url') . '" size="45" /><br />';
    echo '<span class="description">Unless you specify a different success URL in your shortcode, your customer will be directed to this URL after a successful upsell.</span>';
}

function novaksolutions_upsell_callback_function_default_failure_url() {
    echo '<input type="text" name="novaksolutions_upsell_default_failure_url" value="' . get_option('novaksolutions_upsell_default_failure_url') . '" size="45" /><br />';
    echo '<span class="description">Unless you specify a different failure URL in your shortcode, your customer will be directed to this URL if something goes wrong during the upsell (such as a rejected credit card).</span>';
}

function novaksolutions_upsell_callback_function_merchantaccount_id() {
    echo '<input type="text" name="novaksolutions_upsell_merchantaccount_id" value="' . get_option('novaksolutions_upsell_merchantaccount_id') . '" /><br />';
    echo '<span class="description">This merchant account will be used when processing upsell orders. To find your merchant account ID, open Infusionsoft and go to E-Commerce&rarr;Settings&rarr;Merchant Accounts. The account ID will be listed after "ID=" in the edit URL for the merchant account you\'d like to use.</span>';
}

function novaksolutions_upsell_callback_function_test_merchantaccount_id() {
    echo '<input type="text" name="novaksolutions_upsell_test_merchantaccount_id" value="' . get_option('novaksolutions_upsell_test_merchantaccount_id') . '" /><br />';
    echo '<span class="description">This merchant account will be used when test="true" is present in the upsell shortcode.</span>';
}

function novaksolutions_upsell_callback_function_default_action_set_id() {
    echo '<input type="text" name="novaksolutions_upsell_default_action_set_id" value="' . get_option('novaksolutions_upsell_default_action_set_id') . '" /><br />';
    echo '<span class="description">Unless you specify a different action set ID in your shortcode, we will run this action set after a successful upsell. To find your action set ID, open Infusionsoft and go to E-commerce&rarr;Actions. Click Actions for the set you\'d like to use. The action set ID is listed after "ID=" in the URL of the window that opens after clicking the Actions button.</span>';
}

function novaksolutions_upsell_callback_function_default_class() {
    echo '<input type="text" name="novaksolutions_upsell_default_class" value="' . get_option('novaksolutions_upsell_default_class') . '" /><br />';
    echo '<span class="description">You can style your upsell button by adding a CSS class to it.</span>';
}

function novaksolutions_upsell_add_admin_menu(){
    add_menu_page( "Infusionsoft® One-click Upsell", "One-click Upsell", "edit_plugins", "novaksolutions_upsell_admin_menu", 'novaksolutions_upsell_display_admin_page');
    add_submenu_page( "novaksolutions_upsell_admin_menu", "Infusionsoft® One-click Upsell", "Settings", "edit_plugins", "novaksolutions_upsell_admin_menu", 'novaksolutions_upsell_display_admin_page');
    add_submenu_page( "novaksolutions_upsell_admin_menu", "One-click Upsell Usage Instructions", "Usage", "edit_posts", "novaksolutions_upsell_usage", "novaksolutions_upsell_display_usage");
    add_submenu_page( "novaksolutions_upsell_admin_menu", "Product Upsell Links", "Product Upsell Links", "edit_posts", "novaksolutions_upsell_product_links", "novaksolutions_upsell_display_product_links");
}

function novaksolutions_upsell_get_products(){
//    include('Infusionsoft/infusionsoft.php');
    if( !is_plugin_active( 'infusionsoft-sdk/infusionsoft-sdk.php' )){
        $products = array();
        return $products;
    }

    $enough_to_go = false;
    $valid_key = true;
    if(get_option('infusionsoft_sdk_app_name') != '' && get_option('infusionsoft_sdk_api_key') != ''){
        $enough_to_go = true;

        try{
            Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));
            $products = Infusionsoft_DataService::query(new Infusionsoft_Product(), array('Id' => '%'));
        } catch(Infusionsoft_Exception $e) {
            $enough_to_go = false;

            if($e == '[InvalidKey]Invalid Key') {
                $valid_key = false;
                add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
            }
        }
    } else {
        $products = array();

        if(!get_option('infusionsoft_sdk_app_name')){
            add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "Please enter your Infusionsoft app name.", "error");
        }

        if(!get_option('infusionsoft_sdk_api_key')){
            $valid_key = false;
            add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "Please enter your Infusionsoft API key.", "error");
        }
    }

    if($enough_to_go && Infusionsoft_DataService::ping()){
        try{
            Infusionsoft_DataService::findByField(new Infusionsoft_Contact(), 'Id', -1);
        } catch(Exception $e){
            add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
        }
    } else {
        if($valid_key){
            add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "The app name you entered is invalid.", "error");
        }
    }

    if(!is_numeric(get_option('novaksolutions_upsell_merchantaccount_id'))){
        add_settings_error("novaksolutions_upsell_merchantaccount_id", "novaksolutions_upsell_merchantaccount_id", "The merchant account ID you entered is invalid.", "error");
    }

    return $products;
}

function novaksolutions_upsell_display_link_back(){
    echo '<h2>Like this plugin?</h2>';
    echo '<p>Visit <a href="http://novaksolutions.com/?utm_source=wordpress&utm_medium=link&utm_campaign=upsell">Novak Solutions</a> to find dozens of free tips, tricks, and tools to help you get the most out of Infusionsoft®.</p>';
}

function novaksolutions_upsell_display_admin_page(){
    echo '<h2>Infusionsoft&reg; One-click Upsell Settings</h2>';

    $products = novaksolutions_upsell_get_products();
    settings_errors();

    echo '<form method="POST" action="options.php">';
    settings_fields('novaksolutions-upsell-settings');   //pass slug name of page
    do_settings_sections('novaksolutions-upsell-settings');    //pass slug name of page
    submit_button();
    echo '</form>';

    novaksolutions_upsell_display_link_back();
}

function novaksolutions_upsell_display_product_links(){
    echo '<h2>Product Upsell Links</h2>';

    $products = novaksolutions_upsell_get_products();
    settings_errors();

    echo '<p>Getting started with the <em>Infusionsoft&reg; One-click Upsell</em> plugin is easy. Simply include one of these <em>upsell</em> shortcodes in your order "thank you" page.</p>';

    if(count($products) > 0){
        echo '<p><em>Showing the first <strong>' . number_format(count($products)). '</strong> products in your Infusionsoft app.</em></p>';
        echo '<ul>';
        foreach($products as $product){
            echo '<li>[upsell product_id="' . $product->Id . '" button_text="Buy ' . htmlentities($product->ProductName) . ' Now!"]</li>';
        }
        echo '</ul>';
    } else {
        echo "<p><strong>We weren't able to find any products in your Infusionsoft app. Please make sure your app name and API key are correct on the settings page.</strong></p>";
    }

    novaksolutions_upsell_display_link_back();
}

function novaksolutions_upsell_display_usage(){
    ?>
    <h2>One-click Upsell Usage Instructions</h2>

    <h3>Upsell Usage</h3>
    <p><strong>Example usage:</strong> [upsell product_id="12"]</p>
    <p>Use the upsell shortcode on any Thank You page or post. Make sure you select the option in Infusionsoft to pass the contact's information to the Thank You page.</p>
    <p>Once the customer clicks the upsell button, One-click Upsell will charge the customer's last used credit card and place the order in Infusionsoft.</p>

    <h4>Parameters:</h4>

    <p><strong>product_id</strong> &ndash; The shortcode's only required parameter is the product_id, which should contain the numeric product ID from Infusionsoft. You can find a sample list of your products on the <a href="<?php echo admin_url( 'admin.php?page=novaksolutions_upsell_product_links' ); ?>">Product Upsell Links</a> page.</p>

    <p><strong>button_text</strong> &ndash; You can change the text of the upsell button by setting the button_text parameter.</p>

    <p><strong>class</strong> &ndash; You can add a CSS class to your upsell button with the class parameter.</p>

    <p><strong>success_url</strong> &ndash; If the upsell is successful, the customer will be redirected to this URL.</p>

    <p><strong>failure_url</strong> &ndash; If the upsell fails (for example, if the credit card is declined), the customer will be redirected to this URL.</p>

    <p><strong>action_set_id</strong> &ndash; Optionally run an Infusionsoft action set if the order is successful.</p>

    <p><strong>test</strong> &ndash; To enable test mode for this upsell, set this parameter to <em>true</em>. This will give you additional debugging information, and send the transaction through the test merchant account that you've configured.</p>

    <h3>Downsell Usage</h3>

    <p><strong>Example usage:</strong> <em>&lt;a href="/some-wordpress-post/?[downsell]"&gt;No Thanks&lt;/a&gt;</em></p>

    <p>To add contact information to a URL so you can handle downsells, add <strong>?[downsell]</strong> to the end of any link. This will add the required URL parameters to the URL as if the user had come directly from the Infusionsoft cart or order form.</p>

    <?php
    novaksolutions_upsell_display_link_back();
}
