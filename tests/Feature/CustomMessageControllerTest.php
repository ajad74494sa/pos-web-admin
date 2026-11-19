<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomMessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomMessageControllerTest extends TestCase
{
    public function test_it_sends_a_whatsapp_message_through_openwa()
    {
        Http::fake([
            'http://example.test/api/sessions/test-session/messages/send-text' => Http::response(['success' => true], 200),
        ]);

        config()->set('services.openwa.base_url', 'http://example.test');
        config()->set('services.openwa.session_id', 'test-session');
        config()->set('services.openwa.api_key', 'test-key');

        $request = Request::create('/api/custom-message/send', 'POST', [
            'to' => '+1234567890',
            'message' => 'Hello from POS',
        ]);

        $controller = new CustomMessageController();
        $response = $controller->send($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://example.test/api/sessions/test-session/messages/send-text'
                && $request['text'] === 'Hello from POS'
                && $request['chatId'] === '1234567890@c.us';
        });
    }
}
