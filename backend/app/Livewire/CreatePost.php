<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use NunoMaduro\Collision\Provider;
use Prism\Prism\Prism;

class CreatePost extends Component
{
    public string $userMessage;
    public string $streamedResponse;

    public function render()
    {
        return view('livewire.create-post');
    }

    public function send()
    {
        $this->streamedResponse = '';

        $responseStream = Prism::text()
            ->using('openai', 'gpt-4o-mini')
            ->withPrompt($this->userMessage)
            ->asStream();

        foreach ($responseStream as $chunk) {
            $this->streamedResponse .= $chunk->text;
            flush();
        }
    }
}
