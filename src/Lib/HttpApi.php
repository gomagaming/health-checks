<?php

namespace GomaGaming\HealthChecks\Lib;

use Illuminate\Support\Facades\Http;

class HttpApi
{
    protected $url;

    public function __construct($url = '')
    {
        $this->url = $url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function newRequest($method, $endpoint, $params = [])
    {
        $url = $this->url . DIRECTORY_SEPARATOR . $endpoint;

        $headers = array();
        $headers['Accept'] = 'application/json';

        $httpRequest = HTTP::withHeaders($headers)
                ->withToken(base64_encode(config('gomagaminglogs.jira.user_email') . ':' . config('gomagaminglogs.jira.user_api_token')), 'Basic')
                ->withBody(json_encode($params), 'application/json');

        if ($method == 'POST') {
            $response = $httpRequest->post($url, $params);
        }elseif ($method == 'PUT') {
            $response = $httpRequest->put($url, $params); 
        }elseif ($method == 'DELETE') {
            $response = $httpRequest->delete($url, $params); 
        }elseif ($method == 'PATCH') {
            $response = $httpRequest->patch($url, $params);  
        }elseif ($method == 'GET') {
            $response = $httpRequest->get($url, $params);
        }

        if (!$response->successful()) {
            throw new \Exception("Error processing GomaGamingLogs Http request");
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
