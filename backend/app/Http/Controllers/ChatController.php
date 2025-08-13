<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Illuminate\Http\Request;

class ChatController
{
    public function index()
    {
        return view('chat');
    }

    public function streamResponse()
    {
        return response()->stream(function () {
            $userInput = request()->input('message');

                ob_start();

                $stream = Prism::text()
                    ->using(Provider::OpenAI, 'gpt-4o-mini')
                    ->withPrompt($userInput)
                    ->asStream();

                foreach ($stream as $chunk) {
                    echo $chunk->text;
                    if (ob_get_length()) {
                        ob_flush();
                    }
                    flush();
                }
            }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

