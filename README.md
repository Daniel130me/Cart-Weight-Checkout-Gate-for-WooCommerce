# CartQuota – Minimum Weight for WooCommerce

CartQuota – Minimum Weight for WooCommerce requires the combined weight of
shippable cart items to reach an administrator-configured minimum before a
customer can check out or pay.

The default minimum is 10 kg. Administrators and WooCommerce Shop Managers can
change the minimum or disable enforcement from the WooCommerce settings menu.

## Features

- Clear, accessible cart-weight progress indicator.
- Immediate quantity-change preview with server verification.
- Server-side validation for classic checkout, Cart and Checkout Blocks, the
  Store API, order creation, and payment links.
- Automatic conversion from the WooCommerce product-weight unit to kilograms.
- Support for High-Performance Order Storage (HPOS).
- No external services, advertising, analytics, tracking, or telemetry.

## Requirements

- WordPress 6.5 or later.
- PHP 7.4 or later.
- WooCommerce 8.0 or later.
- Accurate weights on shippable WooCommerce products.

## Installation

1. Install and activate WooCommerce.
2. Download the latest release ZIP.
3. In WordPress, open **Plugins > Add Plugin > Upload Plugin**.
4. Upload and activate the ZIP.
5. Open **WooCommerce > CartQuota**.
6. Enable enforcement and set the required minimum weight.

## How enforcement works

The progress indicator is customer guidance only. Checkout and payment access
are independently validated on the server, so hiding or bypassing the browser
interface does not bypass the minimum-weight rule.

Only shippable, non-virtual products with a configured WooCommerce weight count
toward the minimum.

## Development

The installable plugin source is kept at the repository root. There is no build
step and no bundled third-party runtime dependency.

Before proposing a change:

1. Run PHP syntax checks on every PHP file.
2. Run `node --check assets/js/frontend.js`.
3. Run the official WordPress Plugin Check plugin.
4. Test both classic and block-based WooCommerce cart and checkout flows.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution expectations and
[SECURITY.md](SECURITY.md) for responsible vulnerability reporting.

## Privacy

The plugin does not collect or transmit personal data. A private numeric order
weight snapshot is stored with the WooCommerce order so later payment attempts
can be validated consistently.

## License

Licensed under the GNU General Public License, version 2 or later. See
[LICENSE](LICENSE).

