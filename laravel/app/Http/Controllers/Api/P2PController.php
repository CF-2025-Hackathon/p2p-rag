<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LLPhant\Chat\OllamaChat;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\OllamaConfig;
use DB;

class P2PController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function query(Request $request)
    {
        // Validator
        $validated = $request->validate([
            'question' => 'required|min:1',
            'perPage' => 'sometimes|integer|min:1',
        ]);

        $perPage = $validated['perPage'] ?? 15;

        // Ollama
        $config        = new OllamaConfig();
        $config->model = 'nomic-embed-text';
        $config->url   = 'http://ollama:11434/api/';
        $chat          = new OllamaEmbeddingGenerator($config);
        $response      = $chat->embedText($validated['question']);

        // Postgres
        $match_count = $perPage;
        $filter      = json_encode(['source' => 'cf_docs']);
        $results     = DB::connection('pgsql')->select('SELECT * FROM match_site_pages(:query_embedding, :match_count, :filter)',
            [
                'query_embedding' => '['.implode(',', $response).']',
//            'query_embedding' => $response,
//            'query_embedding' => DB::raw("ARRAY[?]::vector", [$response]),
//            'query_embedding' => '{' . implode(',', $response) . '}',
                'match_count'     => $match_count,
                'filter'          => $filter,
            ]);


//        $question = 'what is one + one ?';
//
//        $config        = new OllamaConfig();
//        $config->model = 'gemma3:1b';
//        $config->url   = 'http://ollama:11434/api/';
//        $chat          = new OllamaChat($config);
//        $response      = $chat->generateText($question);

        return response()->json([
            'question'     => $validated['question'],
            'response'     => $results,
            'ollama_model' => $config->model,
            'url_docker'   => $config->url,
            'status'       => 200,
        ]);
    }


    public function topics(Request $request)
    {
        return response()->json($request->all());
    }
}