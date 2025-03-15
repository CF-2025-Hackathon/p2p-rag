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
        $question = 'what is one + one ?';

        $config = new OllamaConfig();
        $config->model = 'gemma3:1b';
        $config->url = 'http://ollama:11434/api/';
        $chat = new OllamaChat($config);
        $response = $chat->generateText($question);

        return response()->json([
            'question' => $question,
            'response' => $response,
            'ollama_model' => $config->model,
            'url_docker' => $config->url,
            'status' => 200
        ]);
    }
}