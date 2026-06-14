<?php

require_once __DIR__ . '/ChannelAdapterInterface.php';
require_once __DIR__ . '/Mailer.php';

class EmailChannel implements ChannelAdapterInterface
{
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        try {
            $mailer = new Mailer();

            // List-Unsubscribe improves deliverability and is required for bulk sends
            if (!empty($options['list_unsubscribe'])) {
                $mailer->addHeader('List-Unsubscribe', '<' . $options['list_unsubscribe'] . '>');
                $mailer->addHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }

            $ok = $mailer->send($to, $subject, $body, true);

            if (!$ok) {
                return [
                    'success'         => false,
                    'provider_msg_id' => '',
                    'error'           => $mailer->getLastError() ?: 'send returned false',
                ];
            }

            return [
                'success'         => true,
                'provider_msg_id' => 'email_' . uniqid(),
                'error'           => '',
            ];
        } catch (Exception $e) {
            return [
                'success'         => false,
                'provider_msg_id' => '',
                'error'           => $e->getMessage(),
            ];
        }
    }
}
