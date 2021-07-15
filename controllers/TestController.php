<?php

namespace app\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\web\Controller;
use yii\web\Response;

class TestController extends Controller
{
    private $client = null;
    private $baseUrl = 'http://api.jakarta.go.id/v1/';
    private $method = 'GET';
    private $token = 'a53f2f8bfd99bb78ad7c0de42d80a362cd2caacc0ffeeb2a2e3f84b9c27d142f';
    private $auth = 'LdT23Q9rv8g9bVf8v/fQYsyIcuD14svaYL6Bi8f9uGhLBVlHA3ybTFjjqe+cQO8k';

    const END_POINT_RUMAH_SAKIT_UMUM = 'rumahsakitumum';
    const END_POINT_KELURAHAN = 'kelurahan';

    /**
     * Endpoint to get the result of combine API RS and Kelurahan
     * @return array
     */
    public function actionRsJakarta()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if ($this->validateSignature()) {
            $rs = $this->getRsu();
            $kelurahan = $this->getKelurahan();

            foreach ($rs as &$value) {
                $key = array_search(
                    $value['kode_kelurahan'],
                    array_column($kelurahan, 'kode_kelurahan')
                );
                $data = $kelurahan[$key];

                ArrayHelper::remove($value, 'kode_kota');
                ArrayHelper::remove($value, 'kode_kecamatan');
                ArrayHelper::remove($value, 'kode_kelurahan');

                $result = [
                    'kelurahan' => [
                        'kode' => $data['kode_kelurahan'],
                        'nama' => $data['nama_kelurahan']
                    ],
                    'kecamatan' => [
                        'kode' => $data['kode_kecamatan'],
                        'nama' => $data['nama_kecamatan']
                    ],
                    'kota' => [
                        'kode' => $data['kode_kota'],
                        'nama' => $data['nama_kota']
                    ],
                ];

                $value = ArrayHelper::merge($value, $result);
            }

            return $this->response($rs);
        }

        return [
            'status' =>'failed',
            'message' =>'Invalid request',
        ];
    }

    /**
     * Validate incoming request before starting process
     * @return bool
     */
    private function validateSignature()
    {
        $signature = Yii::$app->request->headers->get('Signature');

        return $signature === $this->token;
    }

    /**
     * Get data rumah sakit umum from API
     * @return false|mixed
     * @throws \yii\httpclient\Exception
     */
    private function getRsu()
    {
        $response = $this->getClient()
            ->setUrl(self::END_POINT_RUMAH_SAKIT_UMUM);

        if ($result = $response->send()) {
            $result = $result->getData();
        }

        return $result['status'] === 'success' ? $result['data'] : false;
    }

    /**
     * Setup base http client for request via API
     * @return \yii\httpclient\Request
     * @throws \yii\base\InvalidConfigException
     */
    private function getClient()
    {
        if ($this->client === null) {
            $this->client = new Client(['baseUrl' => $this->baseUrl]);
        }

        return $this->client
            ->createRequest()
            ->setMethod($this->method)
            ->setFormat(Client::FORMAT_JSON)
            ->setHeaders([
                'Authorization' => $this->auth,
                'Content-Type' => 'application/json'
            ]);
    }

    /**
     * Get data kelurahan from API
     * @return false|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    private function getKelurahan()
    {
        $response = $this->getClient()
            ->setUrl(self::END_POINT_KELURAHAN);

        if ($result = $response->send()) {
            $result = $result->getData();
        }

        return $result['status'] === 'success' ? $result['data'] : false;
    }

    /**
     * Setup request response based on result of combine data rumah sakit umum and kelurahan
     * @param $data
     * @return array
     */
    private function response($data)
    {
        $response = [
            'status' => 'failed',
            'count' => 0,
            'data' => null
        ];

        if (!empty($data)) {
            $response = [
                'status' => 'success',
                'count' => count($data),
                'data' => $data
            ];
        }

        return $response;
    }
}