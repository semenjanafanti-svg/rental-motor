<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_customer_password_reset_request_is_sent_to_the_owner(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $owner = User::factory()->create(['role' => 'super_admin']);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status', 'Permintaan reset password sudah dikirim ke owner. Password baru akan dikirim melalui WhatsApp setelah diproses.');
        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_multiple_requests_keep_one_pending_request(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email]);

        $this->assertSame(1, PasswordResetRequest::where('user_id', $user->id)->count());
    }
}
