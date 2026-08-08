# Pristine Dev

A Symfony 8.1 mentoring platform where learners create an account, transfer a one-time payment with PayPal, and book a private web development lesson after manual payment approval.

## Stack

- PHP 8.4, Symfony 8.1, Doctrine ORM and SQLite
- Symfony UX Live Components, Stimulus, Turbo and AssetMapper
- Tailwind CSS 4 and Flowbite
- Direct PayPal payment link with manual transaction-ID approval
- PHPUnit functional/unit tests and PHPStan level 10

## Local setup

```bash
composer install
bin/console importmap:install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console tailwind:build
symfony server:start

bin/console asset-map:compile // only prod
```

Create a PayPal.Me link (or another PayPal payment link) for your receiving account and put the full €89 URL in `.env.local`:

```dotenv
PAYPAL_PAYMENT_URL=https://www.paypal.com/paypalme/yourname/89EUR
MAILER_DSN=smtp://username:password@smtp.example.com:587
MAILER_FROM_EMAIL=hello@your-domain.example
```

The learner opens that link, transfers €89, and submits the transaction ID from their PayPal receipt. The application does not call the PayPal API and cannot verify a transfer automatically. Visit `/admin/payments`, match the submitted transaction ID and amount against your PayPal activity, and approve it. Approval grants exactly one lesson credit; booking consumes that credit.

Registration sends a signed account-confirmation link through Symfony Mailer. The link expires after 24 hours, and password login is blocked until the email is confirmed. For local development, the default mail transport expects a mail catcher such as Mailpit on `127.0.0.1:1025`. Production must provide a real `MAILER_DSN` and verified sender address through hosting environment variables or Symfony secrets.

### Approving lesson proposals

After confirming your own mentor account, grant it administrator access:

```bash
php bin/console app:make-admin your-email@example.com
```

Learners spend one paid lesson credit to propose a time. Visit `/admin/lesson-proposals` to approve it; only approved lessons appear as scheduled in the learner dashboard. Declining a proposal returns the learner's lesson credit.

There is no sandbox, REST app, API key, webhook, or recurring agreement in this flow. Production only needs the real receiving account’s `PAYPAL_PAYMENT_URL`.

Any recurring agreements created before this pay-as-you-go flow are not canceled by a code deployment. Cancel those separately in the relevant payment-provider dashboard before going live so they cannot renew.

## Quality checks

```bash
composer test
composer analyse
composer cs-check
composer lint
composer quality
```

## Logging

Monolog uses environment-specific handlers:

- Development application logs rotate in `var/log/dev-YYYY-MM-DD.log`.
- Development deprecations rotate in `var/log/dev.deprecations-YYYY-MM-DD.log`.
- Production application and deprecation logs are emitted as JSON to `stderr` for collection by the hosting platform.

Production buffers normal application messages and flushes them when an error occurs.
