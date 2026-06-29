<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RenphoService
{
    private const BASE_URL = 'https://cloud.renpho.com';

    private function encryptAes(string $content): string
    {
        $key       = config('services.renpho.aes_key');
        $encrypted = openssl_encrypt($content, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);

        if ($encrypted === false) {
            throw new RuntimeException('Renpho : échec du chiffrement AES');
        }

        return base64_encode($encrypted);
    }

    private function decryptAes(string $encryptedBase64): string
    {
        $key       = config('services.renpho.aes_key');
        $decrypted = openssl_decrypt(base64_decode($encryptedBase64), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);

        if ($decrypted === false) {
            throw new RuntimeException('Renpho : échec du déchiffrement AES');
        }

        return $decrypted;
    }

    private function postEncrypted(string $path, array $headers, array $body): array
    {
        $response = Http::withHeaders($headers)
            ->post(self::BASE_URL . '/' . $path, [
                'encryptData' => $this->encryptAes(json_encode($body)),
            ]);

        $response->throw();

        $json = $response->json();

        if (($json['code'] ?? null) !== 101) {
            throw new RuntimeException('Renpho API error on ' . $path . ': ' . ($json['msg'] ?? 'unknown'));
        }

        return json_decode($this->decryptAes($json['data']), true);
    }

    private function postEncryptedEmpty(string $path, array $headers): array
    {
        $key       = config('services.renpho.aes_key');
        $empty     = openssl_encrypt('', 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $encrypted = base64_encode($empty ?: '');

        $response = Http::withHeaders($headers)
            ->post(self::BASE_URL . '/' . $path, [
                'encryptData' => $encrypted,
            ]);

        $response->throw();

        $json = $response->json();

        if (($json['code'] ?? null) !== 101) {
            throw new RuntimeException('Renpho API error on ' . $path . ': ' . ($json['msg'] ?? 'unknown'));
        }

        return json_decode($this->decryptAes($json['data']), true);
    }

    public function authenticate(string $email, string $password): array
    {
        $scales = [
            '01','02','03','04','05','06','07','08','09','0A',
            '0B','0C','0D','0E','0F','10','11','12','13','14',
        ];

        $loginPayload = [
            'questionnaire' => new \stdClass(),
            'login'         => [
                'password'       => $password,
                'areaCode'       => '',
                'appRevision'    => '6.6.0',
                'cellphoneType'  => 'PythonScript',
                'systemType'     => '11',
                'email'          => $email,
                'platform'       => 'android',
            ],
            'bindingList' => ['deviceTypes' => $scales],
        ];

        $response = Http::post(self::BASE_URL . '/renpho-aggregation/user/login', [
            'encryptData' => $this->encryptAes(json_encode($loginPayload)),
        ]);

        $response->throw();

        $json = $response->json();

        if (($json['code'] ?? null) !== 101) {
            throw new RuntimeException('Renpho auth failed: ' . ($json['msg'] ?? 'unknown'));
        }

        $rawData = $this->decryptAes($json['data']);
        $userData = json_decode($rawData, true);
        $login    = $userData['login'];

        // Extract as string to avoid precision loss on large integers
        preg_match('/"id":(\d+)/', $rawData, $matches);
        $userId = $matches[1] ?? (string) $login['id'];
        $token  = $login['token'];

        $headers = [
            'token'      => $token,
            'userId'     => $userId,
            'appVersion' => '7.0.0',
            'platform'   => 'android',
        ];

        $deviceData    = $this->postEncryptedEmpty('renpho-aggregation/device/count', $headers);
        $rawDeviceJson = json_encode($deviceData);

        $scaleTables = [];
        foreach ($deviceData['scale'] ?? [] as $i => $scaleInfo) {
            preg_match_all('/"userIds":\[(\d+(?:,\d+)*)\]/', $rawDeviceJson, $uidMatches);
            $userIds = isset($uidMatches[1][$i])
                ? explode(',', $uidMatches[1][$i])
                : array_map('strval', $scaleInfo['userIds'] ?? []);

            $scaleTables[] = [
                'table_name' => $scaleInfo['tableName'],
                'count'      => (int) $scaleInfo['count'],
                'user_ids'   => $userIds,
            ];
        }

        if (empty($scaleTables)) {
            throw new RuntimeException('Renpho : aucune balance trouvée sur ce compte');
        }

        return [
            'token'        => $token,
            'user_id'      => $userId,
            'scale_tables' => $scaleTables,
        ];
    }

    public function fetchMeasurements(array $auth, int $lastUpdatedAt): array
    {
        $headers = [
            'token'      => $auth['token'],
            'userId'     => $auth['user_id'],
            'appVersion' => '7.0.0',
            'platform'   => 'android',
        ];

        $all      = [];
        $pageSize = 200;

        foreach ($auth['scale_tables'] as $scaleTable) {
            $count    = $scaleTable['count'];
            $lastPage = max(1, (int) ceil($count / $pageSize));

            for ($page = $lastPage; $page >= 1; $page--) {
                $data = $this->postEncrypted(
                    'RenphoHealth/scale/queryAllMeasureDataList',
                    $headers,
                    [
                        'pageNum'   => $page,
                        'pageSize'  => $pageSize,
                        'userIds'   => $scaleTable['user_ids'],
                        'tableName' => $scaleTable['table_name'],
                    ],
                );

                $entries = is_array($data) ? $data : [];

                if (empty($entries)) {
                    break;
                }

                $timestamps = array_map(fn($m) => (int) ($m['timeStamp'] ?? 0), $entries);
                $oldest     = min($timestamps);

                foreach ($entries as $entry) {
                    if ((int) ($entry['timeStamp'] ?? 0) > $lastUpdatedAt) {
                        $all[] = $entry;
                    }
                }

                if ($oldest <= $lastUpdatedAt) {
                    break;
                }
            }
        }

        return $all;
    }
}
