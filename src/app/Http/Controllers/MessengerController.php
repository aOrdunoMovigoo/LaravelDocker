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
        Log::debug($input);
        $id = $input['entry'][0]['messaging'][0]['sender']['id'];
        $message = $input['entry'][0]['messaging'][0]['message']['text'];

        $response = [
            'recipient' => ['id' => $id],
            'message' => ['text' => $message]
        ];

        $this->sendMessage($response);

    }

    protected function sendMessage($response) {
        
        //Envia mensaje al Dominio De movigoo
        $tenant_name = "MoviGoo - Partner Domain";
        $tenant_id = "131160";
        
        $campaign_name = 'whatsapp_ventas';
        $callback_url = "https://movigooapp.com/gateway/five9/senati/v1/senati/webhooks/whatsapps/";
        $integration_id = 2; // five9


        $fbMessUsrID = $response['recipient']['id'];
        $message = $response['message']['text'];


        $five9 = new Five9MessageController($tenant_name, $tenant_id);
        $session = $five9->get_session();

        $interaction_data = new \stdClass();
        $interaction_data->callback_url = $callback_url;
        $interaction_data->campaign_name = $campaign_name;
        $interaction_data->tenant_id = $tenant_id;
        $interaction_data->api_url = $session->metadata->dataCenters[0]->apiUrls[0]->host;
        $interaction_data->phone_number = '+526141287721';
        $interaction_data->five9_farm_id = $session->context->farmId;

        $conversation_data = $five9->create_chat_interaction($session, $interaction_data);

        Log::debug($message);

        $five9->send_message(
            $interaction_data->api_url,
            $conversation_data->id,
            $interaction_data->five9_farm_id,
            $message
        );

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
