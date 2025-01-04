<?php

namespace Pointilis\Pustaloka\Core;

use Google\Client;

class Google_API_Client {

    protected string $api_key = 'AIzaSyCBbcySH-7dqckZ0bpcURdxv2TQ9hITAYI';
    private Client $client;

    public function __construct() {
        $this->client = new Client();
        $this->client->setAuthConfig(PUSTALOKA_PATH . '/client_secret.json');
        $this->client->setApplicationName('Pustaloka');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/userinfo.email', 
            'https://www.googleapis.com/auth/userinfo.profile',
        ]);
    }

    /**
     * Validate ID Token
     * 
     * @param string $id_token
     */
    public function validate_id_token(string $id_token) {
        try {
            $payload = $this->client->verifyIdToken($id_token);
            if ($payload) {
                return $payload;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

}