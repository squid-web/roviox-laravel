# Changelog

## Unreleased

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
