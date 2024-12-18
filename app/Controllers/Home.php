<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    private $notionApiToken;
    private $ticktickClientId;
    private $ticktickClientSecret;
    private $username = 'omar';
    private $password = '0956063218';
    private $maxAttempts = 5;
    private $lockoutDuration = 3600; // 1 hour in seconds
    private $attempts = [];
    private $lockoutEndTime = [];
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

    public function loginValidate()
    {
        $input = $this->request->getJSON(true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        $ip = $this->request->getIPAddress();

        if (isset($this->lockoutEndTime[$ip]) && time() < $this->lockoutEndTime[$ip]) {
            return $this->response->setJSON([
                'success' => false,
                'locked' => true,
                'remainingAttempts' => 0,
            ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        if ($username === $this->username && $password === $this->password) {
            unset($this->attempts[$ip]);
            return $this->response->setJSON(['success' => true]);
        }

        $this->attempts[$ip] = ($this->attempts[$ip] ?? 0) + 1;

        if ($this->attempts[$ip] >= $this->maxAttempts) {
            $this->lockoutEndTime[$ip] = time() + $this->lockoutDuration;
            return $this->response->setJSON([
                'success' => false,
                'locked' => true,
                'remainingAttempts' => 0,
            ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        return $this->response->setJSON([
            'success' => false,
            'locked' => false,
            'remainingAttempts' => $this->maxAttempts - $this->attempts[$ip],
        ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
    }

    private $statusFile = WRITEPATH . 'sync_status.json';

    public function updateStatus()
    {
        $input = $this->request->getJSON(true);
        $status = $input['status'] ?? null;

        if ($status === null || !in_array($status, [0, 1])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid status'])->setStatusCode(400);
        }

        file_put_contents($this->statusFile, json_encode(['status' => $status]));

        return $this->response->setJSON(['success' => true, 'message' => 'Status updated']);
    }
}