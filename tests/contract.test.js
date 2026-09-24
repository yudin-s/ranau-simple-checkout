'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const bootstrap = fs.readFileSync(path.join(root, 'ranau-simple-checkout.php'), 'utf8');
const plugin = fs.readFileSync(path.join(root, 'src', 'Plugin.php'), 'utf8');
const frontend = fs.readFileSync(path.join(root, 'assets', 'js', 'checkout.js'), 'utf8');
const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8');

assert(bootstrap.includes('Plugin Name:       Ranau Simple Checkout for WooCommerce'));
assert(bootstrap.includes('Requires Plugins:  woocommerce'));
assert(bootstrap.includes("declare_compatibility('custom_order_tables'"));
assert(bootstrap.includes("declare_compatibility('cart_checkout_blocks'"));
const legacyBrand = ['Glow', 'Me'].join(' ');
const legacyNamespace = ['Glow', 'Me'].join('');
const legacyHost = ['glow', 'me.shop'].join('-');
assert(!bootstrap.includes(legacyBrand));
assert(!plugin.includes(legacyNamespace));
assert(!frontend.includes(legacyNamespace));

assert(plugin.includes("'namespace' => self::STORE_API_NAMESPACE"));
assert(plugin.includes('woocommerce_store_api_register_update_callback'));
assert(plugin.includes('woocommerce_store_api_checkout_update_order_from_request'));
assert(plugin.includes('woocommerce_after_checkout_validation'));
assert(plugin.includes('woocommerce_checkout_create_order'));
assert(plugin.includes('@example.invalid'));
assert(plugin.includes("apply_filters('ranau_simple_checkout_countries', array('RU'))"));

const extensionUpdateCount = (frontend.match(/extensionCartUpdate\(/g) || []).length;
assert.strictEqual(extensionUpdateCount, 1, 'a city commit must have one Blocks cart update owner');
assert(frontend.includes("trigger('update_checkout')"));
assert(frontend.indexOf("trigger('update_checkout')") > frontend.indexOf('extensionCartUpdate'));
assert(frontend.includes('new window.MutationObserver(scheduleMount)'));
assert(frontend.includes('if (scheduled)'));

assert(readme.includes('Stable tag: 0.1.0'));
assert(readme.includes('No. The plugin does not call Ranau or any analytics service.'));
assert(readme.includes('https://ranau.uk/'));
assert(!readme.includes(legacyHost));

console.log('Ranau Simple Checkout contract checks passed.');
