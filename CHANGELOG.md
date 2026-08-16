# Changelog

## v0.4.0

- Attachments. `sendEmail()` and `sendTemplatedEmail()` take an
  `attachments:` array. Pass a path and the SDK reads, names and encodes the
  file for you, or pass `['filename' => …, 'content' => …]` when it only
  exists in memory. Never a URL: the bytes travel in the request, so Roviox
  never fetches anything from your side. At most 10 files and 15 MB decoded
  per email; an unreadable path throws `RovioxException` rather than sending
  a mail with a missing attachment.
- `sendInvoice()`, for the new `invoice` email type: the number, the amount
  and the due date in a small table, your PDF attached, and an optional
  payment link as the button. The amount is a number in major units and
  Roviox formats it for the language of the mail, with `currency:` (ISO 4217,
  default EUR). For the due date pass either `dueDate:` as your own string or
  `paymentTermDays: 14` and let Roviox count from today; both at once is
  refused.
- The README covers errors, queueing and testing, which it did not before.

Nothing here is breaking: the new arguments sit at the end of the existing
signatures.

## v0.3.0

- **Breaking:** `createCampaign()` takes `list` and `content` before the
  sender, and the sender is now optional: leave `fromName` and `from` out and
  the campaign uses the domain's newsletter sender. `fromLocalPart` is renamed
  to `from` (the part before the @, same as the transactional endpoint), and
  `send` to `sendNow`.

## v0.2.0

- **Breaking:** `ROVIOX_URL` and the `roviox.url` config key are gone. The
  package always talks to `https://api.roviox.app`, which is the only place
  Roviox runs. `RovioxClient::__construct()` lost its `$baseUrl` argument;
  the host now lives in `RovioxClient::BASE_URL`. Nothing to do unless you
  built the client by hand.
- Added a test suite (PHPUnit + Testbench) covering every facade method, the
  request payloads, and the error handling. Run it with `composer test`.

## v0.1.0

First release.

- `Roviox::sendEmail()` and `sendTemplatedEmail()` for transactional email.
- `Roviox::createTicket()` to push contact-form submissions to the support desk.
- `Roviox::lists()` and `upsertSubscriber()` for newsletter subscribers.
- `Roviox::createCampaign()`, `campaign()` and `sendCampaign()` for campaigns.
- Auto-discovered service provider and `Roviox` facade, config publishable with
  `--tag=roviox-config`.
- Configure with `ROVIOX_KEY`; `ROVIOX_URL` only to reach a development install.
- Errors throw `Roviox\RovioxException` carrying `status` and `errors`.
