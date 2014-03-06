<?php

add_action('wp_ajax_process_upsell', 'novaksolutions_process_upsell');
add_action('wp_ajax_nopriv_process_upsell', 'novaksolutions_process_upsell');

function novaksolutions_upsell_getContactId($orderId, $firstName, $lastName) {
    Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));

    try {
        $order = new Infusionsoft_Job($orderId);
    } catch (Exception $e) {
        return false;
    }

    $contactId = (string) $order->ContactId;

    if($contactId) {

        try {
            $contact = new Infusionsoft_Contact($contactId);
        } catch (Exception $e) {
            return false;
        }

        if((string) $contact->FirstName == $firstName && (string) $contact->LastName == $lastName){
            return $contactId;
        }
    }

    return false;
}

function novaksolutions_process_upsell(){
//    require_once('Infusionsoft/infusionsoft.php');

    $merchantaccount_id = 0;

    if($_POST['test'] == 'true'){
        $merchantaccount_id = get_option('novaksolutions_upsell_test_merchantaccount_id');
    } else {
        $merchantaccount_id = get_option('novaksolutions_upsell_merchantaccount_id');
    }

    Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));

    //Load information from request
    $contact_id = $_POST['contact_id'];
    $order_id = $_POST['order_id'];

    $product_id = $_POST['product_id'];


    $error = false;

    $success_url = $_POST['success_url'];
    $failure_url = $_POST['failure_url'];

    $quantity = 1;

    $pass_along_params = array('orderId' => $order_id, 'contactId' => $contact_id);
    try {
        $contact = new Infusionsoft_Contact($contact_id);

        $authed = false;

        if($order_id != ''){
            $order = new Infusionsoft_Job($order_id);
            if($order->ContactId == $contact_id){
                $authed = true;
                $invoices = Infusionsoft_DataService::query(new Infusionsoft_Invoice(), array('JobId' => $order->Id));
                if(count($invoices) > 0){
                    $invoice = array_shift($invoices);
                    $lead_affiliate_id = $invoice->LeadAffiliateId;
                    $sale_affiliate_id = $invoice->AffiliateId;
                }
            } else {
                $authed = false;
            }
        }

        if($authed){
            $creditCards = Infusionsoft_DataService::queryWithOrderBy(new Infusionsoft_CreditCard(), array('ContactId' => $contact_id), 'Id', false, 1, 0, array('Id'));

            if (count($creditCards) > 0) {
                $creditCard = array_shift($creditCards);
                $creditCardId = $creditCard->Id;

                $original_time_zone = date_default_timezone_get();
                date_default_timezone_set('America/New_York');

                $product = new Infusionsoft_Product($product_id);

                $invoiceId = Infusionsoft_InvoiceService::createBlankOrder($contact_id, 'Upsell - ' . $product->ProductName, date('Ymd') . 'T00:00:00', $lead_affiliate_id, $sale_affiliate_id);

                Infusionsoft_InvoiceService::addOrderItem($invoiceId, $product_id, 4, $product->ProductPrice, $quantity, $product->ProductName, $product->ProductName);


                $amountOwed = Infusionsoft_InvoiceService::calculateAmountOwed($invoiceId);
                Infusionsoft_InvoiceService::addPaymentPlan($invoiceId, true, $creditCardId, $merchantaccount_id, 3, 3, $amountOwed, date('Ymd') . 'T00:00:00', date('Ymd') . 'T00:00:00', 0, 0);
                date_default_timezone_set($original_time_zone);
                $orderInfo = Infusionsoft_InvoiceService::getOrderId($invoiceId);
                $orderId = $orderInfo['orderId'];

                $order = new Infusionsoft_Job($orderId);

//                foreach ($order_data as $orderParam) {
//                    $orderParamPieces = explode('=', $orderParam);
//                    if (count($orderParamPieces) == 2) {
//                        if (strpos($orderParamPieces[0], '_') === 0) {
//                            Infusionsoft_Job::addCustomField($orderParamPieces[0]);
//                        }
//                        $order->$orderParamPieces[0] = $orderParamPieces[1];
//                    }
//                }

                $order->save();

                $result = Infusionsoft_InvoiceService::chargeInvoice($invoiceId, "Upsell Payment", $creditCardId, $merchantaccount_id, false);
                if($result['Successful'] == true){
                    if(!empty($_POST['action_set_id'])){
                        Infusionsoft_ContactService::runActionSequence($contact->Id, $_POST['action_set_id']);
                    }
                } else {
                    $error = true;
                }
            } else {
                $error = true;
                $message = 'No credit cards on file for contact: ' . $contact_id;
            }

            if (!$error) {
                header('Location: ' . add_params_to_url($success_url, $pass_along_params));
            } else{
                header('Location: ' . $failure_url . '?msg=' . urlencode($message));
            }
        } else {
            header('Location: ' . $failure_url . '?msg=' . urlencode('Order or Subscription do not belong to specified contact.'));
        }
    } catch (Exception $e) {
        header('Location: ' . $failure_url . '?msg=Exception Caught');
    }
}

function add_params_to_url($url, $params){
    if(strpos($url, "?") === false){
        $url .= "?";
    } else {
        if(substr($url, -1, 1) != '&'){
            $url .= '&';
        }
    }
    $url .= http_build_query($params);
    return $url;
}
