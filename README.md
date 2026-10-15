# Billions Payments for BILLmanager

Official [Billions.works](https://billions.works/) payment module for BILLmanager 6. It uses the Billions Public API directly. This integration was developed with LLM assistance and is maintained in this repository. License: [MIT](LICENSE).

## Install the latest release

The packages are architecture-independent (PHP). Run the command for your host OS as root. You can also [browse all release files](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest).

Ubuntu 20.04 — [package](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-ubuntu20.deb):

```sh
curl -fL https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-ubuntu20.deb -o /tmp/billions-billmanager-ubuntu20.deb && apt install -y /tmp/billions-billmanager-ubuntu20.deb
```

Ubuntu 24.04 — [package](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-ubuntu24.deb):

```sh
curl -fL https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-ubuntu24.deb -o /tmp/billions-billmanager-ubuntu24.deb && apt install -y /tmp/billions-billmanager-ubuntu24.deb
```

Debian 12 — [package](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-debian12.deb):

```sh
curl -fL https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-debian12.deb -o /tmp/billions-billmanager-debian12.deb && apt install -y /tmp/billions-billmanager-debian12.deb
```

Debian 13 — [package](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-debian13.deb):

```sh
curl -fL https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-debian13.deb -o /tmp/billions-billmanager-debian13.deb && apt install -y /tmp/billions-billmanager-debian13.deb
```

AlmaLinux / Rocky Linux 9 and compatible RPM-based systems — [package](https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-el9.noarch.rpm):

```sh
dnf install -y https://github.com/billions-works/billions-payments-for-billmanager/releases/latest/download/billions-billmanager-el9.noarch.rpm
```

The Debian-family commands require `curl`. The host needs BILLmanager 6, PHP CLI 7.4 or newer with XML/SimpleXML and mbstring, a valid CA trust store, and outbound HTTPS access to `api.billions.works`. The incoming HTTPS callback path `/mancgi/billionsresult.php` must be publicly reachable. Restart BILLmanager after installing if the new payment method does not appear immediately.

## Configure

Create a Billions payment method in BILLmanager and fill in:

- **Public API key** — merchant API key issued by Billions. It stays server-side; the UI marks it private.
- **Unique BILLmanager instance ID** — 3–30 ASCII letters, digits, underscores or hyphens. Use a different ID for every BILLmanager installation sharing an API key, and never change it after the first payment. It forms part of the invoice's idempotency key.
- **Public HTTPS origin of BILLmanager** — for example `https://billing.example.com`, without a path or trailing slash. Billions sends payment notifications to `<origin>/mancgi/billionsresult.php?elid=<payment-id>`.
- **Billions API origin** — optional; leave empty for `https://api.billions.works`. Only change for an authorized test environment.

Enable the currencies supported by your Billions merchant account. USD and EUR can be invoice currencies; cryptocurrency acceptance is determined by the Billions checkout. Test one small payment before enabling this method for customers.

## Payment flow and security

The payment page creates an invoice with a stable `order_no` and `idempotencyKey` derived from instance, payment-method and BILLmanager payment IDs. Reopening the page reuses the stored Billions invoice. A notification is only a prompt to retrieve the invoice again from the authenticated Billions API. Before marking a BILLmanager payment paid, the module compares the Billions invoice ID, order number, amount and settlement currency with the original payment. It never trusts the notification body's status or paid amount. API traffic and callback URLs require HTTPS. No HMAC secret is needed for this flow.

`completed` marks the payment paid, `processing` marks it in progress, and `expired`/`failed` marks it unpaid. Subscriptions and refunds are not implemented. A customer return from checkout is not payment proof. If a callback cannot be verified, the endpoint returns an error so the payment system may retry it. Administrators should reconcile any missed notifications against the Billions dashboard.

The module does not collect additional customer data beyond the invoice details sent to Billions. See [Billions Privacy Policy](https://billions.works/privacy) and [Terms of Service](https://billions.works/terms).

## Build from source

Tagged releases trigger [GitHub Actions](.github/workflows/release.yml) to lint PHP, run the bridge tests, build four Debian-family packages and one EL9 RPM, and upload them to the release. Package filenames are stable so the latest-release links above remain valid. To build locally on a matching Linux system, run `sh packaging/build.sh ubuntu24 0.1.0` or another target (`ubuntu20`, `debian12`, `debian13`, `el9`). The packages install the scripts, XML form and library under `/usr/local/mgr5`.

Report issues at [GitHub Issues](https://github.com/billions-works/billions-payments-for-billmanager/issues).
