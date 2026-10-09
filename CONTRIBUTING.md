# Contributing

Thank you for helping improve Cart Weight Checkout Gate for WooCommerce.

## Before opening a pull request

- Keep changes focused and compatible with the supported WordPress, PHP, and
  WooCommerce versions.
- Use WordPress and WooCommerce public APIs.
- Preserve server-side checkout and payment validation.
- Sanitize input, escape output, check capabilities, and use nonces for
  state-changing requests.
- Avoid external services, telemetry, advertising, and unnecessary database
  queries.
- Add comments only where the purpose or security reasoning is not obvious.
- Update `readme.txt` when behavior or compatibility changes.

## Verification

Run PHP and JavaScript syntax checks, WordPress Coding Standards,
PHPCompatibilityWP for PHP 7.4+, and the official WordPress Plugin Check tool.
Manually test quantity changes and checkout enforcement in both classic and
block-based WooCommerce experiences.

Do not include generated archives, dependency directories, editor settings, or
local WordPress files in a pull request.

