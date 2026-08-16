# Roviox for Laravel

Laravel SDK for the [Roviox](https://roviox.app) API: send transactional email, push contact-form submissions to the support desk, and create newsletter campaigns from any Laravel application.

## Installation

```bash
composer require squidweb/roviox-laravel
```

Set your API key in `.env`. Create one in Roviox under *API keys*; the key is bound to one domain, so it decides what you send for.

```dotenv
ROVIOX_KEY=rx_xxxxxxxxxxxxxxxxxxxx
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

### Inbox

Not wrapped by the facade yet, but the endpoints are there and take the same
`X-Api-Key` header (a key with the `inbox` scope):

| Method | Path | What |
|---|---|---|
| GET | `/v1/mailboxes` | your inbox addresses, including which one is the catch-all |
| GET | `/v1/messages` | mail you received, newest first (`mailbox`, `direction`, `since_id`, `since`, `include_spam`, `per_page`) |
| GET | `/v1/messages/{id}` | one message with body, headers and attachment list |
| GET | `/v1/messages/{id}/attachments/{index}` | download one attachment; the message payload hands you this URL as `download_url` |
| DELETE | `/v1/messages/{id}` | delete a message for good |

A domain can also call your app when mail arrives (domain settings, tab
"Receiving mail"). That webhook carries identifiers only, never the mail: it
hands you a `message_id` to fetch through the API above. Verify the
`X-Roviox-Signature` header before trusting it; the API docs show how.

Errors throw `Roviox\RovioxException` with `status` and `errors` (validation) properties.

## Tests

```bash
composer install
composer test
```

The suite fakes the HTTP layer, so it never touches the API.

## Attachments

JSON has no bytes, so files go out base64 encoded. The SDK does that for you:
pass a path, or the contents if the file only exists in memory.

```php
Roviox::sendEmail(
    from: 'facturen',
    to: 'klant@example.com',
    subject: 'Je factuur',
    text: 'Zie bijlage.',
    attachments: [
        storage_path('app/invoices/2026-014.pdf'),                    // a path
        ['filename' => 'bon.pdf', 'content' => $pdfBytes],            // or the bytes
    ],
);
```

At most 10 files and 15 MB decoded per email. Roviox stores the filename, size
and type with the send log, never the file itself.

## Invoices

`sendInvoice()` is the invoice template with its PDF: the number, the amount
and the due date land in a small table, and a payment link becomes the button.

```php
Roviox::sendInvoice(
    to: 'klant@example.com',
    invoiceNumber: '2026-014',
    amount: 249.00,
    pdf: storage_path('app/invoices/2026-014.pdf'),
    paymentTermDays: 14,
    paymentUrl: $mollie->getCheckoutUrl(),
    locale: 'nl',
    extra: ['name' => $customer->name],
);
```

The amount is a number in major units and Roviox formats it for the language
of the mail: `€ 249,00` in Dutch, `€249.00` in English. Pass `currency:` for
anything other than EUR.

For the due date you either pass `dueDate:` as your own string, or
`paymentTermDays: 14` and Roviox counts the date from today and writes it in
the language of the mail. Passing both is refused, because that is a
contradiction rather than a default. Everything except the number and the amount is
optional, and the copy follows: no PDF means the mail no longer says one is
attached, no payment link means no button.

## Errors

Anything other than a 2xx throws a `Roviox\RovioxException`, carrying the HTTP
status and the validation errors from the response.

```php
use Roviox\RovioxException;

try {
    Roviox::sendEmail(from: 'noreply', to: $user->email, subject: '…', text: '…');
} catch (RovioxException $e) {
    $e->getMessage();  // "The to field must be a valid email address."
    $e->status;        // 422
    $e->errors;        // ['to' => ['The to field must be a valid email address.']]

    report($e);
}
```

What the statuses mean: **401** the key is wrong or revoked, **403** the key is
missing the scope for that endpoint (a permissions problem you fix in Roviox,
not in your code), **422** validation, **429** rate limited.

## Queueing

Calls are synchronous HTTP requests. Anything in a web request belongs in a job,
so a slow API call never becomes a slow page.

```php
dispatch(fn () => Roviox::sendTemplatedEmail('welcome', $user->email, ['name' => $user->name]));
```

## Testing

Fake the HTTP layer rather than the facade, so you also assert what was sent.

```php
use Illuminate\Support\Facades\Http;

Http::fake(['*/v1/emails' => Http::response(['id' => 1, 'status' => 'sent'])]);

// … run the code under test …

Http::assertSent(fn ($request) => $request['to'] === 'jan@example.com');
```

Leave `ROVIOX_KEY` empty in your test environment and every call throws right
away, which is a useful way to notice a code path that sends mail when it
should not.
