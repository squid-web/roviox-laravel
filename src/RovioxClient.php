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
     * @param  array<int, string|array<string, string>>  $attachments  paths, or ['filename' => …, 'content' => …]
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
        array $attachments = [],
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
            'attachments' => $this->encodeAttachments($attachments),
        ], fn ($value) => $value !== null));
    }

    /**
     * Send a standard transactional email (confirm_signup, two_factor_code,
     * password_reset, magic_login, welcome, email_change, notification, invoice) in
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
        array $attachments = [],
    ): array {
        return $this->post('/v1/emails/template', array_filter([
            'type' => $type,
            'to' => $to,
            'data' => $data ?: null,
            'locale' => $locale,
            'theme' => $theme,
            'from' => $from,
            'from_name' => $fromName,
            'attachments' => $this->encodeAttachments($attachments),
        ], fn ($value) => $value !== null));
    }

    /**
     * Turns file paths or raw contents into the shape the API expects.
     * JSON has no bytes, so everything goes out base64 encoded.
     *
     * Accepts, per entry:
     *   'invoice.pdf'                                  a path on disk
     *   ['filename' => 'x.pdf', 'path' => '/tmp/x.pdf']
     *   ['filename' => 'x.pdf', 'content' => $bytes, 'content_type' => 'application/pdf']
     *
     * @param  array<int, string|array<string, string>>  $attachments
     * @return array<int, array<string, string>>|null
     */
    protected function encodeAttachments(array $attachments): ?array
    {
        if ($attachments === []) {
            return null;
        }

        return array_values(array_map(function ($attachment) {
            if (is_string($attachment)) {
                $attachment = ['path' => $attachment];
            }

            if (isset($attachment['path'])) {
                if (! is_readable($attachment['path'])) {
                    throw new RovioxException("Attachment not readable: {$attachment['path']}");
                }

                $attachment['content'] ??= file_get_contents($attachment['path']);
                $attachment['filename'] ??= basename($attachment['path']);
                $attachment['content_type'] ??= mime_content_type($attachment['path']) ?: null;
            }

            return array_filter([
                'filename' => $attachment['filename'] ?? 'attachment',
                'content' => base64_encode($attachment['content'] ?? ''),
                'content_type' => $attachment['content_type'] ?? null,
            ], fn ($value) => $value !== null);
        }, $attachments));
    }

    /**
     * Send an invoice: the number, the amount and the PDF, plus an optional
     * payment link that becomes the button in the mail.
     *
     * The amount is a number in major units (249 or 249.50) and Roviox
     * formats it for the language of the mail, so "€ 249,00" in Dutch and
     * "€249.00" in English.
     *
     * @param  int|float  $amount  in major units, e.g. 249 or 249.50
     * @param  string  $currency  ISO 4217, e.g. EUR or USD
     * @param  string|array<string, string>  $pdf  a path on your own disk, or
     *                                              ['filename' => …, 'content' => …]. Never a URL:
     *                                              the SDK reads and encodes the file itself.
     * @param  int|null  $paymentTermDays  instead of $dueDate: Roviox counts the date from today
     * @param  array<string, mixed>  $extra  name, note, subject, …
     */
    public function sendInvoice(
        string $to,
        string $invoiceNumber,
        int|float $amount,
        string|array|null $pdf = null,
        ?string $dueDate = null,
        ?string $paymentUrl = null,
        ?string $locale = null,
        string $currency = 'EUR',
        ?int $paymentTermDays = null,
        array $extra = [],
    ): array {
        return $this->sendTemplatedEmail(
            type: 'invoice',
            to: $to,
            data: array_filter([
                ...$extra,
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'currency' => $currency,
                'due_date' => $dueDate,
                'payment_term_days' => $paymentTermDays,
                'payment_url' => $paymentUrl,
            ], fn ($value) => $value !== null),
            locale: $locale,
            attachments: $pdf === null ? [] : [$pdf],
        );
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
     * Create a newsletter campaign. Pass sendNow: true to send (or schedule,
     * when scheduledAt is in the future) immediately.
     *
     * @param  string  $list  the subscriber list's slug or id, shown on the
     *                        list's page in Roviox and returned by lists()
     * @param  string|null  $from  part before the @, e.g. "news"; both sender
     *                             fields fall back to the domain's setting
     */
    public function createCampaign(
        string $name,
        string $subject,
        string $list,
        string $content,
        ?string $fromName = null,
        ?string $from = null,
        ?int $templateId = null,
        ?string $scheduledAt = null,
        ?string $dynamicContentUrl = null,
        bool $sendNow = false,
    ): array {
        return $this->post('/v1/campaigns', array_filter([
            'name' => $name,
            'subject' => $subject,
            'list' => $list,
            'content' => $content,
            'from_name' => $fromName,
            'from' => $from,
            'template_id' => $templateId,
            'scheduled_at' => $scheduledAt,
            'dynamic_content_url' => $dynamicContentUrl,
            'send_now' => $sendNow ?: null,
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
