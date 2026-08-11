<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use Sylius\Component\Mailer\Sender\SenderInterface;

final class RecordingSender implements SenderInterface
{
    /**
     * @var list<string> 
     */
    public array $codes = [];

    /**
     * @var list<array<int, string|null>> 
     */
    public array $recipients = [];

    /**
     * @var list<array<string, mixed>> 
     */
    public array $data = [];

    /**
     * @param array<int, string|null> $recipients
     * @param array<string, mixed>    $data
     * @param array<int, string>      $attachments
     * @param array<int, string>      $replyTo
     */
    public function send(
        string $code,
        array $recipients,
        array $data = [],
        array $attachments = [],
        array $replyTo = [],
    ): void {
        $this->codes[] = $code;
        $this->recipients[] = $recipients;
        $this->data[] = $data;
    }
}
