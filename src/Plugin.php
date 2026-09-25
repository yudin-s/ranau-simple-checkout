<?php

declare(strict_types=1);

namespace Ranau\WooCommerce\SimpleCheckout;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

defined('ABSPATH') || exit;

final class Plugin
{
    private const STORE_API_NAMESPACE = 'ranau-simple-checkout';
    private const ORDER_META_PLACEHOLDER = '_ranau_simple_checkout_placeholder_email';

    private static ?self $instance = null;
    private bool $storeApiCallbackRegistered = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void
    {
        add_filter('woocommerce_get_country_locale', array($this, 'filterCountryLocale'));
        add_filter('woocommerce_checkout_fields', array($this, 'filterClassicFields'));
        add_filter('woocommerce_checkout_posted_data', array($this, 'completeClassicData'));
        add_filter('default_option_woocommerce_checkout_phone_field', static fn (): string => 'required');

        add_action('woocommerce_after_checkout_validation', array($this, 'validateClassic'), 10, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'markClassicPlaceholderEmail'), 10, 2);
        add_action(
            'woocommerce_store_api_checkout_update_order_from_request',
            array($this, 'completeAndValidateStoreApiOrder'),
            5,
            2
        );
        add_action('woocommerce_blocks_loaded', array($this, 'registerStoreApiCallback'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));

        if (function_exists('woocommerce_store_api_register_update_callback')) {
            $this->registerStoreApiCallback();
        }
    }

    /** @param array<string, mixed> $locale */
    public function filterCountryLocale(array $locale): array
    {
        foreach ($this->minimalCountries() as $country) {
            if (!isset($locale[$country]) || !is_array($locale[$country])) {
                $locale[$country] = array();
            }

            foreach (array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'state', 'postcode') as $field) {
                $locale[$country][$field] = array_merge(
                    is_array($locale[$country][$field] ?? null) ? $locale[$country][$field] : array(),
                    array('required' => false, 'hidden' => true)
                );
            }

            $locale[$country]['city'] = array_merge(
                is_array($locale[$country]['city'] ?? null) ? $locale[$country]['city'] : array(),
                array('required' => true, 'hidden' => false, 'label' => __('Delivery city', 'ranau-simple-checkout-for-woocommerce'))
            );
            $locale[$country]['phone'] = array_merge(
                is_array($locale[$country]['phone'] ?? null) ? $locale[$country]['phone'] : array(),
                array('required' => true, 'hidden' => false, 'label' => __('Phone number', 'ranau-simple-checkout-for-woocommerce'))
            );
        }

        return $locale;
    }

    /** @param array<string, mixed> $fields */
    public function filterClassicFields(array $fields): array
    {
        foreach (array('billing', 'shipping') as $section) {
            if (!isset($fields[$section]) || !is_array($fields[$section])) {
                continue;
            }

            foreach ($fields[$section] as $key => &$field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = preg_replace('/^(billing|shipping)_/', '', (string) $key);
                if (in_array($name, array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'state', 'postcode', 'email'), true)) {
                    $field['required'] = false;
                    $field['type'] = 'hidden';
                    $field['class'] = array('ranau-simple-checkout__internal-field');
                }

                if ($name === 'city') {
                    $field['required'] = true;
                    $field['label'] = __('Delivery city', 'ranau-simple-checkout-for-woocommerce');
                    $field['priority'] = 20;
                }

                if ($name === 'phone') {
                    $field['required'] = true;
                    $field['label'] = __('Phone number', 'ranau-simple-checkout-for-woocommerce');
                    $field['priority'] = 10;
                }
            }
            unset($field);
        }

        return $fields;
    }

    /** @param array<string, mixed> $data */
    public function completeClassicData(array $data): array
    {
        $phone = $this->sanitizePhone((string) ($data['billing_phone'] ?? $data['shipping_phone'] ?? ''));
        $city = sanitize_text_field((string) ($data['shipping_city'] ?? $data['billing_city'] ?? ''));
        $emailWasEmpty = trim((string) ($data['billing_email'] ?? '')) === '';

        $data['billing_phone'] = $phone;
        $data['shipping_phone'] = $phone;
        $data['billing_city'] = $city;
        $data['shipping_city'] = $city;
        $data['billing_country'] = $this->allowedCountry((string) ($data['billing_country'] ?? ''));
        $data['shipping_country'] = $this->allowedCountry((string) ($data['shipping_country'] ?? ''));
        $data['billing_first_name'] = $this->fallback((string) ($data['billing_first_name'] ?? ''), __('Customer', 'ranau-simple-checkout-for-woocommerce'));
        $data['shipping_first_name'] = $this->fallback((string) ($data['shipping_first_name'] ?? ''), $data['billing_first_name']);
        $data['billing_last_name'] = $this->fallback((string) ($data['billing_last_name'] ?? ''), __('Online order', 'ranau-simple-checkout-for-woocommerce'));
        $data['shipping_last_name'] = $this->fallback((string) ($data['shipping_last_name'] ?? ''), $data['billing_last_name']);
        $data['billing_address_1'] = $this->fallback((string) ($data['billing_address_1'] ?? ''), $city);
        $data['shipping_address_1'] = $this->fallback((string) ($data['shipping_address_1'] ?? ''), $city);

        if ($emailWasEmpty) {
            $data['billing_email'] = $this->placeholderEmail($phone);
            $data[self::ORDER_META_PLACEHOLDER] = 'yes';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validateClassic(array $data, \WP_Error $errors): void
    {
        $phone = $this->sanitizePhone((string) ($data['billing_phone'] ?? $data['shipping_phone'] ?? ''));
        $city = trim((string) ($data['shipping_city'] ?? $data['billing_city'] ?? ''));

        if (!$this->validPhone($phone)) {
            $errors->add(
                'ranau_simple_checkout_phone_required',
                __('Enter a valid phone number.', 'ranau-simple-checkout-for-woocommerce')
            );
        }

        if (mb_strlen($city) < 2) {
            $errors->add(
                'ranau_simple_checkout_city_required',
                __('Choose a delivery city.', 'ranau-simple-checkout-for-woocommerce')
            );
        }
    }

    /** @param array<string, mixed> $data */
    public function markClassicPlaceholderEmail(\WC_Order $order, array $data): void
    {
        $email = strtolower((string) $order->get_billing_email());
        if (substr($email, -16) === '@example.invalid') {
            $order->update_meta_data(self::ORDER_META_PLACEHOLDER, 'yes');
        }
    }

    public function completeAndValidateStoreApiOrder(\WC_Order $order, \WP_REST_Request $request): void
    {
        if ($this->isTotalsCalculation($request)) {
            return;
        }

        $shipping = $this->requestArray($request->get_param('shipping_address'));
        $billing = $this->requestArray($request->get_param('billing_address'));
        $phone = $this->sanitizePhone((string) ($shipping['phone'] ?? $billing['phone'] ?? $order->get_billing_phone()));
        $city = sanitize_text_field((string) ($shipping['city'] ?? $billing['city'] ?? $order->get_shipping_city()));

        if (!$this->validPhone($phone)) {
            $this->throwStoreApiError('ranau_simple_checkout_phone_required', __('Enter a valid phone number.', 'ranau-simple-checkout-for-woocommerce'));
        }

        if (mb_strlen($city) < 2) {
            $this->throwStoreApiError('ranau_simple_checkout_city_required', __('Choose a delivery city.', 'ranau-simple-checkout-for-woocommerce'));
        }

        $country = $this->allowedCountry((string) ($shipping['country'] ?? $billing['country'] ?? ''));
        $order->set_billing_phone($phone);
        $order->set_shipping_phone($phone);
        $order->set_billing_city($city);
        $order->set_shipping_city($city);
        $order->set_billing_country($country);
        $order->set_shipping_country($country);
        $order->set_billing_first_name($this->fallback($order->get_billing_first_name(), __('Customer', 'ranau-simple-checkout-for-woocommerce')));
        $order->set_shipping_first_name($this->fallback($order->get_shipping_first_name(), $order->get_billing_first_name()));
        $order->set_billing_last_name($this->fallback($order->get_billing_last_name(), __('Online order', 'ranau-simple-checkout-for-woocommerce')));
        $order->set_shipping_last_name($this->fallback($order->get_shipping_last_name(), $order->get_billing_last_name()));
        $order->set_billing_address_1($this->fallback($order->get_billing_address_1(), $city));
        $order->set_shipping_address_1($this->fallback($order->get_shipping_address_1(), $city));

        if (trim($order->get_billing_email()) === '') {
            $order->set_billing_email($this->placeholderEmail($phone));
            $order->update_meta_data(self::ORDER_META_PLACEHOLDER, 'yes');
        }
    }

    public function registerStoreApiCallback(): void
    {
        if ($this->storeApiCallbackRegistered || !function_exists('woocommerce_store_api_register_update_callback')) {
            return;
        }

        woocommerce_store_api_register_update_callback(array(
            'namespace' => self::STORE_API_NAMESPACE,
            'callback' => array($this, 'updateCustomerContext'),
        ));
        $this->storeApiCallbackRegistered = true;
    }

    /** @param mixed $value */
    public function updateCustomerContext($value): void
    {
        $data = is_array($value) ? $value : array();
        $city = sanitize_text_field((string) ($data['city'] ?? ''));
        $phone = $this->sanitizePhone((string) ($data['phone'] ?? ''));

        if (mb_strlen($city) < 2 || !function_exists('WC') || !WC()->customer) {
            return;
        }

        WC()->customer->set_shipping_city($city);
        WC()->customer->set_billing_city($city);
        WC()->customer->set_shipping_country($this->allowedCountry(''));
        WC()->customer->set_billing_country($this->allowedCountry(''));

        if ($this->validPhone($phone)) {
            WC()->customer->set_shipping_phone($phone);
            WC()->customer->set_billing_phone($phone);
        }

        WC()->customer->save();
        $this->clearShippingCache();
    }

    public function enqueueAssets(): void
    {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }

        $script = RANAU_SIMPLE_CHECKOUT_PATH . 'assets/js/checkout.js';
        $style = RANAU_SIMPLE_CHECKOUT_PATH . 'assets/css/checkout.css';
        wp_enqueue_style(
            'ranau-simple-checkout',
            RANAU_SIMPLE_CHECKOUT_URL . 'assets/css/checkout.css',
            array(),
            is_file($style) ? (string) filemtime($style) : RANAU_SIMPLE_CHECKOUT_VERSION
        );
        wp_enqueue_script(
            'ranau-simple-checkout',
            RANAU_SIMPLE_CHECKOUT_URL . 'assets/js/checkout.js',
            array('wc-blocks-checkout'),
            is_file($script) ? (string) filemtime($script) : RANAU_SIMPLE_CHECKOUT_VERSION,
            true
        );
        wp_localize_script('ranau-simple-checkout', 'ranauSimpleCheckout', array(
            'namespace' => self::STORE_API_NAMESPACE,
            'country' => $this->allowedCountry(''),
            'messages' => array(
                'chooseCity' => __('Choose city', 'ranau-simple-checkout-for-woocommerce'),
                'saving' => __('Saving…', 'ranau-simple-checkout-for-woocommerce'),
                'saved' => __('City selected', 'ranau-simple-checkout-for-woocommerce'),
                'invalidCity' => __('Enter a city name.', 'ranau-simple-checkout-for-woocommerce'),
                'invalidPhone' => __('Enter a valid phone number.', 'ranau-simple-checkout-for-woocommerce'),
                'failed' => __('Could not update checkout. Try again.', 'ranau-simple-checkout-for-woocommerce'),
            ),
        ));
    }

    /** @return string[] */
    private function minimalCountries(): array
    {
        $countries = apply_filters('ranau_simple_checkout_countries', array('RU'));

        return array_values(array_filter(array_map('strval', is_array($countries) ? $countries : array('RU'))));
    }

    private function allowedCountry(string $country): string
    {
        $countries = $this->minimalCountries();
        $country = strtoupper(trim($country));

        return in_array($country, $countries, true) ? $country : (string) ($countries[0] ?? 'RU');
    }

    private function sanitizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+()\-\s]/u', '', $phone);

        return trim(is_string($phone) ? $phone : '');
    }

    private function validPhone(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return is_string($digits) && strlen($digits) >= 10 && strlen($digits) <= 15;
    }

    private function placeholderEmail(string $phone): string
    {
        return 'order-' . substr(hash_hmac('sha256', $phone, wp_salt('auth')), 0, 20) . '@example.invalid';
    }

    private function fallback(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private function clearShippingCache(): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $packages = WC()->cart ? WC()->cart->get_shipping_packages() : array();
        foreach (array_keys((array) $packages) as $index) {
            WC()->session->__unset('shipping_for_package_' . $index);
        }
    }

    private function isTotalsCalculation(\WP_REST_Request $request): bool
    {
        return filter_var($request->get_param('__experimental_calc_totals'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @param mixed $value
     *  @return array<string, mixed>
     */
    private function requestArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $decoded = json_decode(wp_json_encode($value), true);

            return is_array($decoded) ? $decoded : array();
        }

        return array();
    }

    private function throwStoreApiError(string $code, string $message): void
    {
        if (class_exists(RouteException::class)) {
            throw new RouteException(esc_attr($code), esc_html($message), 400);
        }

        throw new \RuntimeException(esc_html($message));
    }
}
