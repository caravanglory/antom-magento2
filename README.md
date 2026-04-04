# Antom Payment Integration for Magento 2

Accept **Credit/Debit Cards**, **Google Pay**, and **Apple Pay** in your Magento 2 store via [Antom](https://www.antom.com) (Ant International).

## Features

- **Integrated Checkout**: Seamless payment experience within your Magento store.
- **Multiple Payment Methods**: Supports major credit cards, Google Pay, and Apple Pay.
- **Secure**: Uses Antom's secure API and follows Magento's security best practices (CSP compliant).
- **Easy Configuration**: Simple setup through the Magento Admin Panel.
- **Order Management**: Supports standard Magento order workflow (Inquiry, Capture, Refund, Cancel).

## Installation

```bash
composer require caravanglory/module-antom
bin/magento module:enable CaravanGlory_Antom
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

## Configuration

1. Log in to your Magento Admin Panel.
2. Navigate to **Stores** > **Settings** > **Configuration**.
3. In the left panel, expand **Sales** and select **Payment Methods**.
4. Find **Antom Payment** and configure the following:
   - **Enabled**: Set to Yes to activate the module.
   - **Environment**: Choose between Sandbox and Live.
   - **Credentials**: Enter your Client ID, Merchant Private Key, and Alipay Public Key.
   - **Google Pay/Apple Pay**: Enable and configure specific settings for these methods.

## Support

For any issues or questions, please contact [CaravanGlory](mailto:info@caravanglory.com).

## License

MIT License. See [LICENSE.txt](LICENSE.txt) for details.