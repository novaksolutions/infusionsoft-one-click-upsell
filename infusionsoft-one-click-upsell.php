<?php

/*
Plugin Name: Infusionsoft One-click Upsell
Plugin URI: http://novaksolutions.com/wordpress-plugins/infusionsoft-one-click-upsell/
Description: Easily upsell Infusionsoft customers from within WordPress.
Author: Novak Solutions
Version: 1.2.0
Author URI: http://novaksolutions.com/
*/

add_action( 'admin_notices', 'novaksolutions_upsell_missing_sdk' );
function novaksolutions_upsell_missing_sdk() {
    if( !is_plugin_active( 'infusionsoft-sdk/infusionsoft-sdk.php' )){
        echo "<div class=\"error\"><p><strong><em>Infusionsoft One-click Upsell</em> requires the <em>Infusionsoft SDK</em> plugin. Please install and activate the <em>Infusionsoft SDK</em> plugin.</strong></p></div>";
    }
}

include(dirname(__FILE__).'/admin_init.php');
include(dirname(__FILE__).'/ajax_init.php');

add_shortcode('upsell', 'novaksolutions_shortcode_upsell');
add_shortcode('downsell', 'novaksolutions_shortcode_downsell');

function novaksolutions_shortcode_upsell($attributes){
    $test = false;
    if(!isset($attributes['success_url'])) $attributes['success_url'] = get_option('novaksolutions_upsell_default_success_url');
    if(!isset($attributes['failure_url'])) $attributes['failure_url'] = get_option('novaksolutions_upsell_default_failure_url');
    if(!isset($attributes['action_set_id'])) $attributes['action_set_id'] = get_option('novaksolutions_upsell_default_action_set_id');
    if(!isset($attributes['id'])) $attributes['id'] = get_option('novaksolutions_upsell_default_id');
    if(!isset($attributes['class'])) $attributes['class'] = get_option('novaksolutions_upsell_default_class');
    if(!isset($attributes['button_text'])) $attributes['button_text'] = 'Yes!';
    if(!isset($attributes['image'], $attributes['image_width'], $attributes['image_height'])) {
        $attributes['image'] = get_option('novaksolutions_upsell_default_image');
        $attributes['image_width'] = get_option('novaksolutions_upsell_default_image_width');
        $attributes['image_height'] = get_option('novaksolutions_upsell_default_image_height');

        if(empty($attributes['image']) || empty($attributes['image_width']) || empty($attributes['image_height'])){
            $useImage = false;
        } else {
            $useImage = true;
        }
    } else {
        $useImage = true;
    }

    // Check if required fields are set
    if(!$attributes['success_url'] || !$attributes['failure_url'] || !get_option('novaksolutions_upsell_merchantaccount_id')) {
        return "The Infusionsoft One-click Upsell plugin is not configured.";
    }

    if(isset($attributes['test'])){
        $test = true;
    }

    $order_id = isset($_GET['orderId']) ? $_GET['orderId'] : '';

    if(isset($_GET['contactId'])) {
        $contact_id = $_GET['contactId'];
    } elseif(isset($_GET['inf_field_FirstName'], $_GET['inf_field_LastName'])) {
        $contact_id = novaksolutions_upsell_getContactId($order_id, $_GET['inf_field_FirstName'], $_GET['inf_field_LastName']);
    } else {
        $contact_id = false;
    }

    $product_id = $attributes['product_id'];
    $success_url = $attributes['success_url'];
    $failure_url = $attributes['failure_url'];
    $button_text = $attributes['button_text'];
    $action_set_id = $attributes['action_set_id'];
    $id = $attributes['id'];
    $class = $attributes['class'];
    $checksum = '';

    ob_start();
    ?>
    <?php echo $test ? 'Test Mode Enabled' : ''; ?>

<?php if($useImage): 

$checksum = crc32($attributes['image']);
$checksum = sprintf("-%u", $checksum);

?>
<style>
.ns-one-click-upsell-button<?php echo $checksum; ?> {
    background: transparent url("<?php echo $attributes['image']; ?>") 0 0 no-repeat;
    color: transparent;
    font-size: 0;
    line-height: 0;
    display: block;
    text-align: center;
    cursor: pointer;
    width: <?php echo $attributes['image_width']; ?>px;
    height: <?php echo $attributes['image_height']; ?>px;
    border: 0;
}
</style>
<?php endif; ?>

    <form action="<?php echo site_url('wp-admin/admin-ajax.php?action=process_upsell');?>" method="post">
        <?php echo $test ? 'Contact ID:' : ''; ?> <input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="contact_id" value="<?php echo htmlentities($contact_id)?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'Existing Order Id:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="order_id" value="<?php echo htmlentities($order_id)?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'Product Id:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="product_id" value="<?php echo $product_id ?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'Success Url:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="success_url" value="<?php echo htmlentities($success_url) ?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'Success Url:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="failure_url" value="<?php echo htmlentities($failure_url) ?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'Test:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="test" value="<?php echo $test ? 'true' : 'false'?>"> <?php echo $test ? '<br />' : ''; ?>
        <?php echo $test ? 'ActionSetId:' : ''; ?><input type="<?php echo $test ? 'text' : 'hidden'; ?>" name="action_set_id" value="<?php echo htmlentities($action_set_id) ?>"> <?php echo $test ? '<br />' : ''; ?>
        <input type="submit" onclick="this.disabled=true; this.form.submit(); return false;" value="<?php echo htmlentities($button_text); ?>" 
            <?php
                if($class) {
                    echo ' class="ns-one-click-upsell-button'.$checksum.' ' . htmlentities($class) . '"';
                } else {
                    echo ' class="ns-one-click-upsell-button'.$checksum.'"';
                }
            ?>
            <?php if($id) echo ' id="' . htmlentities($id) . '"'; ?>
        >
    </form>

    <?php if($test){ ?>Usage: [upsell test="true" action_set_id="" success_url="http://www.google.com" failure_url="http://failblog.com" product_id="12" button_text="Yes!"]<br/>To disable test mode, remove test="true" from the shortcode.<?php } ?>

    <?php
    $html = ob_get_clean();

    return $html;
}

function novaksolutions_shortcode_downsell(){
    $order_id = $_GET['orderId'];

    if(isset($_GET['contactId'])) {
        $contact_id = $_GET['contactId'];
    } elseif(isset($_GET['inf_field_FirstName'], $_GET['inf_field_LastName'])) {
        $contact_id = novaksolutions_upsell_getContactId($order_id, $_GET['inf_field_FirstName'], $_GET['inf_field_LastName']);
    } else {
        $contact_id = false;
    }

    return 'orderId=' . $order_id . '&contactId=' . $contact_id;
}

