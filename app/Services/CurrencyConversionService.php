<?php

namespace App\Services;

use App\Traits\ConsumesExternalServices;
use Illuminate\Http\Request;

class CurrencyConversionService
{
    use ConsumesExternalServices;

    protected $baseUri;

    protected $apiKey;


    public function __construct()
    {
        $this->baseUri = config('services.currency_conversion.base_uri');
        $this->apiKey = config('services.currency_conversion.api_key');
    }

    // public function resolveAuthorization(&$queryParams, &$formParams, &$headers) {}

    public function decodeResponse($response)
    {
        return json_decode($response);
    }

    public function resolveAccessToken()
    {
        return $this->apiKey;
    }

    public function convertCurrency($from, $to)
    {
        $response = $this->makeRequest(
            'GET',
            "/v6/{$this->resolveAccessToken()}/pair/{$from}/{$to}",
        );

        return $response->conversion_rate;
    }
}
