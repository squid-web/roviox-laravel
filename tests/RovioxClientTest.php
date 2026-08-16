<?php

namespace Roviox\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Roviox\Facades\Roviox;
use Roviox\RovioxClient;
use Roviox\RovioxException;

class RovioxClientTest extends TestCase
{
    public function test_it_sends_a_transactional_email(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 7, 'status' => 'sent', 'provider_message_id' => 'abc']),
        ]);

        $result = Roviox::sendEmail(
            from: 'noreply',
            to: 'jan@example.com',
            subject: 'Hello',
            html: '<p>Hi</p>',
            fromName: 'Acme',
        );

        $this->assertSame(7, $result['id']);

        Http::assertSent(function (Request $request) {
            return $request->url() === RovioxClient::BASE_URL.'/v1/emails'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Api-Key', 'mb_testkey')
                && $request['from'] === 'noreply'
                && $request['from_name'] === 'Acme'
                && $request['html'] === '<p>Hi</p>'
                // Optional arguments that were not passed stay out of the body.
                && ! array_key_exists('text', $request->data())
                && ! array_key_exists('reply_to', $request->data());
        });
    }

    public function test_it_sends_a_templated_email(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'status' => 'sent'])]);

        Roviox::sendTemplatedEmail(
            type: 'password_reset',
            to: 'jan@example.com',
            data: ['action_url' => 'https://example.com/reset'],
            locale: 'nl',
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === RovioxClient::BASE_URL.'/v1/emails/template'
                && $request['type'] === 'password_reset'
                && $request['locale'] === 'nl'
                && $request['data']['action_url'] === 'https://example.com/reset'
                && ! array_key_exists('theme', $request->data());
        });
    }

    public function test_it_creates_a_ticket(): void
    {
        Http::fake(['*' => Http::response([
            'ticket_number' => 'T-1024', 'status' => 'open', 'is_spam' => false,
        ])]);

        $ticket = Roviox::createTicket('contact', [
            'name' => 'Jan', 'email' => 'jan@example.com', 'message' => 'Hoi',
        ]);

        $this->assertSame('T-1024', $ticket['ticket_number']);

        Http::assertSent(fn (Request $request) => $request->url() === RovioxClient::BASE_URL.'/v1/tickets'
            && $request['form'] === 'contact'
            && $request['fields']['message'] === 'Hoi');
    }

    public function test_it_lists_subscriber_lists_and_unwraps_the_envelope(): void
    {
        Http::fake(['*' => Http::response(['lists' => [
            ['id' => 1, 'name' => 'Monthly'],
        ]])]);

        $lists = Roviox::lists();

        $this->assertSame('Monthly', $lists[0]['name']);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === RovioxClient::BASE_URL.'/v1/lists');
    }

    public function test_it_upserts_a_subscriber_with_custom_fields(): void
    {
        Http::fake(['*' => Http::response(['subscriber' => ['id' => 3, 'email' => 'jan@example.com']])]);

        $subscriber = Roviox::upsertSubscriber(
            email: 'jan@example.com',
            name: 'Jan',
            customFields: ['plan' => 'premium'],
            list: 'monthly',
        );

        $this->assertSame(3, $subscriber['id']);

        Http::assertSent(fn (Request $request) => $request['custom_fields']['plan'] === 'premium'
            && $request['list'] === 'monthly'
            && ! array_key_exists('locale', $request->data()));
    }

    public function test_it_creates_a_campaign(): void
    {
        Http::fake(['*' => Http::response(['campaign' => ['id' => 12, 'status' => 'sending']])]);

        $campaign = Roviox::createCampaign(
            name: 'Weekly digest',
            subject: 'This week',
            list: 'monthly',
            content: '<h1>Hello</h1>',
            fromName: 'Acme',
            from: 'news',
            sendNow: true,
        );

        $this->assertSame(12, $campaign['id']);

        Http::assertSent(fn (Request $request) => $request['from'] === 'news'
            && $request['send_now'] === true
            // Not scheduled, so the key should be absent rather than null.
            && ! array_key_exists('scheduled_at', $request->data()));
    }

    public function test_send_now_is_omitted_when_the_campaign_stays_a_draft(): void
    {
        Http::fake(['*' => Http::response(['campaign' => ['id' => 13, 'status' => 'draft']])]);

        Roviox::createCampaign(
            name: 'Draft', subject: 'Later',
            list: 'monthly', content: '<p>x</p>',
        );

        Http::assertSent(fn (Request $request) => ! array_key_exists('send_now', $request->data())
            // No sender given, so none is sent: the domain decides.
            && ! array_key_exists('from', $request->data())
            && ! array_key_exists('from_name', $request->data()));
    }

    public function test_it_reads_and_sends_a_campaign_by_id(): void
    {
        Http::fake([
            '*/v1/campaigns/12' => Http::response(['campaign' => ['id' => 12, 'open_rate' => 41.5]]),
            '*/v1/campaigns/12/send' => Http::response(['campaign' => ['id' => 12, 'status' => 'sending']]),
        ]);

        $this->assertSame(41.5, Roviox::campaign(12)['open_rate']);
        $this->assertSame('sending', Roviox::sendCampaign(12)['status']);
    }

    public function test_a_validation_error_becomes_an_exception_carrying_the_details(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['to' => ['The to field must be a valid email address.']],
        ], 422)]);

        try {
            Roviox::sendEmail(from: 'noreply', to: 'not-an-email', subject: 'Hi');
            $this->fail('Expected a RovioxException.');
        } catch (RovioxException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame('The given data was invalid.', $exception->getMessage());
            $this->assertArrayHasKey('to', $exception->errors);
        }
    }

    public function test_an_error_without_a_message_still_reports_the_status(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(RovioxException::class);
        $this->expectExceptionMessage('Roviox API error (500)');

        Roviox::lists();
    }

    public function test_the_api_host_is_fixed(): void
    {
        Http::fake(['*' => Http::response(['lists' => []])]);

        Roviox::lists();

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.roviox.app/'));
    }

    public function test_it_encodes_an_attachment_from_a_path(): void
    {
        Http::fake(['*' => Http::response(['id' => 8, 'status' => 'sent'])]);

        $path = tempnam(sys_get_temp_dir(), 'roviox').'.txt';
        file_put_contents($path, 'hallo wereld');

        Roviox::sendEmail(
            from: 'noreply',
            to: 'jan@example.com',
            subject: 'Met bijlage',
            text: 'Zie bijlage.',
            attachments: [$path],
        );

        Http::assertSent(function (Request $request) use ($path) {
            $file = $request['attachments'][0];

            // JSON has no bytes, so the SDK reads and encodes the file itself.
            return $file['filename'] === basename($path)
                && base64_decode($file['content']) === 'hallo wereld';
        });

        unlink($path);
    }

    public function test_it_takes_attachment_contents_without_a_path(): void
    {
        Http::fake(['*' => Http::response(['id' => 9, 'status' => 'sent'])]);

        Roviox::sendEmail(
            from: 'noreply', to: 'jan@example.com', subject: 'Hi', text: 'Hallo',
            attachments: [['filename' => 'in-memory.pdf', 'content' => '%PDF-1.4', 'content_type' => 'application/pdf']],
        );

        Http::assertSent(function (Request $request) {
            $file = $request['attachments'][0];

            return $file['filename'] === 'in-memory.pdf'
                && $file['content_type'] === 'application/pdf'
                && base64_decode($file['content']) === '%PDF-1.4';
        });
    }

    public function test_an_unreadable_attachment_throws_instead_of_sending_nothing(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $this->expectException(RovioxException::class);

        Roviox::sendEmail(
            from: 'noreply', to: 'jan@example.com', subject: 'Hi', text: 'Hallo',
            attachments: ['/does/not/exist.pdf'],
        );
    }

    public function test_it_sends_an_invoice_with_its_pdf(): void
    {
        Http::fake(['*' => Http::response(['id' => 10, 'status' => 'sent'])]);

        $path = tempnam(sys_get_temp_dir(), 'invoice').'.pdf';
        file_put_contents($path, '%PDF-1.4 factuur');

        Roviox::sendInvoice(
            to: 'klant@example.com',
            invoiceNumber: '2026-014',
            amount: 249.00,
            pdf: $path,
            paymentUrl: 'https://pay.example.com/2026-014',
            locale: 'nl',
            paymentTermDays: 14,
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === RovioxClient::BASE_URL.'/v1/emails/template'
                && $request['type'] === 'invoice'
                && $request['locale'] === 'nl'
                // A number and a currency, not a written out amount: Roviox
                // formats it for the language of the mail.
                && $request['data']['amount'] === 249.0
                && $request['data']['currency'] === 'EUR'
                && $request['data']['payment_term_days'] === 14
                && $request['data']['invoice_number'] === '2026-014'
                && ! array_key_exists('due_date', $request['data'])
                && base64_decode($request['attachments'][0]['content']) === '%PDF-1.4 factuur';
        });

        unlink($path);
    }

    public function test_an_invoice_without_a_pdf_sends_no_attachments_block(): void
    {
        Http::fake(['*' => Http::response(['id' => 11, 'status' => 'sent'])]);

        Roviox::sendInvoice(to: 'k@example.com', invoiceNumber: 'X', amount: 40, currency: 'USD');

        Http::assertSent(function (Request $request) {
            return $request['data']['currency'] === 'USD'
                && ! array_key_exists('attachments', $request->data());
        });
    }
}
