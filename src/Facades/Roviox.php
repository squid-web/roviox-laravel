<?php

namespace Roviox\Facades;

use Illuminate\Support\Facades\Facade;
use Roviox\RovioxClient;

/**
 * @method static array sendEmail(string $from, string $to, string $subject, ?string $html = null, ?string $text = null, ?string $fromName = null, ?string $replyTo = null, array $metadata = [])
 * @method static array sendTemplatedEmail(string $type, string $to, array $data = [], ?string $locale = null, ?string $theme = null, ?string $from = null, ?string $fromName = null)
 * @method static array createTicket(string $form, array $fields)
 * @method static array lists()
 * @method static array upsertSubscriber(string $email, ?string $name = null, ?string $locale = null, array $customFields = [], ?string $list = null)
 * @method static array createCampaign(string $name, string $subject, string $list, string $content, ?string $fromName = null, ?string $from = null, ?int $templateId = null, ?string $scheduledAt = null, ?string $dynamicContentUrl = null, bool $sendNow = false)
 * @method static array campaign(int $id)
 * @method static array sendCampaign(int $id)
 *
 * @see RovioxClient
 */
class Roviox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RovioxClient::class;
    }
}
