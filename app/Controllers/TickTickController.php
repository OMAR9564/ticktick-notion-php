<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TickTickController extends BaseController
{
    private $ticktickClientId;
    private $ticktickClientSecret;
    private $redirectUri;
    private $loginUrl;
    private $email;
    private $password;

    public function __construct()
    {
        $this->loginUrl = 'https://ticktick.com/api/v2/user/signon?wc=true&remember=true';
        $this->ticktickClientId = getenv('TICKTICK_CLIENT_ID');
        $this->ticktickClientSecret = getenv('TICKTICK_CLIENT_SECRET');
        $this->redirectUri = getenv('TICKTICK_REDIRECT_URI');
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
        // Access Token'ı session'dan alın
        $accessToken = session()->get('ticktick_access_token');

        // Eğer Access Token yoksa, kullanıcıyı giriş sayfasına yönlendirin
        if ($accessToken) {
            return redirect()->to('/ticktick/login')->with('error', 'Oturum açmanız gerekiyor.');
        }

        try {
            $client = new Client();
            
            // Tamamlanmamış görevler (tasks)
            $activeResponse = $client->get("https://api.ticktick.com/api/v2/project/$projectId/tasks", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            $activeTasks = json_decode($activeResponse->getBody(), true);

            // Tamamlanmış görevler (completed)
            $completedResponse = $client->get("https://api.ticktick.com/api/v2/project/$projectId/completed", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            $completedTasks = json_decode($completedResponse->getBody(), true);

            // Görevleri birleştir
            $tasks = [
                'activeTasks' => $activeTasks['tasks'] ?? [],
                'completedTasks' => $completedTasks ?? [],
            ];

            // Görevleri View'e gönder
            return view('ticktick_project_tasks', [
                'tasks' => $tasks,
                'project' => $activeTasks['project'] ?? [],
            ]);
        } catch (RequestException $e) {
            // API çağrısında bir hata oluşursa, bunu kullanıcıya gösterin
            return view('error', ['message' => 'Görevler alınamadı: ' . $e->getMessage()]);
        }
    }
    public function login()
    {
        try {
            $client = new Client();
            $response = $client->post($this->loginUrl, [
                'json' => [
                    'email' => $this->email,
                    'password' => $this->password,
                ],
            ]);
            
            $body = json_decode($response->getBody(), true);
    
            if (!empty($body['access_token'])) {
                session()->set('ticktick_access_token', $body['access_token']);
                return redirect()->to('/ticktick/tasks');
            } else {
                return view('error', ['message' => 'Giriş başarısız oldu.']);
            }
        } catch (RequestException $e) {
            return view('error', ['message' => 'Login işlemi sırasında hata oluştu: ' . $e->getMessage()]);
        }
    }
    

}
