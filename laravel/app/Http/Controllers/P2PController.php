<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChat;

class P2PController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $config = new OllamaConfig();
        $config->model = 'gemma3:4b';
//        $config->url = 'http://172.18.209.59:11434/api/';
//        $config->url = 'http://172.18.209.149:11434/api/';
//        $config->url = 'http://ollama:11434/api/';
        $chat = new OllamaChat($config);
        $response = $chat->generateText('what is one + one ?');

        print_r($response);
        die;

        echo 'TEST';
    }
}