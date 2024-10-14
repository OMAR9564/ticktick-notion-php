<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Home extends BaseController
{
    private $notionApiToken;
    private $ticktickClientId;
    private $ticktickClientSecret;
    public function __construct()
    {
        $this->notionApiToken = getenv('NOTION_API_TOKEN');
        $this->ticktickClientId = getenv('TICKTICK_CLIENT_ID');
        $this->ticktickClientSecret = getenv('TICKTICK_CLIENT_SECRET');
        $this->redirectUri = getenv('TICKTICK_REDIRECT_URI');
    }

    public function index()
    {
        return view('integration');
    }

    public function activateIntegration()
    {
        $authUrl = "https://ticktick.com/oauth/authorize?" . http_build_query([
            'scope' => 'tasks:write tasks:read',
            'client_id' => $this->ticktickClientId,
            'state' => csrf_hash(),
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code'
        ]);
        return redirect()->to($authUrl);
    }

    public function handleCallback()
    {
        $code = $this->request->getGet('code');
        if (!$code) {
            session()->setFlashdata('sync_error', 'Authorization failed.');
            return redirect()->to('/integration');
        }

        try {
            $accessToken = $this->getAccessToken($code);
            session()->set('ticktick_access_token', $accessToken);
            session()->set('integration_active', true);
            return redirect()->to('/integration');
        } catch (RequestException $e) {
            session()->setFlashdata('sync_error', 'Error during token exchange: ' . $e->getMessage());
            return redirect()->to('/integration');
        }
    }

    public function callback()
    {
        $code = $this->request->getGet('code');
        if (!$code) {
            session()->setFlashdata('sync_error', 'Authorization failed.');
            return redirect()->to('/integration');
        }

        try {
            $accessToken = $this->getAccessToken($code);
            session()->set('ticktick_access_token', $accessToken);
            session()->set('integration_active', true);
            return redirect()->to('/integration');
        } catch (RequestException $e) {
            session()->setFlashdata('sync_error', 'Error during token exchange: ' . $e->getMessage());
            return redirect()->to('/integration');
        }
    }

    public function syncTasks()
    {
        if (!session()->get('integration_active') || !session()->get('ticktick_access_token')) {
            session()->setFlashdata('sync_error', 'Integration is not active');
            return redirect()->to('/integration');
        }

        try {
            $ticktickTasks = $this->getTickTickTasks(session()->get('ticktick_access_token'));
            if ($ticktickTasks) {
                $this->sendTasksToNotion($ticktickTasks);
                session()->setFlashdata('sync_success', 'Tasks synced successfully');
            } else {
                session()->setFlashdata('sync_error', 'No tasks fetched from TickTick');
            }
        } catch (RequestException $e) {
            session()->setFlashdata('sync_error', 'Error syncing tasks: ' . $e->getMessage());
        }

        return redirect()->to('/integration');
    }

    private function getAccessToken($code)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://ticktick.com/oauth/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->ticktickClientId . ':' . $this->ticktickClientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'form_params' => [
                'client_id' => $this->ticktickClientId,
                'client_secret' => $this->ticktickClientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'scope' => 'tasks:write tasks:read',
                'redirect_uri' => $this->redirectUri
            ]
        ]);

        $body = json_decode($response->getBody(), true);
        return $body['access_token'] ?? null;
    }

    private function getTickTickTasks($accessToken)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', 'https://api.ticktick.com/api/v2/tasks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw $e;
        }
    }

    private function sendTasksToNotion($tasks)
    {
        $client = new Client();
        foreach ($tasks as $task) {
            try {
                $client->request('POST', 'https://api.notion.com/v1/pages', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->notionApiToken,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'parent' => [
                            'database_id' => 'YOUR_NOTION_DATABASE_ID'
                        ],
                        'properties' => [
                            'Name' => [
                                'title' => [
                                    [
                                        'text' => [
                                            'content' => $task['title'] ?? 'Untitled Task'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            } catch (RequestException $e) {
                throw $e;
            }
        }
    }
}