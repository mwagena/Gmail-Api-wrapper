<?php

namespace MartijnWagena\Gmail;

use Google_Client;
use Google_Service_Gmail;

class Gmail {

    protected $config;
    protected $scopes;
    protected $accessToken;
    protected $emailAddress;

    public function __construct($config = [])
    {
        $this->config = $config;

        $this->scopes = implode(' ', [
            Google_Service_Gmail::GMAIL_READONLY,
            Google_Service_Gmail::GMAIL_SEND
        ]);

        $this->accessToken = '';
        $this->refreshToken = '';

        $this->client = new Google_Client();

        $this->getClient();
    }

    public static function create()
    {
        return new static;
    }

    /**
     * @return Google_Client
     */
    public function getClient() {
        $this->client->setApplicationName($this->config['app_name']);
        $this->client->setScopes($this->scopes);
        $this->client->setAuthConfig($this->getAuthConfig());
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri($this->config['redirect_uri']);
        return $this->client;
    }

    /**
     * @return array
     */
    public function getAuthUrl() {

        $this->getClient();
        $authUrl = $this->client->createAuthUrl();

        return compact('authUrl');
    }

    /**
     * @param $code
     * @return static
     */
    public function makeAccessToken($code) {
        $this->getClient();

        $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

        $this->accessToken = $accessToken['access_token'];
        $this->refreshToken = $accessToken['refresh_token'];

        $this->client->setAccessToken($this->formatAccessToken());

        $me = $this->getProfile();
        if($me) {
            $this->emailAddress = $me->emailAddress;
        }


        return $this->saveAccessToken();
    }

    /**
     * @param bool $update
     * @return static
     */
    public function saveAccessToken() {
        $config = json_decode(
            File::get(
                $file = storage_path('gmail-' . $this->emailAddress . 'json')
            ),
            true
        );

        $config['access_token'] = $this->accessToken;
        $config['refresh_token'] = $this->refreshToken;
        $config['email'] = $this->emailAddress;

        File::put($file, json_encode($config));
    }

    /**
     *
     */
    public function revokeAccess() {
        $this->client->revokeToken();
    }

    /**
     * @return \Google_Service_Gmail_Profile
     */
    private function getProfile() {
        $service = new Google_Service_Gmail($this->client);
        return $service->users->getProfile('me');
    }

    /**
     *
     */
    public function isAccessTokenExpired() {

        if($this->client->isAccessTokenExpired()) {

            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $accessToken = $this->client->getAccessToken();

            $this->accessToken = $accessToken['access_token'];
            $this->refreshToken = $accessToken['refresh_token'];

            $this->saveAccessToken();
        }
    }

    /**
     * @param $accessToken
     * @param $refreshToken
     * @return $this
     */
    public function setAccessToken($accessToken, $refreshToken) {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->client->setAccessToken($this->formatAccessToken());

        return $this;
    }


    /**
     * @return array
     */
    private function formatAccessToken() {
        return [
            "access_token" => $this->accessToken,
            "token_type" => "Bearer",
            "expires_in" => 3600,
            "refresh_token" => $this->refreshToken,
            "created" => 1492614871
        ];
    }

    /**
     * @return array
     */
    private function getAuthConfig()
    {
        return [
            'installed' => [
                'client_id' => $this->config['client_id'],
                'project_id' => $this->config['project_id'],
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://accounts.google.com/o/oauth2/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_secret' => $this->config['secret'],
                'redirect_uris' => [
                    "urn:ietf:wg:oauth:2.0:oob",
                    "http://localhost",
                    $this->config['redirect_uri'],
                ]
            ]
        ];
    }

    /**
     * @param $list
     * @param $property
     * @return string|null
     */
    public function findProperty($list, $property) {

        $find = $list->filter(function($e) use ($property) {
            return $e->name == $property;
        })->first();

        if($find) {
            return $find->value;
        }
        return null;
    }

}

