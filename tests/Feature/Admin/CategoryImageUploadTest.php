<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_image_upload_returns_media_route_url(): void
    {
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/upload-image', [
                'image' => UploadedFile::fake()->image('category.png'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $url = $response->json('url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/api/v1/media/uploads/', $url);

        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $storedPath = str_replace('api/v1/media/', '', $path);

        $mediaResponse = $this->get('/'.$path)
            ->assertOk();

        $this->assertSame(Storage::disk('public')->get($storedPath), $mediaResponse->getContent());
    }
}
