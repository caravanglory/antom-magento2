# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is `CaravanGlory_Antom`, a Magento 2 payment module integrating Ant International's (Antom) payment gateway. It provides three payment methods: Credit/Debit Cards, Google Pay, and Apple Pay. The frontend is built for Hyvä Checkout (not Luma/default Magento checkout).

## Development Commands

This module is installed into a Magento 2 instance via Composer. There is no standalone build, test, or lint tooling in this repo. To develop:

```bash
# Install into a Magento 2 project
composer require caravanglory/module-antom

# After code changes, recompile DI and deploy static content
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy

# Clear caches during development
bin/magento cache:flush
```

Logs are written to `var/log/antom.log` (when debug mode is enabled in admin config).

## Architecture

### Payment Method Codes

Three separate Magento payment methods share one gateway infrastructure: `antom_cc`, `antom_googlepay`, `antom_applepay`.

All config is under a shared XML path: `payment/antom/*`.

### Gateway Layer (Magento Payment Gateway pattern)

All three methods share a single `AntomCommandPool` defined in `etc/di.xml` with these commands:
- `create_session` — Creates an Antom payment session (called from frontend before payment)
- `capture` / `refund` / `void` / `cancel` / `inquiry` — Standard order operations

Each command follows Magento's `GatewayCommand` pipeline: **Builder → TransferFactory → Client → Handler/Validator**.

- `Gateway/Http/Client.php` — Wraps `antom/global-open-sdk-php` (`DefaultAlipayClient`). All API calls go through here.
- `Gateway/Config.php` — Central config reader. Handles sandbox/live credential switching and maps Magento payment_action to Antom's captureMode (`AUTOMATIC`/`MANUAL`).
- `Gateway/AmountConverter.php` — Converts Magento amounts to Antom's minor-unit format.

### Frontend (Hyvä Checkout only)

The checkout integration uses Magewire (Hyvä's Livewire-based system), not standard Magento JS components:
- `view/frontend/templates/payment/antom-card.phtml` / `antom-googlepay.phtml` / `antom-applepay.phtml` — Payment method templates
- `*-csp-js.phtml` — Inline scripts that load the Antom Web SDK and mount payment elements
- `Model/Magewire/Payment/AntomPlaceOrderService.php` — Custom Magewire place-order service
- `Observer/Frontend/HyvaCheckoutConfigGenerateBefore.php` — Registers payment method config with Hyvä Checkout

Layout files: `view/frontend/layout/hyva_checkout.xml` and `hyva_checkout_components.xml`.

### Notification Webhook

`Controller/Notification/Index.php` handles async payment notifications from Antom at route `/antom/notification/index`. CSRF validation is bypassed (returns `true`). Signature verification uses `Gateway/Validator/NotificationValidator.php`. Processing is handled by `Model/Notification/Processor.php` which updates order status via `Model/Order/StatusResolver.php`.

### Key Dependencies

- `antom/global-open-sdk-php` ^1.0 — Antom's official PHP SDK (provides `DefaultAlipayClient`, `AlipayRequest`)
- Antom Web SDK loaded from CDN: `https://sdk.marmot-cloud.com/package/ams-checkout/...`
- PHP >=8.2, Magento >=2.4.6 (implied by framework version constraints)

### Config Structure

Admin config is defined in `etc/adminhtml/system.xml`. Credentials are stored per-environment (sandbox/live) and encrypted via Magento's `EncryptorInterface`. The `Gateway/Config.php` class auto-selects the correct credential set based on the environment setting.