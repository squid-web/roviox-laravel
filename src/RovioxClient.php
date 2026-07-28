<?php

namespace Roviox;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RovioxClient
{
    /** The Roviox API. There is one, so it is not configurable. */
    public const BASE_URL = 'https://api.roviox.app';

    public function __construct(
        protected ?string $apiKey,
        protected int $timeout = 15,
    ) {}

    /**
     * Send a transactional email.
     *
     * @param  string  $from  local part of the sender address (e.g. "noreply")
     * @return array{id: int, status: string, provider_message_id: ?string}
     */
    public function sendEmail(
        string $from,
        string $to,
        string $subject,
        ?string $html = null,
        ?string $text = null,
        ?string $fromName = null,
        ?string $replyTo = null,
        array $metadata = [],
    ): array {
        return $this->post('/v1/emails', array_filter([
            'from' => $from,
            'from_name' => $fromName,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'reply_to' => $replyTo,
            'metadata' => $metadata ?: null,
        ], fn ($value) => $value !== null));
    }

    /**
     * Send a standard transactional email (confirm_signup, two_factor_code,
     * password_reset, magic_login, welcome, email_change, notification) in
     * the domain's look-and-feel.
     *
     * @param  array<string, mixed>  $data  type variables, e.g. ['action_url' => '…', 'name' => 'Jan']
     * @param  string|null  $locale  e.g. 'nl', falls back to the domain default
     * @param  string|null  $theme  clean | bold | minimal, falls back to the domain setting
     */
    public function sendTemplatedEmail(
        string $type,
        string $to,
        array $data = [],
        ?string $locale = null,
        ?string $theme = null,
        ?string $from = null,
        ?string $fromName = null,
    ): array {
        return $this->post('/v1/emails/template', array_filter([
            'type' => $type,
            'to' => $to,
            'data' => $data ?: null,
            'locale' => $locale,
            'theme' => $theme,
            'from' => $from,
            'from_name' => $fromName,
        ], fn ($value) => $value !== null));
    }

    /**
     * Push contact-form content to the Roviox desk as a support ticket.
     *
     * @param  string  $form  the form's slug or public key
     * @param  array<string, mixed>  $fields  field values (keys must match the form definition)
     * @return array{ticket_number: string, status: string, is_spam: bool}
     */
    public function createTicket(string $form, array $fields): array
    {
        return $this->post('/v1/tickets', [
            'form' => $form,
            'fields' => $fields,
        ]);
    }

    /**
     * List the domain's subscriber lists.
     */
    public function lists(): array
    {
        return $this->get('/v1/lists')['lists'] ?? [];
    }

    /**
     * Create or update a subscriber and their custom data (e.g. mark a
     * user as premium, set a country). Custom fields are merged; pass
     * $list (slug or id) to also subscribe them to a list.
     *
     * @param  array<string, scalar>  $customFields
     */
    public function upsertSubscriber(
        string $email,
        ?string $name = null,
        ?string $locale = null,
        array $customFields = [],
        ?string $list = null,
    ): array {
        return $this->post('/v1/subscribers', array_filter([
            'email' => $email,
            'name' => $name,
            'locale' => $locale,
            'custom_fields' => $customFields ?: null,
            'list' => $list,
        ], fn ($value) => $value !== null))['subscriber'] ?? [];
    }

    /**
     * Create a newsletter campaign. Pass send: true to send (or schedule,
     * when scheduled_at is in the future) immediately.
     *
     * @param  string  $list  the subscriber list's slug or id
     */
    public function createCampaign(
        string $name,
        string $subject,
        string $fromName,
        string $fromLocalPart,
        string $list,
        string $content,
        ?int $templateId = null,
        ?string $scheduledAt = null,
        ?string $dynamicContentUrl = null,
        bool $send = false,
    ): array {
        return $this->post('/v1/campaigns', array_filter([
            'name' => $name,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_local_part' => $fromLocalPart,
            'list' => $list,
            'content' => $content,
            'template_id' => $templateId,
            'scheduled_at' => $scheduledAt,
            'dynamic_content_url' => $dynamicContentUrl,
            'send' => $send ?: null,
        ], fn ($value) => $value !== null))['campaign'] ?? [];
    }

    /**
     * Fetch a campaign with its delivery/open/click stats.
     */
    public function campaign(int $id): array
    {
        return $this->get("/v1/campaigns/{$id}")['campaign'] ?? [];
    }

    /**
     * Send a draft campaign now.
     */
    public function sendCampaign(int $id): array
    {
        return $this->post("/v1/campaigns/{$id}/send")['campaign'] ?? [];
    }

    // ------------------------------------------------------------------

    protected function get(string $path): array
    {
        return $this->handle($this->request()->get($this->url($path)));
    }

    protected function post(string $path, array $payload = []): array
    {
        return $this->handle($this->request()->post($this->url($path), $payload));
    }

    protected function request(): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) $this->apiKey])
            ->acceptJson()
            ->timeout($this->timeout);
    }

    protected function url(string $path): string
    {
        return self::BASE_URL.$path;
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw new RovioxException(
                $response->json('message') ?? "Roviox API error ({$response->status()})",
                $response->status(),
                $response->json('errors') ?? [],
            );
        }

        return $response->json() ?? [];
    }
}
