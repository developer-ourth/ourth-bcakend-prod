<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One recipient of a marketing broadcast. Queued per recipient so a large
 * segment does not time out the HTTP request, and failures don't stop the rest.
 */
class SendWhatsAppBroadcastMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $phone,
        public string $name,
        public string $message,
        public string $mediaType = 'none',
        public ?string $mediaUrl = null,
        public string $ctaType = 'none',
        public ?string $templateName = null,
        public string $templateLanguage = 'en',
    ) {}

    public function handle(WhatsAppService $wa): void
    {
        // Templates are required outside the 24-hour customer service window.
        $sent = $this->templateName
            ? $wa->sendTemplate($this->phone, $this->templateName, $this->templateLanguage, [$this->name])
            : $wa->sendRichMediaMessage(
                $this->phone,
                str_replace(['{name}', '{business_name}', '{phone}'], [$this->name, $this->name, $this->phone], $this->message),
                $this->mediaType,
                $this->mediaUrl,
                $this->ctaType,
            );

        if (!$sent) {
            Log::warning("WhatsApp broadcast not delivered to {$this->phone}.");
        }
    }
}
