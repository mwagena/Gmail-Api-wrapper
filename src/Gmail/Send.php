<?php

namespace MartijnWagena\Gmail;

use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Swift_Attachment;
use Swift_Message;

class Send extends Gmail
{
    protected $service;

    /**
     * Send constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->service = new Google_Service_Gmail($this->client);
    }

    /**
     * @param array $to
     * @param array $cc
     * @param array $bcc
     * @param null $subject
     * @param null $message_body
     * @param null $in_reply_to
     * @param array $files
     * @return array
     * @internal param null $parent
     */
    public function sendMail($to = [], $cc = [], $bcc = [], $subject = null, $message_body = null, $in_reply_to = null, $files = [])
    {
        if($this->accessToken && $this->refreshToken) {

            $message = Swift_Message::newInstance();
            $message->setTo($to);

            if (!empty($cc)) {
                $message->setCc($cc);
            }

            if (!empty($bcc)) {
                $message->setBcc($bcc);
            }

            if (!empty($in_reply_to)) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('In-Reply-To', $in_reply_to);
                $headers->addTextHeader('References', $in_reply_to);
            }

            $message->setBody($message_body, 'text/html');


            if(!is_null($in_reply_to)) {
                if(stripos($subject, 're:') === false) {
                    $subject = 'Re: ' . $subject;
                }
            }
            $message->setSubject($subject);

            if(!empty($files)) {
                foreach ($files as $file) {
                    $message->attach(Swift_Attachment::fromPath($file->getRealPath())->setFilename($file->getClientOriginalName()));
                }
            }

            $gm_message = new Google_Service_Gmail_Message();
            $gm_message->setRaw(base64url_encode($message->toString()));

            $sent = $this->service->users_messages->send('me', $gm_message);
            if($sent) {

                $msg = $this->service->users_messages->get('me', $sent->id);

                // Collect headers
                $headers = collect($msg->getPayload()->headers);

                return [
                    'id' => $sent->id,
                    'Message-Id' => $this->findProperty($headers, 'Message-Id'),
                ];
            }

        }
    }
}