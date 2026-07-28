# Changelog

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
