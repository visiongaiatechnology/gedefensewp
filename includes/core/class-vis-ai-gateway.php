<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_AI_Gateway {
    private const ENDPOINT='https://api.groq.com/openai/v1/chat/completions';
    private const PROFILES=['builder'=>[45,40],'seo'=>[45,30],'lingua'=>[90,20]];
    public static function chat(string $profile,string $apiKey,array $payload): array|WP_Error {
        if(!isset(self::PROFILES[$profile]))return new WP_Error('profile','AI profile rejected.');
        if($apiKey===''||strlen($apiKey)>512)return new WP_Error('auth','AI credential unavailable.');
        $rate='vgt_ai_rate_'.$profile.'_'.gmdate('YmdHi'); $count=(int)get_transient($rate);
        if($count>=self::PROFILES[$profile][1])return new WP_Error('rate','AI request budget exhausted.');
        if((int)get_transient('vgt_ai_circuit_'.$profile)>=5)return new WP_Error('circuit','AI upstream temporarily isolated.');
        set_transient($rate,$count+1,120);
        try{$payload['messages']=self::messages($payload['messages']??[]);$body=wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}catch(Throwable $e){return new WP_Error('encode','AI request rejected.');}
        if(strlen($body)>196608)return new WP_Error('size','AI request boundary exceeded.');
        $response=wp_remote_post(self::ENDPOINT,['headers'=>['Authorization'=>'Bearer '.$apiKey,'Content-Type'=>'application/json','User-Agent'=>'VGT-Sentinel-AI-Gateway/1.0'],'body'=>$body,'timeout'=>self::PROFILES[$profile][0],'sslverify'=>true,'redirection'=>0,'limit_response_size'=>524288]);
        if(is_wp_error($response)){self::fail($profile);error_log('[VGT AI GATEWAY] '.$profile.' network failure.');return new WP_Error('network','AI upstream unavailable.');}
        $code=(int)wp_remote_retrieve_response_code($response);
        if($code!==200){self::fail($profile);error_log('[VGT AI GATEWAY] '.$profile.' HTTP '.$code);return new WP_Error('upstream','AI upstream rejected request.');}
        try{$data=json_decode((string)wp_remote_retrieve_body($response),true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){self::fail($profile);return new WP_Error('decode','AI response rejected.');}
        if(!is_array($data)||!is_string($data['choices'][0]['message']['content']??null)){self::fail($profile);return new WP_Error('schema','AI response schema rejected.');}
        delete_transient('vgt_ai_circuit_'.$profile);
        if(class_exists('VIS_Event_Bus'))VIS_Event_Bus::emit('AI_GATEWAY','UPSTREAM_OK','AI request completed.',['profile'=>$profile],1);
        return $data;
    }
    private static function messages(mixed $messages): array {
        if(!is_array($messages)||$messages===[]||count($messages)>12)throw new InvalidArgumentException('AI message validation failed.');
        $safe=[];foreach($messages as $message){if(!is_array($message)||!in_array($message['role']??'', ['system','user','assistant'],true))throw new InvalidArgumentException('AI role validation failed.');$content=(string)($message['content']??'');if(strlen($content)>131072)throw new InvalidArgumentException('AI message boundary exceeded.');$safe[]=['role'=>$message['role'],'content'=>self::redact($content)];}return $safe;
    }
    private static function redact(string $value): string {return preg_replace(['/(Authorization\s*:\s*Bearer\s+)[A-Za-z0-9._-]+/i','/(api[_-]?key|secret|password|token)\s*[=:]\s*[^\s,]+/i'],'$1[REDACTED]',$value)??$value;}
    private static function fail(string $profile): void {$key='vgt_ai_circuit_'.$profile;set_transient($key,(int)get_transient($key)+1,300);}
}
