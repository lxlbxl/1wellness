<?php

interface ChannelAdapterInterface
{
    /**
     * Send a message via this channel.
     *
     * @param string $to         Recipient email or phone
     * @param string $subject    Subject line (email) or template name (WhatsApp)
     * @param string $body       Rendered HTML/text body
     * @param array  $options    Channel-specific options (list_unsubscribe, from_name, …)
     * @return array{success: bool, provider_msg_id: string, error: string}
     */
    public function send(string $to, string $subject, string $body, array $options = []): array;
}
