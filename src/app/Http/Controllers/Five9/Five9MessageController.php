<?php

namespace App\Http\Controllers\Five9;

use App\Http\Controllers\Controller;
use \Log;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class Five9MessageController extends Controller
{
    public function __construct($tenant_name, $tenant_id)
    {
        $this->tenant_name = $tenant_name;
        $this->tenant_id = $tenant_id;

        $this->httpClient = new \GuzzleHttp\Client();
    }

    public function get_session()
    {
        $url = "https://app.five9.com/appsvcs/rs/svc/auth/anon?cookieless=true";
        $json = ['tenantName' => $this->tenant_name];

        try {
            $request = $this->httpClient->post($url,
                [
                    'json' => $json,
                ]
            );
            $response = json_decode($request->getBody()->getContents());
        } catch (\Exception $ex) {
            $response = $ex;
        }

        return $response;
    }

    public function create_chat_interaction($session, $interaction_data)
    {
        # $interaction_data: object example
        # {
        #   "callback_url": "http://dev.movigooapp.com/whatsapps/webhook/"
        #   "campaign_name": "whatsapp"
        #   "tenant_id": "131160"
        #   "api_url": "app-atl.five9.com"
        #   "phone_number": "56972216367"
        # }

        Log::debug("Info desde five9");
        Log::debug($interaction_data->api_url);

        Log::debug($session->tokenId);
        Log::debug("FIN DATOS FIVE 9");

        $url = 'https://' . $interaction_data->api_url . '/appsvcs/rs/svc/conversations';

        try {
            $headers = [
                'Authorization' => 'Bearer-' . $session->tokenId,
                'farmId' => $session->context->farmId,
                'Content-Type' => 'application/json',
            ];

            $contact = ['number1' => $interaction_data->phone_number];
            $attributes = ['Question' => 'Whatsapp'];
            $json = [
                'tenantId' => $interaction_data->tenant_id,
                'campaignName' => $interaction_data->campaign_name,
                'contact' => $contact,
                'callbackUrl' => $interaction_data->callback_url,
                'attributes' => $attributes,
            ];

            $request = $this->httpClient->post($url,
                [
                    'headers' => $headers,
                    'json' => $json,
                ]
            );
            $response = json_decode($request->getBody()->getContents());

        } catch (\Exception $ex) {
            $response = $ex;
        }

        return $response;
    }

    public function send_message($api_url, $conv_id, $farm_id, $message)
    {
        $url = 'https://' . $api_url . '/appsvcs/rs/svc/conversations/' . $conv_id . '/messages';

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer-' . $conv_id,
                'farmId' => $farm_id,
            ];

            $json = [
                'messageType' => "TEXT",
                'message' => $message,
            ];

            $request = $this->httpClient->post($url,
                [
                    'headers' => $headers,
                    'json' => $json,
                ]
            );
            $response = json_decode($request->getBody()->getContents());
        } catch (\Exception $ex) {
            $response = $ex;
        }

        return $response;
    }
}
