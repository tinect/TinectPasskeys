# Passkeys for Shopware 6

Let your customers log in to the storefront with passkeys (WebAuthn / FIDO2) — using fingerprint, face recognition or
their device PIN instead of a password.
The plugin is compatible with Shopware 6.7 and PHP 8.2 and higher.

## ✔️ Features
- Passkey login on the storefront login page
- Passkey suggestions directly in the e-mail field (conditional UI), if the login field uses `autocomplete="username webauthn"`
- Manage passkeys in the customer profile: add (requires the current password), list with last usage, remove (with confirmation)
- Customers with at least one passkey can disable the password login for their account (storefront and Store API)
- Security notice by e-mail when a passkey was added, via the flow builder (flow "Passkey added", mail template type "Passkey added")
- Administration: list and remove passkeys of a customer in the customer detail page, enable the password login again
- `/.well-known/passkey-endpoints` for password managers
- User verification is always required, challenges are single-use and expire after 5 minutes
- Login rate limited via the Shopware `login` rate limiter

## ℹ️ Good to know
- Passkeys are bound to the domain they were created on. If your sales channels use different domains, customers need
  a passkey per domain.
- Other login checks still apply, e.g. a two-factor authentication plugin will still ask for its code after the passkey login.
- Passkeys are removed together with the customer account.
- A disabled password login answers like wrong credentials, so it does not reveal that the account exists. It is
  enabled again automatically when the customer resets the password via "Forgot password" or removes the last passkey.

## 🚀 How to install
### Composer install (recommended)
```
composer require tinect/passkeys
bin/console plugin:refresh
bin/console plugin:install --activate TinectPasskeys
```
#### Plugin update (composer)
```
composer update tinect/passkeys
bin/console plugin:update TinectPasskeys
```

### .zip install
1. Download the latest TinectPasskeys.zip from the [latest release](https://github.com/tinect/TinectPasskeys/releases/latest).
2. Upload the zip in the Shopware Administration
3. Install & Activate the plugin

#### Plugin update (zip)
1. Download the latest TinectPasskeys.zip from the [latest release](https://github.com/tinect/TinectPasskeys/releases/latest).
2. Upload the zip in the Shopware Administration
3. Update the plugin

## 👷‍ Contribution
Please help with code, love, shares, feedback and bug reporting.

## ⚖️ License
This plugin is licensed under the MIT licence.
