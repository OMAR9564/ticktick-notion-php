<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class BackgroundTask extends Controller
{
    public function syncData()
    {
        if (!is_cli()) {
            return "Bu komut yalnızca CLI ortamında çalıştırılabilir.";
        }

        // CodeIgniter HTTP Client'ını kullanarak API çağrısı
        $client = \Config\Services::curlrequest(); // HTTP Client oluştur

        $apiUrl = 'https://ticktick.omaralfarouk.com/sync';

        try {
            // API'ye GET isteği gönder
            $response = $client->request('GET',$apiUrl, [
               'headers' => [
                   'Connection' => "keep-alive",
                   'Accept-Encoding' => 'gzip, deflate, br',
                   'Accept' => '*/*'
               ]]);

            // HTTP durum kodunu kontrol et
            $statusCode = $response->getStatusCode(); // Örn: 200, 500, vb.

            if ($statusCode === 200) {
                $body = $response->getBody(); // Gelen yanıt içeriği
                log_message('info', 'API isteği başarılı: ' . $body);
                echo "API isteği başarılı!\n";
                return $body;
            } else {
                log_message('error', "API isteği başarısız. HTTP Kod: $statusCode");
                return "API isteği başarısız. HTTP Kod: $statusCode";
            }
        } catch (\Exception $e) {
            // Hata durumunda log kaydı ve hata mesajı
            log_message('error', 'API isteği sırasında bir hata oluştu: ' . $e->getMessage());
            return "API isteği sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}
