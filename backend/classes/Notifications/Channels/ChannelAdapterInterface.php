<?php

interface ChannelAdapterInterface
{
    /**
     * Send a rendered message.
     *
     * @param string      $to      Email address or phone number depending on channel
     * @param string      $subject Email subject (empty for WhatsApp/SMS)
     * @param string      $body    Rendered message body (HTML for email, plain for WA/SMS)
     * @param array       $meta    Extra: ['template_name'=>..., 'queue_id'=>..., 'journey_key'=>..., 'step'=>...]
     * @return array{ok:bool,provider_msg_id:string|null,error:string|null,cost_usd:float|null}
     */
    public function send(string $to, string $subject, string $body, array $meta = []): array;

    /** Human-readable channel name: 'email' | 'whatsapp' | 'sms' */
    public function channelName(): string;

    /** True when credentials are configured and the channel is enabled in settings. */
    public function isAvailable(): bool;
}
