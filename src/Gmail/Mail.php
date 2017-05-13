<?php

namespace MartijnWagena\Gmail;

use Carbon\Carbon;
use Google_Service_Gmail;
use Illuminate\Support\Collection;

class Mail extends Gmail
{
    protected $service;
    protected $start_date;

    /**
     * Mail constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->service = new Google_Service_Gmail($this->client);
    }

    /**
     * @param $date
     * @return $this
     */
    public function setStartDate($date) {
        $this->start_date = $date->format('Y/m/d');

        return $this;
    }

    /**
     * Get all email ids since the service is added to Watermelon
     *
     * @return Collection
     */
    public function fetch()
    {
        $mails = $this->service->users_messages->listUsersMessages('me', [
            'q' => 'after:' . $this->start_date . ' AND -label:chat AND -label:sent'
        ]);

        $collect = collect();
        foreach($mails as $m) {
            $obj = new \stdClass();
            $obj->id = $m->id;
            $obj->threadId = $m->threadId;

            $collect->push($obj);
        }

        return $collect->reverse();
    }

    /**
     * @param $messageId
     * @param $threadId
     * @return array|bool
     */
    public function getMessage($messageId, $threadId)
    {
        if(!$messageId) {
            return false;
        }
        $mail = $this->service->users_messages->get('me', $messageId, ['format' => 'full']);

        // Get actual mail payload
        $m = $mail->getPayload();

        $message = [
            'id' => $messageId,
            'thread_id' => $threadId,
            'message_id' => null,
            'parent_id' => null,
            'date' => null,
            'to' => [],
            'cc' => [],
            'subject' => null,
            'from' => null,
            'reply-to' => null,
            'body' => [
                'html' => null,
                'text' => null,
            ],
            'attachments' => [],
        ];

        // Collect headers
        $headers = collect($m->headers);

        // Set MessageId
        $message_id = $this->findProperty($headers, 'Message-Id');
        if(!$message_id) {
            $message_id = $this->findProperty($headers, 'Message-ID');
        }
        $message['message_id'] = $message_id;

        // Set ParentId
        $message['parent_id'] = $this->findProperty($headers, 'References');

        // Set Date
        $message['date'] = Carbon::parse($this->findProperty($headers, 'Date'));

        // Set receiving user
        $message['to'] = $this->mapContacts($this->findProperty($headers, 'To'));

        // Set Cc when available
        if($cc = $this->findProperty($headers, 'Cc')) {
            $message['cc'] = $this->mapContacts($cc);
        }

        // Set Subject
        $message['subject'] = $this->findProperty($headers, 'Subject');

        // Set Sender address
        $message['from'] = $this->mapContact($this->findProperty($headers, 'From'));

        // Set Reply-to when available
        if($replyTo = $this->findProperty($headers, 'Reply-To')) {
            $message['reply-to'] = $replyTo;
        }

        if($m->parts) {

            // Set body data
            foreach ($m->parts as $key => $part) {

                if ($key == 0 && $part['mimeType'] == 'text/plain') {
                    // plain text
                    $message['body']['text'] = $this->getRawBody($part);

                } else {

                    // html/text version and attachments
                    if(isset($part['parts'])) {
                        foreach ($part['parts'] as $p) {

                            // determine if its an attachment
                            if (isset($p['filename']) && !empty($p['filename'])) {
                                $filename = $p['filename'];
                                $attachmentId = $p['body']['attachmentId'];
                                $message['attachments'][] = [
                                    'mimeType' => $p['mimeType'],
                                    'contents' => $this->getAttachment($messageId, $attachmentId),
                                    'filename' => $filename,
                                ];

                            } else {

                                if ($p['mimeType'] == 'text/plain') {
                                    // plain text
                                    $message['body']['text'] = $this->getRawBody($p);

                                } else {
                                    // otherwise its the html body
                                    $message['body']['html'] = $this->getHtmlBody($p);
                                }

                            }
                        }
                    } else {
                        if ($part['mimeType'] == 'text/html') {
                            $message['body']['html'] = $this->getHtmlBody($part);
                        }
                    }

                }

            }
        } else {
            $message['body']['html'] = $this->getRawBody($m);
        }

        return $message;
    }

    /**
     * @param $body
     * @return string
     */
    private function getHtmlBody($body) {

        return base64url_decode($body->getBody()->data);
    }

    /**
     * @param $body
     * @return string
     */
    private function getRawBody($body) {
        return base64url_decode($body->getBody()->data);
    }

    /**
     * @param $messageId
     * @param $attachmentId
     * @return string
     */
    private function getAttachment($messageId, $attachmentId) {
        $attachment = $this->service->users_messages_attachments->get('me', $messageId, $attachmentId);

        return base64url_decode($attachment->getData());
    }

    /**
     * @param $contacts
     * @return Collection
     */
    private function mapContacts($contacts) {
        $to_explode = collect(explode(', ', $contacts));
        $t = $to_explode->map(function($to) {
            return $this->mapContact($to);
        });
        return $t;
    }

    /**
     * @param $contact
     * @return array
     */
    private function mapContact($contact) {
        $t = explode(' ', $contact);

        $i_address = $t[count($t) - 1];
        $address = str_replace(['<', '>'], '', $i_address);
        $address = trim($address);

        return [
            'name' => trim(str_replace($i_address, '', $contact)),
            'email' => $address,
        ];
    }
}