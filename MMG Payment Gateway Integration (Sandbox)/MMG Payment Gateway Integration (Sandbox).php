<?php
/**
 * Plugin Name: MMG Payment Gateway Integration (Sandbox)
 * Description: A sandbox integration for MMG Payment Gateway for testing.
 * Version: 1.0
 * Author: Your Name
 */

// Hook to initialize the plugin
function mmg_payment_gateway_sandbox_init() {
    // Register the payment form shortcode
    add_shortcode('mmg_payment_form_sandbox', 'mmg_payment_form_sandbox_shortcode');
}

add_action('init', 'mmg_payment_gateway_sandbox_init');

// Function to encrypt payment data
function mmg_encrypt_payment_data($data) {
    $merchant_msisdn = isset($data['merchantId']) ? $data['merchantId'] : '';
    $public_key_path = plugin_dir_path(__FILE__) . 'keys/' . $merchant_msisdn . '.public.pem';
    
    if (!file_exists($public_key_path)) {
        throw new Exception('Public key not found for merchant: ' . $merchant_msisdn);
    }

    $public_key = openssl_pkey_get_public(file_get_contents($public_key_path));
    if (!$public_key) {
        throw new Exception('Failed to load public key');
    }

    $json_data = json_encode($data);
    if (!openssl_public_encrypt($json_data, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new Exception('Encryption failed');
    }

    return base64_encode($encrypted);
}

// Shortcode to display the payment form
function mmg_payment_form_sandbox_shortcode() {
    ob_start();
    ?>
    <form id="mmg-payment-form" method="post" action="">
        <label for="amount">Amount:</label>
        <input type="number" name="amount" id="amount" value="100" readonly>
        <br>
        <label for="merchant_msisdn">Merchant MSISDN:</label>
        <input type="text" name="merchant_msisdn" id="merchant_msisdn" value="7771122" readonly>
        <br>
        <input type="submit" name="submit_payment" value="Pay Now">
    </form>
    <?php
    if (isset($_POST['submit_payment'])) {
        try {
            mmg_process_payment_sandbox();
        } catch (Exception $e) {
            echo "Payment failed: " . esc_html($e->getMessage());
        }
    }
    return ob_get_clean();
}

// Function to process the payment in sandbox mode
function mmg_process_payment_sandbox() {
    $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
    $merchant_msisdn = isset($_POST['merchant_msisdn']) ? $_POST['merchant_msisdn'] : '';

    // Sandbox credentials
    $merchant = "E-Commerce Merchant";  // From UAT setup
    $secret_key = "m7771122gxgd"; // From UAT setup
    $client_id = "2ae426d409aa4b3cae1ef2eeb87a5388";   // From UAT setup
    $merchant_name = $merchant . " (" . $merchant_msisdn . ")";
    $description = "Lorem Ipsum Oil";
    $timestamp = time();

    // Create request data
    $payment_data = array(
        'secretKey' => $secret_key,
        'amount' => $amount,
        'merchantId' => $merchant_msisdn,
        'merchantTransactionId' => (string)$timestamp,
        'productDescription' => $description,
        'requestInitiationTime' => $timestamp,
        'merchantName' => $merchant_name
    );

    try {
        // Encrypt the payment data
        $encrypted_token = mmg_encrypt_payment_data($payment_data);
        
        // Build the checkout URL
        $sandbox_api_url = 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home';
        $checkout_url = add_query_arg(array(
            'token' => $encrypted_token,
            'merchantId' => $merchant_msisdn,
            'X-Client-ID' => $client_id
        ), $sandbox_api_url);

        // Redirect to checkout
        wp_redirect($checkout_url);
        exit;

    } catch (Exception $e) {
        echo "Payment failed: " . esc_html($e->getMessage());
    }
}
