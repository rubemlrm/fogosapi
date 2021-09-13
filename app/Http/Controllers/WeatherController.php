<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Redis;
use Laravel\Lumen\Routing\Controller;

class WeatherController extends Controller
{
    public static function getMeteoByLatAndLng($lat, $lng)
    {
        if (env('APP_ENV') === 'production') {
            $exists = Redis::get('weather:' . $lat . ':' . $lng);
            if ($exists) {
                return json_decode($exists, true);
            } else {
                $client = self::getClient();
                $weatherUrl = self::$weatherUrl . 'lat=' . $lat . '&lon=' . $lng . '&APPID=' . env('OPENWEATHER_API') . '&units=metric&lang=pt';

                try {
                    $response = $client->request('GET', $weatherUrl);

                } catch (ClientException $e) {
                    return ['error' => $e->getMessage()];
                } catch (RequestException $e) {
                    return ['error' => $e->getMessage()];
                }

                $body = $response->getBody();
                $result = json_decode($body->getContents(), true);

                Redis::set('weather:' . $lat . ':' . $lng, json_encode($result), 'EX', 10800);
                return $result;
            }
        }
    }
}