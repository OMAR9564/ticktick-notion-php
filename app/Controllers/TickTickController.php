<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
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
    private $notionDatabasedId;

    private $namedColors = [
        'blue'    => '#0000FF',
        'brown'   => '#A52A2A',
        'default' => '#FFFFFF',
        'gray'    => '#808080',
        'green'   => '#008000',
        'orange'  => '#FFA500',
        'pink'    => '#FFC0CB',
        'purple'  => '#800080',
        'red'     => '#FF0000',
        'yellow'  => '#FFFF00',
    ];

    private $statusFile = WRITEPATH . 'sync_status.json';

    /*
        My reason for using accessTokenFile and ticktickV2AccessTokenFile is 
        that it works without any issues on localhost because authentication is 
        done through the interface.
        However, for it to run continuously in the background on hosting, 
        I need to use a cron job.
        For this reason, I decided to store the authentication data in JSON after 
        first doing the authentication through the interface, since there won't be 
        a session.
        The reason for keeping them in separate files is that it was optional.
    */
    private $accessTokenFile = WRITEPATH . 'access_token.json';
    private $ticktickV2AccessTokenFile = WRITEPATH . 'ticktick_v2_access_token.json';

    public function __construct()
    {
        $this->loginUrl = 'https://ticktick.com/api/v2/user/signon?wc=true&remember=true';
        $this->ticktickClientId = getenv('TICKTICK_CLIENT_ID');
        $this->ticktickClientSecret = getenv('TICKTICK_CLIENT_SECRET');
        $this->redirectUri = getenv('TICKTICK_REDIRECT_URI');
        $this->notionToken = getenv('NOTION_API_TOKEN');
        $this->email = getenv('TICKTICK_EMAIL');
        $this->password = getenv('TICKTICK_PASSWORD');
        $this->notionDatabasedId = getenv('NOTION_DATABASE_ID');

        if (!file_exists($this->accessTokenFile)) {
            file_put_contents($this->accessTokenFile, json_encode(['access_token' => 0]));
        }

        if (!file_exists($this->ticktickV2AccessTokenFile)) {
            file_put_contents($this->ticktickV2AccessTokenFile, json_encode(['ticktick_v2_access_cookie' => 0]));
        }
    }

    private function getSavedAccessToken()
    {
        $accessToken = session()->get('ticktick_access_token');

        if (!$accessToken) {
            $accessTokenData = @json_decode(file_get_contents($this->accessTokenFile), true);
            $accessToken = $accessTokenData['access_token'] ?? null;
        }

        return $accessToken;
    }

    //It will perform a status check for cron jobs and sync accordingly
    public function checkStatus(): string
    {
        if (!file_exists($this->statusFile)) {
            file_put_contents($this->statusFile, json_encode(['status' => 0]));
        }

        $status = json_decode(file_get_contents($this->statusFile), true);
        
        return $status["status"] === 0 ? "false" : "true";
    }

    public function index(): \CodeIgniter\HTTP\Response
    {
        try {
            // Servis durumunu kontrol et
            if ($this->checkStatus() !== "true") {
                throw new Exception("Servis şu anda aktif değil");
            }

            // Access token'ı al ve kontrol et
            $accessToken = $this->getSavedAccessToken();
            if (empty($accessToken)) {
                return $this->response->setStatusCode(401)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.'
                    ]);
            }

            // TickTick listelerini getir
            $lists = $this->getTickTickLists($accessToken);
            if (empty($lists)) {
                throw new Exception("TickTick listeleri alınamadı");
            }

            // Başarılı sonucu oluştur ve logla
            $result = [
                'status' => 'success',
                'message' => 'Senkronizasyon başarıyla tamamlandı',
                'data' => [
                    'list_message' => $this->setTickTickListsToNotion($lists)['success'],
                    'task_messages' => $this->addTasksOfListsToNotion($lists)['success']
                ]
            ];

            log_message('info', 'TickTick senkronizasyonu başarılı', [
                'list_count' => count($lists),
                'sync_result' => $result
            ]);

            return $this->response->setJSON($result);

        } catch (RequestException $e) {
            log_message('error', 'TickTick API hatası: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'TickTick servisi ile iletişim sırasında bir hata oluştu',
                    'detail' => $e->getMessage()
                ]);

        } catch (Exception $e) {
            log_message('error', 'Genel hata: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'İşlem sırasında bir hata oluştu',
                    'detail' => $e->getMessage()
                ]);
        }
    }

    private function getTickTickLists($accessToken)
    {
        $client = new Client();
        $response = $client->request('GET', 'https://api.ticktick.com/open/v1/project', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
        unset($client);
        log_message('warning', "Basarili bir sekilde Listeler alindi");

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
        unset($client);
        $body = json_decode($response->getBody(), true);
        return $body['access_token'];
    }

    public function getListTasks($listId)
    {
        $accessToken = $this->getSavedAccessToken();

        if (!$accessToken) {
            return redirect()->to('/ticktick/authenticate');
        }

        try {
            $client = new Client();

            // API çağrıları öncesi log ekle
            log_message('info', 'Fetching active tasks for project: ' . $listId);

            $ticktickV2AccessCookie = json_decode(file_get_contents($this->ticktickV2AccessTokenFile), true)["ticktick_v2_access_cookie"];

            $activeResponse = $client->get("https://api.ticktick.com/api/v2/project/$listId/tasks", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ticktick.com/',
                    'Accept' => 'application/json',
                    'Origin' => 'https://ticktick.com',
                    'Cookie' => $ticktickV2AccessCookie,
                ],
            ]);

            $activeTasks = json_decode($activeResponse->getBody(), true);

            // Status 2 olan görevleri activeTasks'dan temizle
            $activeTasks = array_filter($activeTasks, function($task) {
                return !isset($task['status']) || $task['status'] != 2;
            });

            // Benzer şekilde completed tasks için de log ekle
            $completedResponse = $client->get("https://api.ticktick.com/api/v2/project/$listId/completed", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ticktick.com/',
                    'Accept' => 'application/json',
                    'Origin' => 'https://ticktick.com',
                    'Cookie' => $ticktickV2AccessCookie,
                ],
            ]);
            unset($client);
            $completedTasks = json_decode($completedResponse->getBody(), true);
            
            $allTasks = array_merge($activeTasks ?? [], $completedTasks ?? []);

            $allTasks = [
                'allTasks' => $allTasks ?? [],
            ];
            log_message('warning', "Basarili bir sekilde Listelerin tasklari alindi");

            return $allTasks;

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

    private function setTickTickListsToNotion($lists) {
        $status = $this->checkStatus();
        if ($status === "true") {
        
            $result = ["success" => [], "errors" => []];
        
            if ($lists) {
                $url = "https://api.notion.com/v1/databases/".$this->notionDatabasedId;
        
                try {
                    $client = new Client();
                    $response = $client->request('GET', $url, [
                        'headers' => [
                            'Authorization' => "Bearer $this->notionToken",
                            'Content-Type' => 'application/json',
                            'Notion-Version' => '2022-06-28'
                        ]
                    ]);
                    log_message('warning', "Basarili bir sekilde Notiondan Listeler alindi");

                    $httpCode = $response->getStatusCode();
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $responseData = json_decode($response->getBody(), true);
                        $existingOptions = $responseData['properties']['Category']['select']['options'] ?? [];
                    } else {
                        throw new Exception("HTTP Hatası: $httpCode, Mevcut seçenekler alınamadı.");
                    }
        
                    $newOptions = [];
                    // Seçenekleri güncelleme işlemi
                    foreach ($lists as $list) {
                        $notionCategoryId = $list["id"];
                        $notionCategoryText = $list["name"];
                        $notionCategoryColor = $this->getClosestColorName($list["color"] ?? "default");

                        $found = false;

                        // Mevcut seçeneklerde arayın
                        foreach ($existingOptions as $key => $existingOption) {
                            if ($existingOption['description'] === $notionCategoryId) {
                                // Aynı ID ile mevcut bir seçenek varsa, güncelle
                                unset($existingOptions[$key]['id']);
                                $existingOptions[$key]['name'] = $notionCategoryText;
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            // Yeni bir seçenek oluştur
                            $newOptions[] = [
                                "name" => $notionCategoryText,
                                "color" => $notionCategoryColor,
                                "description" => $notionCategoryId,
                            ];
                        }
                    }

                    
                    $allOptions = array_merge($existingOptions, $newOptions);
                    
                    // Güncellenmiş seçeneklerle PATCH isteği gönderin
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
                    
                    log_message('warning', "Başarılı bir şekilde Notion'a listeler gönderildi.");

                    $patchHttpCode = $patchResponse->getStatusCode();
                    if ($patchHttpCode >= 200 && $patchHttpCode < 300) {
                        $result["success"][] = ["list_message" => "Listeler ve görevler başarıyla Notion'a gönderildi."];
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
    }

    private function addTasksOfListsToNotion(array $lists): array
    {
        $result = ["success" => [], "errors" => []];
        $client = new Client();
        $groupSize = 10; // Görev grubu boyutu
        $maxExecutionTime = 20; // Maksimum çalışma süresi (saniye)
        $waitTime = 5; // Bekleme süresi (saniye)

        log_message('info', 'addTasksOfListsToNotion işlemine başlandı.');
        $startTime = microtime(true);

        try {
            $today = date('Y-m-d'); // Bugünün tarihi

            foreach ($lists as $list) {
                log_message('info', "Liste işleniyor: {$list['name']}");
                
                // Mevcut Notion görevlerini al
                $existingTasks = $this->getExistingTasksFromNotion($list['name']);

                // Bugünün görevlerini belirle
                $listTasks = $this->getListTasks($list["id"]);
                if (empty($listTasks)) {
                    log_message('info', "Liste boş: {$list['name']}");
                    continue;
                }

                $allTasks = $listTasks['allTasks'];
                $todayTasks = array_filter($allTasks, function ($task) use ($today) {
                    return date('Y-m-d', strtotime($task['modifiedTime'])) === $today;
                });

                // Mevcut görevleri `allTasks` ve `todayTasks`'tan çıkar
                $filteredAllTasks = array_filter($allTasks, function ($task) use ($existingTasks) {
                    return !isset($existingTasks[$task['id']]);
                });


                // Notion'daki mevcut görevleri sil
                foreach ($todayTasks as $task) {
                    $tickTickId = $task['id'] ?? '';
                    if ($tickTickId && isset($existingTasks[$tickTickId])) {
                        $this->deleteTasksByTickTickIdFromNotion($tickTickId);
                        log_message('info', "Notion'dan silindi: {$task['title']}");
                    }
                    
                }

                $finalTasks = array_merge($todayTasks, $filteredAllTasks);

                // Görevleri yeniden ekle
                $taskGroups = array_chunk($finalTasks, $groupSize);

                foreach ($taskGroups as $groupIndex => $taskGroup) {
                    log_message('info', "Görev grubu işleniyor. Grup: {$groupIndex}, Görev sayısı: " . count($taskGroup));

                    $requests = function () use ($taskGroup, $list) {
                        foreach ($taskGroup as $task) {
                            $priorityName = match ($task['priority'] ?? "") {
                                "1" => "Low",
                                "3" => "Medium",
                                "5" => "High",
                                default => "None",
                            };

                            $taskData = [
                                "parent" => ["type" => "database_id", "database_id" => $this->notionDatabasedId],
                                "properties" => [
                                    "Name" => ["title" => [["type" => "text", "text" => ["content" => $task['title']]]]],
                                    "Created Time" => ["date" => ["start" => date('Y-m-d\TH:i:s.000\Z', strtotime($task['createdTime']))]],
                                    "Modified Time" => ["date" => ["start" => date('Y-m-d\TH:i:s.000\Z', strtotime($task['modifiedTime']))]],
                                    "Priority" => ["select" => ["name" => $priorityName]],
                                    "Category" => ["select" => ["name" => $list["name"]]],
                                    "Status" => ["select" => ["name" => $task["status"] == "0" ? "Uncomplate" : "Complate"]],
                                    "Ticktick Id" => ["rich_text" => [["type" => "text", "text" => ["content" => $task['id'] ?? '']]]],
                                ],
                                "children" => [[
                                    "object" => "block",
                                    "paragraph" => [
                                        "rich_text" => [["type" => "text", "text" => ["content" => $task['content'] ?? '']]]
                                    ]
                                ]]
                            ];

                            yield new Request(
                                'POST',
                                'https://api.notion.com/v1/pages',
                                [
                                    'Authorization' => "Bearer $this->notionToken",
                                    'Content-Type' => 'application/json',
                                    'Notion-Version' => '2022-06-28'
                                ],
                                json_encode($taskData)
                            );
                        }
                    };

                    $pool = new Pool($client, $requests(), [
                        'concurrency' => 5,
                        'fulfilled' => function ($response, $index) use (&$result, $taskGroup, $list) {
                            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                                $result["success"][] = ["list" => $list["name"], "task" => $taskGroup[$index]['title']];
                            }
                        },
                        'rejected' => function ($reason, $index) use (&$result, $taskGroup, $list) {
                            $errorMessage = $reason instanceof RequestException ? $reason->getMessage() : $reason;
                            $result["errors"][] = ["list" => $list["name"], "task" => $taskGroup[$index]['title'], "error" => $errorMessage];
                        }
                    ]);

                    $pool->promise()->wait();
                    log_message('info', "Görev grubu tamamlandı. Grup: {$groupIndex}");

                    // Çalışma süresi kontrolü
                    if ((microtime(true) - $startTime) >= $maxExecutionTime) {
                        sleep($waitTime);
                        $startTime = microtime(true);
                    }
                }
            }
        } catch (Exception $e) {
            log_message('error', $e->getMessage());
        }

        return $result;
    }

    private function deleteTasksByTickTickIdFromNotion($tickTickId)
    {
        $client = new Client();
        try {
            $response = $client->request('POST', "https://api.notion.com/v1/databases/".$this->notionDatabasedId."/query", [
                'headers' => [
                    'Authorization' => "Bearer $this->notionToken",
                    'Content-Type' => 'application/json',
                    'Notion-Version' => '2022-06-28'
                ],
                'json' => [
                    'filter' => [
                        'property' => 'Ticktick Id',
                        'rich_text' => [
                            'equals' => $tickTickId
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $pages = $data['results'] ?? [];

            if (empty($pages)) {
                log_message('info', "No pages found for TickTick ID: $tickTickId");
                return false;
            }

            
            foreach ($pages as $page) {
                $pageId = $page['id'];
                $this->deleteTaskFromNotion($pageId);
            }
        } catch (Exception $e) {
            log_message('error', "Silme hatası: {$e->getMessage()}");
        }
    }

    private function deleteTaskFromNotion($pageId)
    {
        $client = new Client();
        try {
            $client->request('PATCH', "https://api.notion.com/v1/pages/{$pageId}", [
                'headers' => [
                    'Authorization' => "Bearer $this->notionToken",
                    'Notion-Version' => '2022-06-28'
                ],
                'json' => [
                    'archived' => true,
                    'in_trash' => true
                ]
            ]);
            log_message('info', "Notion'dan görev silindi: {$pageId}");
        } catch (Exception $e) {
            log_message('error', "Silme hatası: {$e->getMessage()}");
        }
    }

    private function getExistingTasksFromNotion($categoryName)
    {
        $client = new Client();
        $existingTasks = [];
        $url = "https://api.notion.com/v1/databases/".$this->notionDatabasedId."/query";
        $hasMore = true; // Sayfalama kontrolü
        $startCursor = null; // Sayfanın başlangıcı

        try {
            while ($hasMore) {
                // API isteği parametreleri
                $options = [
                    'headers' => [
                        'Authorization' => "Bearer $this->notionToken",
                        'Content-Type' => 'application/json',
                        'Notion-Version' => '2022-06-28'
                    ],
                    'json' => [
                        'filter' => [
                            'property' => 'Category',
                            'select' => [
                                'equals' => $categoryName
                            ]
                        ]
                    ]
                ];
                
                // Sayfanın başlangıç değerini ekle
                if ($startCursor) {
                    $options['json']['start_cursor'] = $startCursor;
                }
                
                // API isteği
                $response = $client->post($url, $options);
                $data = json_decode($response->getBody(), true);

                // Hata kontrolü: Yanıt yapısının doğruluğunu kontrol et
                if (!isset($data['results']) || !is_array($data['results'])) {
                    throw new Exception("Unexpected response structure from Notion API.");
                }

                // Gelen veriyi işle
                foreach ($data['results'] as $item) {
                    $ticktickId = $item['properties']['Ticktick Id']['rich_text'][0]['text']['content'] ?? null;
                    if ($ticktickId) {
                        $existingTasks[$ticktickId] = true;
                    }
                }

                // Pagination kontrolü
                $hasMore = $data['has_more'] ?? false;
                $startCursor = $data['next_cursor'] ?? null;
            }
        } catch (RequestException $e) {
            error_log("Notion API Request failed: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error fetching tasks from Notion: " . $e->getMessage());
        }

        return $existingTasks;
    }

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

    private function colorDistance($rgb1, $rgb2) {
        return sqrt(pow($rgb1[0] - $rgb2[0], 2) + pow($rgb1[1] - $rgb2[1], 2) + pow($rgb1[2] - $rgb2[2], 2));
    }

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

    //login with routes
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
            unset($client);
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

            file_put_contents($this->ticktickV2AccessTokenFile, json_encode(['ticktick_v2_access_cookie' => $cookieString]));

            if (!empty($body['token'])) {
                session()->set('ticktick_v2_access_token', $body['token']);
                log_message('warning', "Basarili bir sekilde giris yapildi");

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

            file_put_contents($this->accessTokenFile, json_encode(['access_token' => $accessToken]));

            return redirect()->to('/ticktick');
        } catch (RequestException $e) {
            return view('error', ['message' => 'Access Token alınamadı: ' . $e->getMessage()]);
        }
    }
}