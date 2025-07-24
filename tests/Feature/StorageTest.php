<?php

namespace Tests\Feature;

use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageTest extends TestCase
{
    protected StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageService = new StorageService();
    }

    /**
     * Test basic file upload
     */
    public function test_can_upload_file(): void
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('test.txt', 100);
        
        $result = $this->storageService->uploadFile($file, 'public');
        
        $this->assertArrayHasKey('disk', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertEquals('public', $result['disk']);
        
        Storage::disk('public')->assertExists($result['path']);
    }

    /**
     * Test image upload with processing
     */
    public function test_can_upload_and_process_image(): void
    {
        Storage::fake('avatars');
        
        $image = UploadedFile::fake()->image('avatar.jpg', 800, 600);
        
        $results = $this->storageService->uploadImage($image, 'avatars');
        
        $this->assertArrayHasKey('original', $results);
        $this->assertArrayHasKey('thumbnail', $results);
        
        foreach ($results as $size => $result) {
            $this->assertArrayHasKey('disk', $result);
            $this->assertArrayHasKey('path', $result);
            $this->assertArrayHasKey('url', $result);
            
            Storage::disk('avatars')->assertExists($result['path']);
        }
    }

    /**
     * Test file deletion
     */
    public function test_can_delete_file(): void
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('test.txt', 100);
        $result = $this->storageService->uploadFile($file, 'public');
        
        // Verify file exists
        Storage::disk('public')->assertExists($result['path']);
        
        // Delete file
        $deleted = $this->storageService->deleteFile($result['disk'], $result['path']);
        
        $this->assertTrue($deleted);
        Storage::disk('public')->assertMissing($result['path']);
    }

    /**
     * Test file info retrieval
     */
    public function test_can_get_file_info(): void
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('test.txt', 100);
        $result = $this->storageService->uploadFile($file, 'public');
        
        $info = $this->storageService->getFileInfo($result['disk'], $result['path']);
        
        $this->assertArrayHasKey('exists', $info);
        $this->assertArrayHasKey('size', $info);
        $this->assertArrayHasKey('url', $info);
        $this->assertTrue($info['exists']);
        $this->assertIsNumeric($info['size']);
    }

    /**
     * Test disk selection for different types
     */
    public function test_selects_correct_disk_for_type(): void
    {
        // Test local storage selection
        $reflection = new \ReflectionClass($this->storageService);
        $method = $reflection->getMethod('getDiskForType');
        $method->setAccessible(true);
        
        $this->assertEquals('avatars', $method->invoke($this->storageService, 'avatars'));
        $this->assertEquals('posts', $method->invoke($this->storageService, 'posts'));
        $this->assertEquals('messages', $method->invoke($this->storageService, 'messages'));
        $this->assertEquals('groups', $method->invoke($this->storageService, 'groups'));
        $this->assertEquals('public', $method->invoke($this->storageService, 'unknown'));
    }
} 