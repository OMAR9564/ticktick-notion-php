<?php

namespace App\Controllers;

class BackgroundTask extends \CodeIgniter\Controller
{
      public function syncData()
      {
         // API'den veri çekme işlemi
         $apiUrl = 'https://ticktick.omaralfarouk.com';
         $response = file_get_contents($apiUrl);

         if ($response === false) {
               // Hata durumunda log kaydı
               log_message('error', 'API isteği başarısız.');
               return "API isteği başarısız!";
         }

      }
}
