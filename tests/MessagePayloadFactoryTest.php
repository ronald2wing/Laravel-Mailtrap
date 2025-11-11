<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ronald2Wing\LaravelMailtrap\MessagePayloadFactory;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MessagePayloadFactoryTest extends TestCase
{
    use BuildsTestEmails;

    private function factory(): MessagePayloadFactory
    {
        return new MessagePayloadFactory;
    }

    private function defaultEnvelope(): Envelope
    {
        return new Envelope(
            new Address(self::SENDER_EMAIL, self::SENDER_NAME),
            [new Address(self::RECIPIENT_EMAIL)],
        );
    }

    private function envelopeFor(Address $sender, Address ...$recipients): Envelope
    {
        return new Envelope($sender, $recipients);
    }

    #[Test]
    public function builds_basic_payload(): void
    {
        $email = $this->basicEmail()->subject('Hello')->text('Hello world');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('Hello world', $payload['text']);
        $this->assertSame(self::SENDER_EMAIL, $payload['from']['email']);
        $this->assertSame(self::SENDER_NAME, $payload['from']['name']);
        $this->assertSame(self::RECIPIENT_EMAIL, $payload['to'][0]['email']);
    }

    #[Test]
    public function builds_html_only_payload(): void
    {
        $email = $this->basicEmail()->subject('HTML')->html('<h1>Hello</h1>');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('<h1>Hello</h1>', $payload['html']);
        $this->assertArrayNotHasKey('text', $payload);
    }

    #[Test]
    public function builds_text_only_payload(): void
    {
        $email = $this->basicEmail()->subject('Text')->text('Plain text');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('Plain text', $payload['text']);
        $this->assertArrayNotHasKey('html', $payload);
    }

    #[Test]
    public function builds_multipart_payload(): void
    {
        $email = $this->basicEmail()
            ->subject('Multipart')
            ->text('Text version')
            ->html('<p>HTML version</p>');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('Text version', $payload['text']);
        $this->assertSame('<p>HTML version</p>', $payload['html']);
    }

    #[Test]
    public function includes_cc_recipients(): void
    {
        $email = $this->basicEmail()
            ->cc(new Address('cc@example.com', 'CC Person'))
            ->subject('CC')
            ->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame([
            ['email' => 'cc@example.com', 'name' => 'CC Person'],
        ], $payload['cc']);
    }

    #[Test]
    public function includes_bcc_recipients(): void
    {
        $email = $this->basicEmail()
            ->bcc(new Address('bcc@example.com', 'BCC Person'))
            ->subject('BCC')
            ->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame([
            ['email' => 'bcc@example.com', 'name' => 'BCC Person'],
        ], $payload['bcc']);
    }

    #[Test]
    public function includes_multiple_recipient_types(): void
    {
        $email = $this->basicEmail()
            ->to(
                new Address('one@example.com', 'One'),
                new Address('two@example.com', 'Two'),
            )
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Multiple')
            ->text('Body');
        $envelope = $this->envelopeFor(
            new Address(self::SENDER_EMAIL, self::SENDER_NAME),
            new Address(self::RECIPIENT_EMAIL),
            new Address('one@example.com', 'One'),
            new Address('two@example.com', 'Two'),
        );
        $payload = $this->factory()->build($email, $envelope);

        $this->assertCount(3, $payload['to']);
        $this->assertCount(1, $payload['cc']);
        $this->assertCount(1, $payload['bcc']);
    }

    #[Test]
    public function includes_attachment(): void
    {
        $content = 'File content';
        $email = $this->basicEmail()
            ->subject('With attachment')
            ->text('Body')
            ->attach($content, 'report.txt', 'text/plain');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame([
            [
                'content' => base64_encode($content),
                'type' => 'text/plain',
                'filename' => 'report.txt',
                'disposition' => 'attachment',
            ],
        ], $payload['attachments']);
    }

    #[Test]
    public function includes_multiple_attachments(): void
    {
        $email = $this->basicEmail()
            ->subject('Multiple')
            ->text('Body')
            ->attach('Content 1', 'a.txt', 'text/plain')
            ->attach('Content 2', 'b.pdf', 'application/pdf');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertCount(2, $payload['attachments']);
        $this->assertSame('a.txt', $payload['attachments'][0]['filename']);
        $this->assertSame('b.pdf', $payload['attachments'][1]['filename']);
    }

    #[Test]
    public function includes_inline_attachment(): void
    {
        $content = 'fake image bytes';
        $email = $this->basicEmail()
            ->subject('With inline')
            ->html('<img src="cid:logo">')
            ->embed($content, 'logo', 'image/png');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame([
            [
                'content' => base64_encode($content),
                'type' => 'image/png',
                'filename' => 'logo',
                'disposition' => 'inline',
            ],
        ], $payload['attachments']);
    }

    #[Test]
    public function includes_mixed_attachment_types(): void
    {
        $email = $this->basicEmail()
            ->subject('Mixed')
            ->html('<img src="cid:logo">')
            ->attach('Regular', 'doc.pdf', 'application/pdf')
            ->embed('Inline', 'logo', 'image/png');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertCount(2, $payload['attachments']);

        $regular = array_values(array_filter($payload['attachments'], fn ($a) => $a['disposition'] === 'attachment'));
        $inline = array_values(array_filter($payload['attachments'], fn ($a) => $a['disposition'] === 'inline'));
        $this->assertCount(1, $regular);
        $this->assertCount(1, $inline);
    }

    #[Test]
    public function no_attachments_key_when_empty(): void
    {
        $email = $this->basicEmail()->subject('No attachments')->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertArrayNotHasKey('attachments', $payload);
    }

    #[Test]
    public function category_header_is_excluded_from_custom_headers(): void
    {
        $email = $this->basicEmail()->subject('Cat')->text('Body');
        $email->getHeaders()->addTextHeader('X-Mailtrap-Category', 'notifications');
        $email->getHeaders()->addTextHeader('X-Custom', 'custom-value');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('notifications', $payload['category']);
        $this->assertArrayHasKey('headers', $payload);
        $this->assertArrayNotHasKey('X-Mailtrap-Category', $payload['headers']);
        $this->assertSame('custom-value', $payload['headers']['X-Custom']);
    }

    #[Test]
    public function category_header_does_not_mutate_email(): void
    {
        $email = $this->basicEmail()->subject('Cat')->text('Body');
        $email->getHeaders()->addTextHeader('X-Mailtrap-Category', 'notifications');
        $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertTrue($email->getHeaders()->has('X-Mailtrap-Category'));
    }

    #[Test]
    public function template_uuid_excluded_from_custom_headers(): void
    {
        $email = $this->basicEmail()->subject('Tpl')->text('Body');
        $email->getHeaders()->addTextHeader('X-Mailtrap-Template-Uuid', 'abc-123');
        $email->getHeaders()->addTextHeader('X-Custom', 'x');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('abc-123', $payload['template_uuid']);
        $this->assertArrayNotHasKey('X-Mailtrap-Template-Uuid', $payload['headers'] ?? []);
    }

    #[Test]
    public function template_variables_excluded_from_custom_headers(): void
    {
        $email = $this->basicEmail()->subject('Vars')->text('Body');
        $email->getHeaders()->addTextHeader(
            'X-Mailtrap-Template-Variables',
            '{"key":"val"}',
        );
        $email->getHeaders()->addTextHeader('X-Other', 'other');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame(['key' => 'val'], $payload['template_variables']);
        $this->assertArrayNotHasKey('X-Mailtrap-Template-Variables', $payload['headers'] ?? []);
    }

    #[Test]
    public function custom_variables_excluded_from_custom_headers(): void
    {
        $email = $this->basicEmail()->subject('CVars')->text('Body');
        $email->getHeaders()->addTextHeader(
            'X-Mailtrap-Custom-Variables',
            '{"key":"val"}',
        );
        $email->getHeaders()->addTextHeader('X-Other', 'other');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame(['key' => 'val'], $payload['custom_variables']);
        $this->assertArrayNotHasKey('X-Mailtrap-Custom-Variables', $payload['headers'] ?? []);
    }

    #[Test]
    public function includes_single_reply_to(): void
    {
        $email = $this->basicEmail()
            ->replyTo(new Address('reply@example.com', 'Reply Handler'))
            ->subject('Reply-To')
            ->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertStringContainsString('reply@example.com', $payload['headers']['Reply-To']);
    }

    #[Test]
    public function joins_multiple_reply_to_addresses(): void
    {
        $email = $this->basicEmail()
            ->replyTo(
                new Address('reply1@example.com', 'First'),
                new Address('reply2@example.com', 'Second'),
            )
            ->subject('Multi Reply-To')
            ->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertStringContainsString('reply1@example.com', $payload['headers']['Reply-To']);
        $this->assertStringContainsString('reply2@example.com', $payload['headers']['Reply-To']);
        $this->assertStringContainsString(', ', $payload['headers']['Reply-To']);
    }

    #[Test]
    public function reply_to_merged_with_custom_headers(): void
    {
        $email = $this->basicEmail()
            ->replyTo(new Address('reply@example.com'))
            ->subject('Merged')
            ->text('Body');
        $email->getHeaders()->addTextHeader('X-Custom', 'custom-value');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertArrayHasKey('Reply-To', $payload['headers']);
        $this->assertSame('custom-value', $payload['headers']['X-Custom']);
    }

    #[Test]
    public function includes_custom_headers(): void
    {
        $email = $this->basicEmail()->subject('Headers')->text('Body');
        $email->getHeaders()->addTextHeader('X-Custom', 'custom-value');
        $email->getHeaders()->addTextHeader('X-Priority', '1');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertSame('custom-value', $payload['headers']['X-Custom']);
        $this->assertSame('1', $payload['headers']['X-Priority']);
    }

    #[Test]
    public function excludes_standard_headers(): void
    {
        $email = $this->basicEmail()->subject('Standard')->text('Body');
        $email->getHeaders()->addTextHeader('X-Custom', 'kept');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertArrayNotHasKey('Subject', $payload['headers']);
        $this->assertArrayNotHasKey('From', $payload['headers']);
        $this->assertArrayNotHasKey('To', $payload['headers']);
        $this->assertSame('kept', $payload['headers']['X-Custom']);
    }

    #[Test]
    public function no_headers_key_when_none(): void
    {
        $email = $this->basicEmail()->subject('No headers')->text('Body');
        $payload = $this->factory()->build($email, $this->defaultEnvelope());

        $this->assertArrayNotHasKey('headers', $payload);
    }

    #[Test]
    public function sender_without_display_name(): void
    {
        $email = (new Email)
            ->from(self::SENDER_EMAIL)
            ->to(self::RECIPIENT_EMAIL)
            ->subject('No name')
            ->text('Body');
        $envelope = $this->envelopeFor(
            new Address(self::SENDER_EMAIL),
            new Address(self::RECIPIENT_EMAIL),
        );
        $payload = $this->factory()->build($email, $envelope);

        $this->assertSame(['email' => self::SENDER_EMAIL], $payload['from']);
        $this->assertArrayNotHasKey('name', $payload['from']);
    }

    #[Test]
    public function handles_utf8_content(): void
    {
        $subject = 'Testing UTF-8: 你好 & "quotes" €£¥';
        $text = 'Email body: 日本語 Español Français 中文';
        $email = (new Email)
            ->from(new Address(self::SENDER_EMAIL, 'José García'))
            ->to(new Address(self::RECIPIENT_EMAIL, 'François Müller'))
            ->subject($subject)
            ->text($text);
        $envelope = $this->envelopeFor(
            new Address(self::SENDER_EMAIL, 'José García'),
            new Address(self::RECIPIENT_EMAIL, 'François Müller'),
        );
        $payload = $this->factory()->build($email, $envelope);

        $this->assertSame($subject, $payload['subject']);
        $this->assertSame($text, $payload['text']);
        $this->assertSame('José García', $payload['from']['name']);
    }

    #[Test]
    public function handles_to_with_multiple_recipients_from_envelope(): void
    {
        $envelope = $this->envelopeFor(
            new Address(self::SENDER_EMAIL),
            new Address('a@example.com', 'A'),
            new Address('b@example.com', 'B'),
            new Address('c@example.com'),
        );
        $email = $this->basicEmail()->subject('Multi to')->text('Body');
        $payload = $this->factory()->build($email, $envelope);

        $this->assertCount(3, $payload['to']);
        $this->assertSame(['email' => 'a@example.com'], $payload['to'][0]);
        $this->assertSame(['email' => 'c@example.com'], $payload['to'][2]);
    }
}
