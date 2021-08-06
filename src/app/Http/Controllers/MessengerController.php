<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessengerController extends Controller
{
    public function index() {
        $this->verifyAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        #$id = $input['entry'][0]['id'];
        $id = $input['entry'][0]['messaging'][0]['sender']['id'];
        Log::debug($id);
        $message = $input['entry'][0]['messaging'][0]['message']['text'];

        $response = [
            'recipient' => ['id' => $id],
            'message' => ['text' => 'Hello world!:)']
        ];

        $this->sendMessage($response);

    }

    public function index_post(Response $response) {
        Log::debug('The system is down!');
        return 'hola';
    }

    protected function sendMessage($response) {

        $ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token=' . env("PAG_ACCESS_TOKEN"));

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        curl_exec($ch);

        curl_close($ch);

    }

    public function verifyAccess() {

        $local_token = env('FACEBOOK_MESSENGER_WEBHOOK_TOKEN');
        $hub_verify_token = request('hub_verify_token');

        if ( $hub_verify_token === $local_token) {
            echo request('hub_challenge');

            exit;
        }
    }

}
