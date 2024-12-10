<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar;
use Exception;

class TickTickController extends BaseController
{
    private $ticktickClientId;
    private $ticktickClientSecret;
    private $redirectUri;
    private $notionToken;
    private $loginUrl;
    private $email;
    private $password;

    private $namedColors = [
        'blue'    => '#0000FF',
        'brown'   => '#A52A2A',
        'default' => '#FFFFFF', // Burada default'u beyaz kabul ettim, isterseniz değiştirin
        'gray'    => '#808080',
        'green'   => '#008000',
        'orange'  => '#FFA500',
        'pink'    => '#FFC0CB',
        'purple'  => '#800080',
        'red'     => '#FF0000',
        'yellow'  => '#FFFF00',
    ];

    public function __construct()
    {
        $this->loginUrl = 'https://ticktick.com/api/v2/user/signon?wc=true&remember=true';
        $this->ticktickClientId = getenv('TICKTICK_CLIENT_ID');
        $this->ticktickClientSecret = getenv('TICKTICK_CLIENT_SECRET');
        $this->redirectUri = getenv('TICKTICK_REDIRECT_URI');
        $this->notionToken = getenv('NOTION_API_TOKEN');
        $this->email = getenv('TICKTICK_EMAIL');
        $this->password = getenv('TICKTICK_PASSWORD');
    }

    // Ana sayfa: Listelerin çekildiği ve seçimin yapıldığı method
    public function index()
    {
        $accessToken = session()->get('ticktick_access_token');

        if (!$accessToken) {
            return redirect()->to('/ticktick/authenticate');
        }

        try {
            $lists = $this->getTickTickLists($accessToken);
            
            print_r($this->setTickTickListsToNotion($lists));
            exit;

            return view('ticktick_lists', ['lists' => $lists]);
        } catch (RequestException $e) {
            return view('error', ['message' => 'TickTick Listeleri alınamadı: ' . $e->getMessage()]);
        }
    }

    // Seçilen proje verilerini getirir
    public function showProjectData($projectId)
    {
        $accessToken = session()->get('ticktick_access_token');

        if (!$accessToken) {
            return redirect()->to('/ticktick/authenticate');
        }

        try {
            $projectData = $this->getProjectData($accessToken, $projectId);

            // JSON formatında ekrana yazdırılır
            return $this->response
                ->setContentType('application/json')
                ->setJSON($projectData);
        } catch (RequestException $e) {
            return view('error', ['message' => 'Proje verileri alınamadı: ' . $e->getMessage()]);
        }
    }

    // TickTick API'ye kullanıcıyı yönlendirir
    public function authenticate()
    {
        $authUrl = "https://ticktick.com/oauth/authorize?" . http_build_query([
            'scope' => 'tasks:read tasks:write',
            'client_id' => $this->ticktickClientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
        ]);

        return redirect()->to($authUrl);
    }

    // Callback işlemleri (Access Token alma)
    public function callback()
    {
        $code = $this->request->getGet('code');
        if (!$code) {
            return redirect()->to('/ticktick/authenticate');
        }

        try {
            $accessToken = $this->getAccessToken($code);
            session()->set('ticktick_access_token', $accessToken);

            return redirect()->to('/ticktick');
        } catch (RequestException $e) {
            return view('error', ['message' => 'Access Token alınamadı: ' . $e->getMessage()]);
        }
    }

    // TickTick Listelerini çeker
    private function getTickTickLists($accessToken)
    {
        $client = new Client();
        $response = $client->request('GET', 'https://api.ticktick.com/open/v1/project', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    // OAuth Access Token alır
    private function getAccessToken($code)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://ticktick.com/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id' => $this->ticktickClientId,
                'client_secret' => $this->ticktickClientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ],
        ]);

        $body = json_decode($response->getBody(), true);
        return $body['access_token'];
    }

    public function getProjectTasks($projectId)
    {
        // Access token'ı debug et
        $accessToken = session()->get('ticktick_access_token');
        log_message('info', 'Access Token: ' . $accessToken); // Token'ı logla

        if (!$accessToken) {
            log_message('error', 'No access token found');
            return redirect()->to('/ticktick/login')->with('error', 'Oturum açmanız gerekiyor.');
        }

        try {
            $client = new Client();

            // API çağrıları öncesi log ekle
            log_message('info', 'Fetching active tasks for project: ' . $projectId);

            $activeResponse = $client->get("https://api.ticktick.com/api/v2/project/$projectId/tasks", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ticktick.com/',
                    'Accept' => 'application/json',
                    'Origin' => 'https://ticktick.com',
                    'Cookie' => session()->get("ticktick_v2_access_cookie"),
                ],
            ]);

            
            $activeTasks = json_decode($activeResponse->getBody(), true);
            
            // Benzer şekilde completed tasks için de log ekle
            $completedResponse = $client->get("https://api.ticktick.com/api/v2/project/$projectId/completed", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ticktick.com/',
                    'Accept' => 'application/json',
                    'Origin' => 'https://ticktick.com',
                    'Cookie' => session()->get("ticktick_v2_access_cookie"),
                ],
            ]);

            $completedTasks = json_decode($completedResponse->getBody(), true);
            print_r($completedTasks);
            exit;
            return [
                'uncompleted' => $activeTasks['tasks'] ?? [],
                'completed' => $completedTasks['tasks'] ?? [],
            ];
        } catch (RequestException $e) {
            // Daha detaylı hata bilgisi
            log_message('error', 'API Request Error: ' . $e->getMessage());
            log_message('error', 'Error Response: ' . $e->getResponse()->getBody());

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();

                if ($statusCode === 401 && strpos($responseBody, '"errorCode":"user_not_sign_on"') !== false) {
                    return redirect()->to('/ticktick/login')->with('error', 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.');
                }
            }

            return view('error', ['message' => 'Görevler alınamadı: ' . $e->getMessage()]);
        }
    }

    public function login()
    {
        try {
            $cookieJar = new CookieJar();

            $client = new Client();
            $response = $client->post($this->loginUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ticktick.com/',
                    'Accept' => 'application/json',
                    'Origin' => 'https://ticktick.com',
                    'X-Device' => '{"platform":"web","os":"Windows 10","device":"Firefox 133.0","name":"","version":6116,"id":"6707b3c671fc7f7b20499be8","channel":"website","campaign":"","websocket":""}'
                ],
                'json' => [
                    'username' => $this->email,
                    'password' => $this->password
                ],
                'verify' => false,
                'cookies' => $cookieJar
            ]);
            
            $body = json_decode($response->getBody(), true);
            $cookies = $response->getHeader("Set-Cookie");
            // Cookie değerlerini birleştir
            $cookieString = '';
            if (!empty($cookies)) {
                foreach ($cookies as $cookie) {
                    $cookieString .= $cookie . '; ';
                }
            }
            session()->set('ticktick_v2_access_cookie', $cookieString);

            if (!empty($body['token'])) {
                session()->set('ticktick_v2_access_token', $body['token']);
                return redirect()->to('/')->with('success', 'Giriş başarılı!');
            } else {
                return redirect()->to('/')->with('error', 'Giriş başarısız oldu. Lütfen bilgilerinizi kontrol edin.');
            }
        } catch (RequestException $e) {
            // Hata ayrıntılarını logla
            log_message('error', 'TickTick Login Error: ' . $e->getMessage());
            if ($e->hasResponse()) {
                log_message('error', 'Response Body: ' . $e->getResponse()->getBody());
            }

            return redirect()->to('/')->with('error', 'Login işlemi sırasında hata oluştu: ' . $e->getMessage());
        }
    }

    private function loadMappings()
    {
        $jsonPath = WRITEPATH . '/data/mappings.json'; // JSON dosyasının yolu
        if (!file_exists($jsonPath)) {
            throw new \Exception('Mapping JSON dosyası bulunamadı.');
        }

        $jsonData = file_get_contents($jsonPath);
        return json_decode($jsonData, true);
    }

    private function addToNotion($databaseId, $task)
    {
        $notionToken = getenv('NOTION_TOKEN');
        $client = new Client();

        $response = $client->post("https://api.notion.com/v1/pages", [
            'headers' => [
                'Authorization' => 'Bearer ' . $notionToken,
                'Content-Type' => 'application/json',
                'Notion-Version' => '2022-06-28',
            ],
            'json' => [
                'parent' => ['database_id' => $databaseId],
                'properties' => [
                    'Name' => ['title' => [['text' => ['content' => $task['title']]]]],
                    'Status' => ['select' => ['name' => $task['status']]],
                    'Due Date' => ['date' => ['start' => $task['dueDate'] ?? null]],
                ],
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function syncTasks()
    {
        $mappings = $this->loadMappings();
        $accessToken = session()->get('ticktick_access_token');

        if (!$accessToken) {
            throw new \Exception('TickTick erişim tokeni bulunamadı.');
        }

        foreach ($mappings as $notionDatabaseId => $ticktickListId) {
            $ticktickTasks = $this->getTickTickTasks($accessToken, $ticktickListId);
            $localData = $this->loadLocalData($notionDatabaseId); // JSON'dan alınır

            // Tamamlanmış görevleri kontrol et
            foreach ($ticktickTasks['completed'] as $task) {
                if (!isset($localData['completed'][$task['id']])) {
                    $this->addToNotion($notionDatabaseId, $task); // Notion'a ekle
                    $localData['completed'][$task['id']] = $task;
                }
            }

            // Tamamlanmamış görevleri kontrol et
            foreach ($ticktickTasks['uncompleted'] as $task) {
                if (!isset($localData['uncompleted'][$task['id']])) {
                    $this->addToNotion($notionDatabaseId, $task);
                    $localData['uncompleted'][$task['id']] = $task;
                }
            }

            // JSON'u güncelle
            $this->saveLocalData($notionDatabaseId, $localData);
        }
    }
    private function saveLocalData($databaseId, $data)
    {
        $filePath = WRITEPATH . "data/$databaseId.json";
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function loadLocalData($databaseId)
    {
        $filePath = WRITEPATH . "data/$databaseId.json";
        if (!file_exists($filePath)) {
            return ['completed' => [], 'uncompleted' => []];
        }
    
        $data = file_get_contents($filePath);
        return json_decode($data, true);
    }

    private function setTickTickListsToNotion($lists){
        $result = ["success" => [], "errors" => []];
    
        if ($lists) {
            $url = "https://api.notion.com/v1/databases/1580ebdd798c809b8db4d4da56a193f2";
    
            try {
                // Mevcut seçenekleri almak için GET isteği
                $client = new Client();
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'Authorization' => "Bearer $this->notionToken",
                        'Content-Type' => 'application/json',
                        'Notion-Version' => '2022-06-28'
                    ]
                ]);
    
                $httpCode = $response->getStatusCode();
                if ($httpCode >= 200 && $httpCode < 300) {
                    $responseData = json_decode($response->getBody(), true);
                    $existingOptions = $responseData['properties']['Category']['select']['options'] ?? [];
                } else {
                    throw new Exception("HTTP Hatası: $httpCode, Mevcut seçenekler alınamadı.");
                }
    
                // Mevcut seçenekleri sakla
                $existingOptionsMap = array_column($existingOptions, null, 'name');
    
                $newOptions = [];
    
                foreach ($lists as $list) {
                    $notionCategoryId = $list["id"];
                    $notionCategoryText = $list["name"];
                    // $notionCategoryColor = $this->getClosestColorName($list["color"] ?? "");
    
                    // Eğer Category_Id mevcutsa atla
                    if (isset($existingOptionsMap[$notionCategoryId])) {
                        continue;
                    }
    
                    // Yeni seçenek oluştur ve ekle
                    $newOptions[] = [
                        "name" => $notionCategoryText,
                        "color" => "default"
                    ];
                }
    
                // Mevcut ve yeni seçenekleri birleştir
                $allOptions = array_merge($existingOptions, $newOptions);
    
                // Güncellenmiş seçeneklerle PATCH isteği gönder
                $data = [
                    "properties" => [
                        "Category" => [
                            "select" => [
                                "options" => $allOptions,
                            ]
                        ]
                    ]
                ];
    
                $patchResponse = $client->request('PATCH', $url, [
                    'headers' => [
                        'Authorization' => "Bearer $this->notionToken",
                        'Content-Type' => 'application/json',
                        'Notion-Version' => '2022-06-28'
                    ],
                    'json' => $data
                ]);
    
                $patchHttpCode = $patchResponse->getStatusCode();
                if ($patchHttpCode >= 200 && $patchHttpCode < 300) {
                    $result["success"][] = $this->getClosestColorName($lists[0]["color"]);
                } else {
                    throw new Exception("HTTP Hatası: $patchHttpCode, Seçenekler güncellenemedi.");
                }
            } catch (Exception $e) {
                $result["errors"][] = ["error" => $e->getMessage()];
            }
        } else {
            $result["errors"][] = ["message" => "Listeler boş veya geçersiz."];
        }
    
        return $result;
    }

    /**
     * Hex kodunu R,G,B olarak döndürür.
     */
    private function hexToRgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
            // Kısa hex formatını uzat (#fff -> #ffffff)
            $hex = str_repeat($hex[0], 2).str_repeat($hex[1], 2).str_repeat($hex[2], 2);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    
        return [$r, $g, $b];
    }
    
    /**
     * İki renk (R,G,B) formatında arası Öklid mesafesi hesaplanır.
     */
    private function colorDistance($rgb1, $rgb2) {
        return sqrt(pow($rgb1[0] - $rgb2[0], 2) + pow($rgb1[1] - $rgb2[1], 2) + pow($rgb1[2] - $rgb2[2], 2));
    }
    
    /**
     * Verilen input hex rengine en yakın isimlendirilmiş rengi bulur.
     */
    private function getClosestColorName($inputHex) {
        if($inputHex){
            $inputRgb = $this->hexToRgb($inputHex);
            $closestName = null;
            $minDistance = PHP_FLOAT_MAX;
            $namedColors = $this->namedColors;

            foreach ($namedColors as $name => $hex) {
                $colorRgb = $this->hexToRgb($hex);
                $dist = $this->colorDistance($inputRgb, $colorRgb);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                    $closestName = $name;
                }
            }

            return $closestName;
        }else{
            return "default";
        }
    }
}
