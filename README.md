# Roviox for Laravel

Laravel SDK for the [Roviox](https://roviox.app) API: send transactional email, push contact-form submissions to the support desk, and create newsletter campaigns from any Laravel application.

## Installation

```bash
composer require squidweb/roviox-laravel
```

Set your API key in `.env`. Create one in Roviox under *API keys*; the key is bound to one domain, so it decides what you send for.

```dotenv
ROVIOX_KEY=mb_xxxxxxxxxxxxxxxxxxxx
```

That is all you need. The package talks to `https://api.roviox.app`, there is
nothing else to point it at.

Optionally publish the config: `php artisan vendor:publish --tag=roviox-config`.

## Usage

```php
use Roviox\Facades\Roviox;

// Transactional email
Roviox::sendEmail(
    from: 'noreply',
    to: 'customer@example.com',
    subject: 'Your order has shipped',
    html: '<p>Track it here…</p>',
    metadata: ['order_id' => 1234],
);

// Templated transactional email in Roviox's look-and-feel.
// Types: confirm_signup, two_factor_code, password_reset, magic_login,
// welcome, email_change, notification. Locale: en/nl. Theme: clean/bold/minimal.
Roviox::sendTemplatedEmail(
    type: 'confirm_signup',
    to: 'customer@example.com',
    data: ['action_url' => 'https://app.example.com/confirm/abc123', 'name' => 'Jan'],
    locale: 'nl',
);

Roviox::sendTemplatedEmail('two_factor_code', 'customer@example.com', [
    'code' => '482913',
    'expires_minutes' => 10,
], locale: 'nl');

// Subscriber custom data (segments can target these fields)
Roviox::upsertSubscriber(
    email: 'user@example.com',
    name: 'Jan',
    customFields: ['premium' => true, 'country' => 'NL'],
    list: 'monthly',            // optional: also subscribe to this list
);

// Contact form → Roviox support desk (form = slug or public key)
Roviox::createTicket('contact', [
    'name' => $request->name,
    'email' => $request->email,
    'message' => $request->message,
]);

// Newsletter campaign
$campaign = Roviox::createCampaign(
    name: 'Weekly digest',           // internal, only you see this
    subject: 'This week at Acme',    // what readers see
    list: 'monthly',                 // slug or id, shown on the list's page
    content: '<h1>Hello {{name}}</h1>',
    sendNow: true,                   // or pass scheduledAt: '2026-08-01 09:00'
);

// The sender comes from your domain settings. Override it per campaign:
// fromName: 'Acme', from: 'news'

Roviox::campaign($campaign['id']);     // status + stats
Roviox::sendCampaign($campaign['id']); // send a draft
```

Errors throw `Roviox\RovioxException` with `status` and `errors` (validation) properties.

## Tests

```bash
composer install
composer test
```

The suite fakes the HTTP layer, so it never touches the API.
