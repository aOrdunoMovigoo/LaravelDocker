<?php

namespace App\Http\Controllers;

use App\Client;
use App\Events\MessageSent;
use App\Http\Controllers\Five9\Five9MessageController;
use App\Http\Controllers\HsmWspPushController;
use App\User;
use App\WhatsappClient;
use App\WhatsappClientUserRelationship;
use App\WhatsappPurecloudInteractions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Storage;
use \Log;


class WhatsappClientVonageV2Five9Controller extends Controller
{
   

    private $now;
    public function __construct()
    {
        // se hace constructor de la hora para manipular diferente por ser un cliente de PERU
        $this->now = Carbon::now('America/Lima');

        // Configurando horario de entrada de senati
        $this->todayIN = Carbon::now('America/Lima');
        $this->todayIN->hour = 12; // dejar a las 8
        $this->todayIN->minute = 43; // deja a las 0 horas
        $this->todayIN->second = 00;

        // Configurando horario de salida de senati
        $this->todayOUT = Carbon::now('America/Lima');
        $this->todayOUT->hour = 13; // dejar a 20 horas
        $this->todayOUT->minute = 01;
        $this->todayOUT->second = 00;

        // proceso para interaccion de purecloud
        // TOKENS PARA Senati
        // TOKENS PARA Senati
        $this->client_id = '61c0a83c-32f8-4892-8e46-5d3d9b51ca83';
        $this->secret_id = 'HjaCO3D2iID1G5HqeNIKPjhxCG-aDpWYQoYne9JlwPQ';

        // Descomentado quiere decir todo trafico a five9 , si se comenta el trafico vuelve a PureCloud IMPORTaNTE
        $this->switch = "1";

    }

    public function index()
    {
        //
    }

    public function sendMessageClientInfoBip(Request $request)
    {

        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');
        Log::debug('*****************************Whatsapp Vonage BEGIN**************************************');
        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');

        Log::debug("Request recibido");
        Log::debug($request);

        $wspID = $request->results[0]["messageId"];
        $whatsappClientRepeated = WhatsappClient::where('wsp_id', $wspID)->first();
        $client = Client::where("email", "senati@senati.com")->first(); //Cliente Movigoo
        $clientRelationShip = WhatsappClientUserRelationship::where('client_id', $client->id)->where('isClient', false)->first();
        $user = User::where('email', $clientRelationShip->email)->first(); // obteniendo usuario en base a relacion
        $phone_numberCheck = $request->results[0]["from"];
        $hsmWsp = new Whatsappclient();
        
        //Logica de obtener el nro
        $phone_number = $request->from["number"];
        $nameWSP = 'Generico'; //En vonage no viene el nombre del cliente
        Log::Debug($phone_number);


        if ($phone_number == '51968435553' || $phone_number == '56999632861') { //  numeros de prueba - Jose Contreras y Francisco Gariglio
            $queue_id = '39b126d5-259e-46dd-b11b-2053a009ea67'; // desarrollo
        } else {
            $queue_id = '39b126d5-259e-46dd-b11b-2053a009ea67'; // produccion

        }


        // GUARDAR WSP ID
        // se activa la logica para IVR
        $ivr_active = true;
        $purecloud_interaction = WhatsappPurecloudInteractions::where('phone_number', $phone_number)
                ->where('client_id', $client->id)
                ->where('status', true)
                ->orderBy('created_at', 'desc')
                ->first();


        if ($purecloud_interaction) {
                $status = true;
                $conversation_id = $purecloud_interaction->conversation_id;

                $test_numbers = ["51968435553", "56951794079", "56948846031", "56999632861", "56963606985", "56956583074", "51968435553", "51938715736"];
                if (in_array($phone_number, $test_numbers) or $this->switch == "1") { // no tiene mucha logica la verdad

                    //asi se obtiene el type desde vonage
                    $type = $request->message["content"]["type"];
                    

                    switch ($type) {
                        case 'text':

                            $type = 'text';
                            Log::debug('****************************************************************************************');
                            Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION TEXT TYPE');
                            Log::debug('****************************************************************************************');

                            // solo guardamos el texto

                            //Obtener mensaje del nuevo WebHOOK
                            $message = $request->message["content"]["text"]; // obtenemos el mensaje del cliente

                            // validacion de mensajes con menos de 2 letras -- con el fin de evitar lluvia de mensajes
                            // se hace return y no se envia ni guarda mensaje
                            if (strlen($message) < 2 && $message != '1' && $message != '2' && $message != '3' && $message != '4' && $message != '5' && $message != '6' && $message != '7' && $message != '8' && $message != '9' && $message != '0') { // se quita el 1 y dos por si es IVR
                                Log::debug('****************************************************************************************');
                                Log::debug('WhatsappClientController -- sendMessageClient -- minus letter');
                                Log::debug('webhook menos de dos letras');
                                Log::debug($message);
                                Log::debug(strlen($message));
                                Log::debug('****************************************************************************************');
                                return ['status' => 'webhook string lenght!'];
                            }

                            break;

                        case 'image':
                            $type = 'image';
                            Log::debug('****************************************************************************************');
                            Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION IMAGE TYPE');
                            Log::debug('****************************************************************************************');

                            // descargamos la imagen y la guardamos en el proyecto
                            // Una vez guardada, lo codificamos a base 64 y luego la guardamos en la base de datos

                            $img_download_url = $request->results[0]["message"]["url"];
                            //$img_download_url = $request->message["content"]["image"]["url"];

                            Log::debug('URL DE IMAGEN ' . $img_download_url);

                            // se agrega este codigo debido al funcionamiento de infoBIP
                            $username = 'SenatiPeru';
                            $password = 'Senati2018##';

                            $context = stream_context_create(array(
                                'http' => array(
                                    'header' => "Authorization: Basic " . base64_encode("$username:$password"),
                                ),
                            ));

                            $contents = file_get_contents($img_download_url, false, $context);

                            $image_name = "$client->name/$phone_number/Clients/img/$client->name" . "_" . substr($img_download_url, strrpos($img_download_url, '/') + 1); // ruta y nombre de archivo a guardar
                            $message = $image_name; // se guarda la ruta de la imagen en el mensaje

                            // guardando imagen -- Se cambio por S3
                            // $img_storage = Storage::disk('whatsapp')->put($image_name, $contents);
                            $img_storage = Storage::disk('s3_whatsapp')->put($image_name, $contents);

                            // tomando datos de imagen cargada para tranformar a base 64
                            // $upload_img = Storage::disk('whatsapp')->get($image_name);
                            // $content_type = Storage::disk('whatsapp')->mimeType($image_name);
                            $upload_img = Storage::disk('s3_whatsapp')->get($image_name);
                            $content_type = Storage::disk('s3_whatsapp')->mimeType($image_name);

                            $base64 = 'data:' . $content_type . ';base64,' . base64_encode($upload_img); // url final para mostrar imagen codificada en base 64 web
                            $message = "https://movigooapp-whatsapp.s3.us-east-2.amazonaws.com/" . $image_name;
                            break;

                        default:
                            // se guarda en el log request de tipo interacciones no desarrolladas aun.
                            // como respaldo para usar como ejemplo mientras se avanza en el desarrollo.
                            Log::debug('****************************************************************************************');
                            Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION OTHER TYPE');
                            Log::debug('****************************************************************************************');
                            Log::debug($request);
                            return ['status' => 'INTERACTION OTHER TYPE'];

                            break;
                    }

                    // $message = $request->results[0]["message"]["text"];
                    // $status = false;
                    // $conversation_id = null;

                    // guardamos mensaje
                    $hsmWsp->phone_number = $phone_number;
                    $hsmWsp->client_name = $nameWSP;
                    $hsmWsp->client_id = $client->id;
                    $hsmWsp->message = $message;
                    $hsmWsp->user_id = $user->id;
                    $hsmWsp->status = $status;
                    $hsmWsp->conversation_id = $conversation_id;
                    $hsmWsp->jwt = null;
                    $hsmWsp->wsp_id = $wspID;
                    $hsmWsp->type = $type;
                    // $hsmWsp->save();
                    $hsmWsp->integration_id = 2;
                    $hsmWsp->save();

                
                    //Envia mensaje al Dominio De movigoo
                    $tenant_name = "MoviGoo - Partner Domain";
                    $tenant_id = "131160";
                    

                    $five9_message = new Five9MessageController($tenant_name, $tenant_id);

                    $five9_message->send_message(
                        $purecloud_interaction->five9_url_api,
                        $purecloud_interaction->conversation_id,
                        $purecloud_interaction->five9_farm_id,
                        $message
                    );

                    $return = "five9";
                }


            } else {
                $return = $this->sendMessageClient2($request, $client, $this->client_id, $this->secret_id, $queue_id, $ivr_active);
            }

        
        
     
        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');
        Log::debug('***************************** Whatsapp Senati END **************************************');
        Log::debug('****************************************************************************************');
        Log::debug('****************************************************************************************');

        return $return;
    }


    //Cuando la interaccion no esta activa

    public function sendMessageClient2(Request $request, Client $client, $client_id, $secret_id, $queue_id, $ivr_active = null, $ivr_attributes = null)
    {
        try {



            ///////
            $type = $request->message["content"]["type"];
            //ok
            $wspID = $request->message_uuid;
            //validado
            $phone_number = $request->from["number"];
             //ok
            $timestamp = $request->timestamp;       
            // no lo trae el webhook
            $nameWSP = $request->from["number"];




            // Guardamos inicio de controlador con
            // tipo y numero para poder identificar rapido en caso de errores
            Log::debug('****************************************************************************************');
            Log::debug('WhatsappClientController -- sendMessageClient -- TYPE & PHONE_NUMBER');
            Log::debug($type);
            Log::debug($phone_number);
            Log::debug('****************************************************************************************');

            // Procesos por tipo de mensaje
            switch ($type) {
                case 'text':

                    $type = 'text';
                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION TEXT TYPE');
                    Log::debug('****************************************************************************************');

                    // solo guardamos el texto

                    //Obtener mensaje del nuevo WebHOOK
                    $message = $request->message["content"]["text"];
                    

                    // validacion de mensajes con menos de 2 letras -- con el fin de evitar lluvia de mensajes
                    // se hace return y no se envia ni guarda mensaje
                    if (strlen($message) < 2 && $message != '1' && $message != '2' && $message != '3' && $message != '4' && $message != '5' && $message != '6' && $message != '7' && $message != '8' && $message != '9' && $message != '0') { // se quita el 1 y dos por si es IVR
                        Log::debug('****************************************************************************************');
                        Log::debug('WhatsappClientController -- sendMessageClient -- minus letter');
                        Log::debug('webhook menos de dos letras');
                        Log::debug($message);
                        Log::debug(strlen($message));
                        Log::debug('****************************************************************************************');
                        return ['status' => 'webhook string lenght!'];
                    }

                    break;

                case 'image':
                    $type = 'image';
                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION IMAGE TYPE');
                    Log::debug('****************************************************************************************');

                    // descargamos la imagen y la guardamos en el proyecto
                    // Una vez guardada, lo codificamos a base 64 y luego la guardamos en la base de datos

                    $img_download_url = $request->results[0]["message"]["url"];
                    //$img_download_url = $request->message["content"]["image"]["url"];

                    Log::debug('URL DE IMAGEN ' . $img_download_url);

                    // se agrega este codigo debido al funcionamiento de infoBIP
                    $username = 'SenatiPeru';
                    $password = 'Senati2018##';

                    $context = stream_context_create(array(
                        'http' => array(
                            'header' => "Authorization: Basic " . base64_encode("$username:$password"),
                        ),
                    ));

                    $contents = file_get_contents($img_download_url, false, $context);

                    $image_name = "$client->name/$phone_number/Clients/img/$client->name" . "_" . substr($img_download_url, strrpos($img_download_url, '/') + 1); // ruta y nombre de archivo a guardar
                    $message = $image_name; // se guarda la ruta de la imagen en el mensaje

                    // guardando imagen -- Se cambio por S3
                    // $img_storage = Storage::disk('whatsapp')->put($image_name, $contents);
                    $img_storage = Storage::disk('s3_whatsapp')->put($image_name, $contents);

                    // tomando datos de imagen cargada para tranformar a base 64
                    // $upload_img = Storage::disk('whatsapp')->get($image_name);
                    // $content_type = Storage::disk('whatsapp')->mimeType($image_name);
                    $upload_img = Storage::disk('s3_whatsapp')->get($image_name);
                    $content_type = Storage::disk('s3_whatsapp')->mimeType($image_name);

                    $base64 = 'data:' . $content_type . ';base64,' . base64_encode($upload_img); // url final para mostrar imagen codificada en base 64 web
                    $message = $base64;
                    break;

                case 'document':
                    $type = 'document';
                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION DOCUMENT TYPE');
                    Log::debug('****************************************************************************************');

                    $document_download_url = $request->results[0]["message"]["url"];
                    $document_name = $request->results[0]["message"]["caption"];
                    //$img_download_url = $request->message["content"]["image"]["url"];

                    Log::debug('URL DEL DOCUMENTO ' . $document_download_url);

                    // se agrega este codigo debido al funcionamiento de infoBIP
                    $username = 'SenatiPeru';
                    $password = 'Senati2018##';

                    $context = stream_context_create(array(
                        'http' => array(
                            'header' => "Authorization: Basic " . base64_encode("$username:$password"),
                        ),
                    ));

                    $contents = file_get_contents($document_download_url, false, $context);

                    $document_name_url = "$client->name/$phone_number/Clients/file/$client->name" . "_" . $document_name; // ruta y nombre de archivo a guardar
                    $message = $document_name_url; // se guarda la ruta de la imagen en el mensaje

                    // guardando imagen -- Se cambio por S3
                    // $document_storage = Storage::disk('whatsapp')->put($document_name_url, $contents); // guardando en laravel el archivo
                    $document_storage = Storage::disk('s3_whatsapp')->put($document_name_url, $contents); // guardando en AWS S3 el archivo

                    // tomando datos de imagen cargada para tranformar a base 64
                    // $upload_document = Storage::disk('whatsapp')->get($document_name);
                    // $content_type = Storage::disk('whatsapp')->mimeType($document_name);

                    // $base64 = 'data:'.$content_type.';base64,'.base64_encode($upload_document); // url final para mostrar imagen codificada en base 64 web
                    // $message = $base64;

                    break;

                case 'voice':
                    $type = 'voice';
                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION VOICE TYPE');
                    Log::debug('****************************************************************************************');

                    $voice_download_url = $request->results[0]["message"]["url"];
                    $voice_name = 'voice_' . date("YmdHis"); // nosotros colocamos el nombre en base a la fecha de llegada
                    // $message = $voice_download_url;

                    Log::debug('URL DEL VOICE ' . $voice_download_url);

                    // se agrega este codigo debido al funcionamiento de infoBIP
                    $username = 'SenatiPeru';
                    $password = 'Senati2018##';

                    $context = stream_context_create(array(
                        'http' => array(
                            'header' => "Authorization: Basic " . base64_encode("$username:$password"),
                        ),
                    ));

                    $contents = file_get_contents($voice_download_url, false, $context);

                    $voice_name_url = "$client->name/$phone_number/Clients/voice/$client->name" . "_" . $voice_name; // ruta y nombre de archivo a guardar
                    $message = $voice_name_url;

                    // $voice_storage = Storage::disk('whatsapp')->put($voice_name_url, $contents); // guardando en laravel el archivo
                    $voice_storage = Storage::disk('s3_whatsapp')->put($voice_name_url, $contents); // guardando en laravel el archivo

                    break;

                default:
                    // se guarda en el log request de tipo interacciones no desarrolladas aun.
                    // como respaldo para usar como ejemplo mientras se avanza en el desarrollo.
                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- INTERACTION OTHER TYPE');
                    Log::debug('****************************************************************************************');
                    Log::debug($request);
                    return ['status' => 'INTERACTION OTHER TYPE'];

                    break;
            }

            // Usuarios relacionados con el cliente para guardar el mensaje de whatsapp
            $clientRelationShip = WhatsappClientUserRelationship::where('client_id', $client->id)->where('isClient', false)->first();

            Log::debug('Email es ' . $clientRelationShip->email);

            $user = User::where('email', $clientRelationShip->email)->first(); // obteniendo usuario en base a relacion

            // Verificacion de mensaje repetido
            $previous_wsp_message = Whatsappclient::where('phone_number', $phone_number)
                ->where('conversation_id', '!=', null)
                ->where('conversation_id', '!=', '1234qwer')
                ->where('client_id', $client->id)
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // dd($previous_wsp_message);

            if ($previous_wsp_message) {
                if ($previous_wsp_message->message == $message && $this->now->diffInSeconds($previous_wsp_message->created_at) <= 30) { // evitando enviar mensajes duplicados

                    Log::debug('****************************************************************************************');
                    Log::debug('WhatsappClientController -- sendMessageClient -- mensaje repetido');
                    Log::debug($message);
                    Log::debug($previous_wsp_message->message);
                    Log::debug('****************************************************************************************');

                    return ['status' => 'webhook mensaje repetido!'];
                }
            }

            //do-while para esperar a que se cree una interaccion del numero
            $i = 0;
            $non_existing_interaction = Whatsappclient::where('phone_number', $phone_number)->where('conversation_id', '=', null)->where('client_id', $client->id)->orderBy('created_at', 'desc')->first();

            // instanciando controlladores y modelo
            $hsmWspController = new HsmWspPushController($client->whatsapp_media_id);
            $hsmWsp = new Whatsappclient();

            // guardamos mensaje
            $hsmWsp->phone_number = $phone_number;
            $hsmWsp->client_name = $nameWSP;
            $hsmWsp->client_id = $client->id; // client_id por cliente
            $hsmWsp->message = $message;
            $hsmWsp->user_id = $user->id; // user_id
            $hsmWsp->status = false;
            $hsmWsp->conversation_id = null;
            $hsmWsp->jwt = $hsmWspController->loginHSM()->jwt;
            $hsmWsp->type = $type;
            $hsmWsp->created_at = $this->now;

            $test_numbers = ["56972216367", "56951794079", "56948846031", "56999632861", "56963606985", "56956583074", "51968435553", "51938715736"];
            if (in_array($phone_number, $test_numbers) or $this->switch == "1") {
                $hsmWsp->integration_id = 2;
            }

            $hsmWsp->save();

            if ($previous_wsp_message) { // existencia de un mensaje previo del numero de parte del agente

                $conversation_id = $previous_wsp_message->conversation_id;
                $member = $previous_wsp_message->member;
                $jwt = $previous_wsp_message->jwt;
                $created_at = $previous_wsp_message->created_at;

                $purecloud_active_interactions = WhatsappPurecloudInteractions::where('phone_number', $phone_number)->orderBy('created_at', 'desc')->first();

                $isInteractionActive = $purecloud_active_interactions->status;

                if (!$isInteractionActive) { // interaccion no esta activa

                    // Usuarios relacionados con el cliente para guardar el mensaje de whatsapp
                    $clientRelationShipIVR = WhatsappClientUserRelationship::where('client_id', $client->id)->where('isClient', true)->first();
                    $userIVR = User::where('email', $clientRelationShipIVR->email)->first(); // obteniendo usuario en base a relacion

                    // Obtener mensaje anterior de Agente
                    $previous_ivr_wsp_message = Whatsappclient::where('phone_number', $phone_number)
                        ->where('client_id', $client->id)
                        ->where('user_id', $userIVR->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($previous_ivr_wsp_message) {

                        if ($previous_ivr_wsp_message->ivr_step == 'IVR-SENATI-1') {

                            switch ($message) {
                                case '1':
                                    $queue_id = '39b126d5-259e-46dd-b11b-2053a009ea67';
                                    $campaignName = 'whatsapp_ventas';

                                    // AQUI PONER MENSAJE DE ESPERA
                                    $this->WaitForAgent($client, $userIVR, $phone_number);

                                    break;

                                case '2':
                                    $queue_id = 'ad637458-35ef-4799-ab9c-3185c0e6d716';
                                    $campaignName = 'whatsapp_atc';
                                    // AQUI PONER MENSAJE DE ESPERA
                                    $this->WaitForAgent($client, $userIVR, $phone_number);
                                    break;

                                default:

                                    // Jose Contreras
                                    // funcion para enviar mensaje IVR de senati
                                    $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);
                                    return $pushAgentMessages;

                                    break;
                            }

                            $this->CreateInteractionFive9($phone_number, $client, $campaignName, $user->id);

                        } else {

                            // Jose Contreras
                            // funcion para enviar mensaje IVR de senati
                            $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);
                            return $pushAgentMessages;
                        }

                    } else { //

                        // Jose Contreras
                        // funcion para enviar mensaje IVR de senati
                        $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);
                        return $pushAgentMessages;

                    }

                }
            } else {

                // IVR
                if ($ivr_active) {

                    // Usuarios relacionados con el cliente para guardar el mensaje de whatsapp
                    $clientRelationShipIVR = WhatsappClientUserRelationship::where('client_id', $client->id)->where('isClient', true)->first();
                    $userIVR = User::where('email', $clientRelationShipIVR->email)->first(); // obteniendo usuario en base a relacion

                    // Obtener mensaje anterior de Agente
                    $previous_ivr_wsp_message = Whatsappclient::where('phone_number', $phone_number)
                        ->where('client_id', $client->id)
                        ->where('user_id', $userIVR->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($previous_ivr_wsp_message) {

                        if ($previous_ivr_wsp_message->ivr_step == 'IVR-SENATI-1') {

                            switch ($message) {
                                case '1':
                                    $queue_id = '39b126d5-259e-46dd-b11b-2053a009ea67';
                                    $campaignName = 'whatsapp_ventas';

                                    // AQUI PONER MENSAJE DE ESPERA
                                    $this->WaitForAgent($client, $userIVR, $phone_number);

                                    break;

                                case '2':
                                    $queue_id = 'ad637458-35ef-4799-ab9c-3185c0e6d716';
                                    $campaignName = 'whatsapp_atc';

                                    // AQUI PONER MENSAJE DE ESPERA
                                    $this->WaitForAgent($client, $userIVR, $phone_number);

                                    break;

                                default:

                                    // Jose Contreras
                                    // funcion para enviar mensaje IVR de senati
                                    $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);
                                    return $pushAgentMessages;

                                    break;
                            }

                            $this->CreateInteractionFive9($phone_number, $client, $campaignName, $user->id);

                        }
                        // solving hour error when first time writing
                        else {
                            // Jose Contreras
                            // funcion para enviar mensaje IVR de senati
                            $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);
                            return $pushAgentMessages;
                        }
                    } else { // primera vez que una persona escribe

                        // Jose Contreras
                        // funcion para enviar mensaje IVR de senati
                        $pushAgentMessages = $this->sendSenatiIvrMsg($client, $userIVR, $phone_number);

                        return $pushAgentMessages;
                    }

                }
            }

            Log::debug("EL phone numeber  ----------------------------->" . $phone_number);

            $test_numbers = ["56972216367", "56951794079", "56948846031", "56999632861", "56963606985", "56956583074", "51968435553", "51938715736"];
            if (!in_array($phone_number, $test_numbers) or $this->switch != "1") {
                $hsmWsp->conversation_id = $conversation_id;
                $hsmWsp->status = true;
                $hsmWsp->save();
                broadcast(new MessageSent($user, $hsmWsp, $hsmWsp->conversation_id))->toOthers();
            }

            // salvando conversation ID

            // transmitiendo usuario, mensaje y conversationID a un canal - el canal crea un room en base a conversation ID

            $status = 'Message Sent!';

        } catch (\Exception $ex) {

            Log::debug('****************************************************************************************');
            Log::debug('WhatsappClientController -- sendMessageClient -- Exception');
            Log::debug($ex);
            Log::debug('****************************************************************************************');

            $status = $ex->getMessage();
        }

        return ['status' => $status];
    }

    private function sendSenatiIvrMsg($client, $userIVR, $phone_number)
    {

        $message = '¡Hola! Bienvenido a SENATI, donde están los expertos que le dan vida a la tecnología.';
        $message .= chr(10);
        $message .= chr(10);
        $message .= 'Recuerda que al continuar con esta conversación, tus datos serán utilizados conforme a nuestra política de privacidad que puedes consultar en el siguiente enlace: http://bit.ly/senati-terminos ';
        $message .= chr(10);
        $message .= chr(10);
        $message .= '*Si desea ser atendido por un asesor de ventas Escriba "1".*';
        $message .= chr(10);
        $message .= chr(10);
        $message .= '*Si desea ser atendido por un asesor de atención al cliente Escriba "2".*';

        // se responde con mensaje fuera de horario
        // construyendo request para enviar mensaje
        $laravel_request = new \stdClass();
        $laravel_request->user = $userIVR;
        $laravel_request->phone_number = $phone_number;
        $laravel_request->client_id = $client->id;
        $laravel_request->message = $message;
        $laravel_request->user_id = $userIVR->id;

        // aca manejamos los pasos del IVR
        $laravel_request->conversation_id = null;
        $laravel_request->ivr_step = 'IVR-SENATI-1'; // paso de IVR 1

        // enviando mensaje desde codigo para que quede registrado
        $pushAgentMessages = $this->pushAgentMessagesJSON($laravel_request);

        return $pushAgentMessages;
    }

    public function pushAgentMessagesJSON($request)
    {

        $user = $request->user;
        $user = json_decode(json_encode($user, true));

        $user_database = User::where('id', $user->id)->first();
        $client = Client::where('id', $request->client_id)->first();

        $hsmWspController = new HsmWspPushController($client->whatsapp_media_id);

        $new_whatsapp_messages = new Whatsappclient();
        $new_whatsapp_messages->phone_number = $request->phone_number;
        $new_whatsapp_messages->client_name = $user->name;
        $new_whatsapp_messages->client_id = $request->client_id;
        $new_whatsapp_messages->message = $request->message;
        $new_whatsapp_messages->user_id = $request->user_id;
        $new_whatsapp_messages->status = false;
        $new_whatsapp_messages->conversation_id = $request->conversation_id;
        $new_whatsapp_messages->jwt = $hsmWspController->loginHSM()->jwt;

        $test_numbers = ["56972216367", "56951794079", "56948846031", "56999632861", "56963606985", "56956583074", "51968435553", "51938715736"];
        if (in_array($request->phone_number, $test_numbers) or $this->switch == "1") {
            $new_whatsapp_messages->integration_id = 2;
        }

        // se deshabilita la hora del server para poder guardar el mensaje con la hora de peru
        $new_whatsapp_messages->timestamps = false;
        $new_whatsapp_messages->created_at = $this->now->format('Y-m-d H:i:s');
        $new_whatsapp_messages->updated_at = $this->now->format('Y-m-d H:i:s');

        // se guarda el IVR STEP
        $new_whatsapp_messages->ivr_step = $request->ivr_step;

        $new_whatsapp_messages->save();

        // Solo se transmite mensaje en pruebas, para produccion manejamos Api de whatsapp
        // broadcast(new MessageSent($user_database, $new_whatsapp_messages->message, $new_whatsapp_messages->conversation_id))->toOthers();

        // enviamos mensaje por api de whatsapp
        //$send_message = $hsmWspController->sendWspInfoBIP($new_whatsapp_messages, $client->whatsapp_media_id);
        $send_message = $hsmWspController->sendWspVonage($new_whatsapp_messages, $client->whatsapp_media_id);

        return ['status' => 'Message Sent!'];
    }

    public function WaitForAgent($client, $userIVR, $phone_number)
    {

        $message = 'Danos unos minutos mientras te transferimos a un ejecutivo.';

        // se responde con mensaje fuera de horario
        // construyendo request para enviar mensaje
        $laravel_request = new \stdClass();
        $laravel_request->user = $userIVR;
        $laravel_request->phone_number = $phone_number;
        $laravel_request->client_id = $client->id;
        $laravel_request->message = $message;
        $laravel_request->user_id = $userIVR->id;

        // aca manejamos los pasos del IVR
        $laravel_request->conversation_id = null;
        $laravel_request->ivr_step = 'IVR-SENATI-2'; // paso de IVR 1

        // enviando mensaje desde codigo para que quede registrado
        $pushAgentMessages = $this->pushAgentMessagesJSON($laravel_request);

        return $pushAgentMessages;
    }

    public function CreateInteractionFive9($phone_number, $client, $campaignName, $user)
    {

     
     //Todo Por Movigoo
            $tenant_name = "MoviGoo - Partner Domain";
            $tenant_id = "131160";
      

        #Campaign debe ser dinamico se asociada al menu IVR anterior esto es para pruebas BY FCO
        $campaign_name = $campaignName;
        $callback_url = "https://movigooapp.com/gateway/five9/senati/v1/senati/webhooks/whatsapps/";
        $integration_id = 2; // five9

        $five9 = new Five9MessageController($tenant_name, $tenant_id);
        $session = $five9->get_session();

        $interaction_data = new \stdClass();
        $interaction_data->callback_url = $callback_url;
        $interaction_data->campaign_name = $campaign_name;
        $interaction_data->tenant_id = $tenant_id;
        $interaction_data->api_url = $session->metadata->dataCenters[0]->apiUrls[0]->host;
        $interaction_data->phone_number = '+' . $phone_number;

        $conversation_data = $five9->create_chat_interaction($session, $interaction_data);

        WhatsappClient::where('phone_number', $phone_number)
            ->where('conversation_id', null)
            ->where('client_id', $client->id)
            ->where('status', false)
            ->where('integration_id', $integration_id)
            ->update([
                'conversation_id' => $conversation_data->id,
            ]);

        $five9_interaction = new WhatsappPurecloudInteractions();
        $five9_interaction->conversation_id = $conversation_data->id;
        $five9_interaction->phone_number = $phone_number;
        $five9_interaction->client_id = $client->id;
        $five9_interaction->integration_id = $integration_id;
        $five9_interaction->five9_token_id = $session->tokenId;
        $five9_interaction->five9_farm_id = $session->context->farmId;
        $five9_interaction->five9_url_api = $session->metadata->dataCenters[0]->apiUrls[0]->host;
        $five9_interaction->save();

        $msgs_to_send = WhatsappClient::where('phone_number', $phone_number)
            ->where('conversation_id', $conversation_data->id)
            ->where('client_id', $client->id)
            ->where('user_id', $user)
            ->where('status', false)
            ->where('integration_id', $integration_id)
            ->get();

        foreach ($msgs_to_send as $msg) {
            $five9->send_message(
                $interaction_data->api_url,
                $conversation_data->id,
                $session->context->farmId,
                $msg->message
            );

            $msg->status = true;
            $msg->save();
        }
    }

    public function get_rand_alphanumeric($length)
    {
        if ($length > 0) {
            $rand_id = "";
            for ($i = 1; $i <= $length; $i++) {
                mt_srand((double) microtime() * 1000000);
                $num = mt_rand(1, 36);
                $rand_id .= assign_rand_value($num);
            }
        }
        return strtoupper($rand_id);
    }

    public function assign_rand_value($num)
    {

        // accepts 1 - 36
        switch ($num) {
            case "1":$rand_value = "a";
                break;
            case "2":$rand_value = "b";
                break;
            case "3":$rand_value = "c";
                break;
            case "4":$rand_value = "d";
                break;
            case "5":$rand_value = "e";
                break;
            case "6":$rand_value = "f";
                break;
            case "7":$rand_value = "g";
                break;
            case "8":$rand_value = "h";
                break;
            case "9":$rand_value = "i";
                break;
            case "10":$rand_value = "j";
                break;
            case "11":$rand_value = "k";
                break;
            case "12":$rand_value = "l";
                break;
            case "13":$rand_value = "m";
                break;
            case "14":$rand_value = "n";
                break;
            case "15":$rand_value = "o";
                break;
            case "16":$rand_value = "p";
                break;
            case "17":$rand_value = "q";
                break;
            case "18":$rand_value = "r";
                break;
            case "19":$rand_value = "s";
                break;
            case "20":$rand_value = "t";
                break;
            case "21":$rand_value = "u";
                break;
            case "22":$rand_value = "v";
                break;
            case "23":$rand_value = "w";
                break;
            case "24":$rand_value = "x";
                break;
            case "25":$rand_value = "y";
                break;
            case "26":$rand_value = "z";
                break;
            case "27":$rand_value = "0";
                break;
            case "28":$rand_value = "1";
                break;
            case "29":$rand_value = "2";
                break;
            case "30":$rand_value = "3";
                break;
            case "31":$rand_value = "4";
                break;
            case "32":$rand_value = "5";
                break;
            case "33":$rand_value = "6";
                break;
            case "34":$rand_value = "7";
                break;
            case "35":$rand_value = "8";
                break;
            case "36":$rand_value = "9";
                break;
        }
        return $rand_value;
    }

    private function checkHour()
    {
        $isHourOk = true;

        // dd($this->now);

        // si la hora actual en peru esta fuera del rango el metodo devuelve FALSE
        if (!($this->now > $this->todayIN && $this->now < $this->todayOUT)) {
            $isHourOk = false;
        }

        return $isHourOk;
    }


}
