<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Five9\Five9MessageController;

use Illuminate\Support\Facades\Log;

class MessengerController extends Controller
{
    public function index() {
        $this->verifyAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['entry'][0]['messaging'][0]['sender']['id'];
        $message = $input['entry'][0]['messaging'][0]['message']['text'];

        $response = [
            'recipient' => ['id' => $id],
            'message' => ['text' => $message]
        ];

        $this->sendMessage($response);

    }

    protected function sendMessage($response) {

        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');
        Log::debug('***************************** Messenger Vonage BEGIN ***********************************');
        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');

        Log::debug("Request recibido");
        Log::debug($response);

        $fbMessUsrID = $response['recipient']['id'];
        $message = $response['message']['text'];

        //Envia mensaje al Dominio De movigoo
        $tenant_name = "MoviGoo - Partner Domain";
        $tenant_id = "131160";

        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');
        Log::debug('******************************* Messege for Five9 **************************************');
        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');
        Log::debug("Id Response");
        Log::debug($fbMessUsrID);
        Log::debug("Text Response");
        Log::debug($message);

        $five9 = new Five9MessageController($tenant_name, $tenant_id);
        $session = $five9->get_session();

        $interaction_data->api_url = $session->metadata->dataCenters[0]->apiUrls[0]->host;

        Log::debug($interaction_data);


        Log::debug($session);
        
        $five9_message->send_message(
            $interaction->api_url,
            $interaction->conversation_id,
            $interaction->five9_farm_id,
            $message
        );

        
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
