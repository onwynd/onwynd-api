<?php

namespace Tests\Feature\API\Contact;

use App\Mail\ContactFormSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submit_creates_lead_and_queues_mail()
    {
        Mail::fake();

        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'general',
            'message' => 'I would like to learn more about your platform.',
            'phone' => '+1234567890',
        ];

        $res = $this->postJson('/api/v1/contact/submit', $payload);

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['ticket_id', 'reference_number'], 'message']);

        $this->assertDatabaseHas('leads', [
            'email' => $payload['email'],
            'source' => 'contact_form',
        ]);

        Mail::assertQueued(ContactFormSubmitted::class, function ($mail) use ($payload) {
            return $mail->payload['email'] === $payload['email'];
        });
    }

    public function test_contact_submit_validation_errors_return_422()
    {
        $res = $this->postJson('/api/v1/contact/submit', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $res->assertStatus(422);
    }

    public function test_newsletter_subscribe_creates_lead()
    {
        $res = $this->postJson('/api/v1/contact/newsletter/subscribe', ['email' => 'sub@example.com']);
        $res->assertStatus(200)->assertJsonPath('data.message', 'Subscribed successfully.');

        $this->assertDatabaseHas('leads', [
            'email' => 'sub@example.com',
            'source' => 'newsletter',
        ]);
    }

    public function test_contact_info_endpoint_returns_keys()
    {
        $res = $this->getJson('/api/v1/contact/info');
        $res->assertStatus(200)->assertJsonStructure(['data' => ['email', 'phone', 'address', 'office_hours']]);
    }
}
