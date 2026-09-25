# Ranau Simple Checkout for WooCommerce

Independent GPL-2.0-or-later WooCommerce plugin providing a city-and-phone checkout flow.

WordPress.org slug: `ranau-simple-checkout-for-woocommerce`. The shorter
`ranau-simple-checkout` identifier remains the GitHub repository name and the
internal code/asset namespace.

## Development

The distributable WordPress.org package is the plugin directory itself. It has no Composer or npm runtime dependency and makes no external requests.

Run the local contract checks from the ecosystem workspace:

```bash
rtk php -l products/ranau-simple-checkout/ranau-simple-checkout.php
rtk php -l products/ranau-simple-checkout/src/Plugin.php
rtk node --check products/ranau-simple-checkout/assets/js/checkout.js
rtk node products/ranau-simple-checkout/tests/contract.test.js
```

Before release, run the real WooCommerce Blocks and classic checkout browser matrix described in the parent project's Phase 9 gates.

## Support

Community source and issues: https://github.com/yudin-s/ranau-simple-checkout

Optional paid installation, compatibility work, and custom development are available through [ranau.uk](https://ranau.uk/).
