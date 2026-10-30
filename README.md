# Passkeys for Shopware 6

Let your customers log in to the storefront with passkeys (WebAuthn / FIDO2) — using fingerprint, face recognition or
their device PIN instead of a password.
The plugin is compatible with Shopware 6.7 and PHP 8.2 and higher.

## ✔️ Features
- Passkey login on the storefront login page
- Passkey suggestions directly in the e-mail field (conditional UI), if the login field uses `autocomplete="username webauthn"`
- Manage passkeys in the customer profile: add (requires the current password), list with last usage, remove (with confirmation),
  up to 20 passkeys per customer
- Customers with at least one passkey can disable the password login for their account (storefront and Store API);
  disabling and enabling it has to be confirmed with one of their passkeys. While it is disabled, adding and removing
  passkeys has to be confirmed with an existing passkey instead of the password and the e-mail address can not be changed
  (otherwise e-mail change + "Forgot password" would bypass it)
- Security notices by e-mail via the flow builder, each with its own flow event, flow and mail template type:
  - a passkey was added (`tinect_passkeys.passkey.added`), with the time, the browser and operating system and the
    shortened IP address (IPv4 without the last octet, IPv6 as /48 prefix). IP address and device are not stored in the
    event log. Mention this security notice in your privacy policy.
  - the password login was disabled (`tinect_passkeys.password_login.disabled`)
  - the password login was enabled again (`tinect_passkeys.password_login.enabled`), with the reason
    `customer`, `recovery`, `lastPasskeyRemoved` or `administration`
- Administration: list and remove passkeys of a customer in the customer detail page, enable the password login again
- `/.well-known/passkey-endpoints` for password managers
- User verification is always required, challenges are single-use and expire after 5 minutes
- Login rate limited via the Shopware `login` rate limiter

## ℹ️ Good to know
- Passkeys are bound to the exact host they were created on, e.g. a passkey created on `www.example.com` does not work on
  `example.com`. If your sales channels use several domains, customers need a passkey per domain.
- Behind a TLS terminating proxy or load balancer, configure `TRUSTED_PROXIES`, so Shopware sees the `https` origin.
  Otherwise the passkey verification fails.
- Other login checks still apply, e.g. a two-factor authentication plugin will still ask for its code after the passkey login.
- Passkeys are removed together with the customer account.
- A disabled password login answers like wrong credentials, so it does not reveal that the account exists. It is
  enabled again automatically when the customer resets the password via "Forgot password" or removes the last passkey.
  So a disabled password login protects against a stolen password, but not against access to the customer's mailbox.
- The password login can only be disabled for the storefront and Store API login routes. There is no passkey login
  via the Store API yet, so customers with a disabled password login can not log in to headless frontends or apps.
- Deactivating or uninstalling the plugin enables the password login again for all customers, without notifying them.
  The setting is kept (on uninstall only when keeping the user data) and applies again once the plugin is activated.

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
