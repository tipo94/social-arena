# Video Processing System

This document outlines the video processing capabilities and requirements for the AI-Book Social Networking Platform.

## Features

### Video Upload & Processing
- **Multiple Format Support**: MP4, AVI, MOV, WMV, FLV, WebM, MKV, 3GP
- **Automatic Compression**: Smart quality adjustment based on content type and size
- **Format Conversion**: Convert between MP4 and WebM formats
- **Thumbnail Generation**: Automatic and custom thumbnail creation
- **Background Processing**: All video operations run asynchronously

### Size Limits by Type
- **Posts**: 500MB max
- **Messages**: 100MB max  
- **Groups**: 300MB max
- **Stories**: 50MB max

### Quality Settings
- **Low**: CRF 28, fast preset, 1000k bitrate
- **Medium**: CRF 24, medium preset, 2000k bitrate
- **High**: CRF 20, slow preset, 4000k bitrate
- **Ultra**: CRF 18, slower preset, 8000k bitrate

## Requirements

### FFmpeg Installation

The video processing system requires FFmpeg and FFprobe to be installed on the server.

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

#### CentOS/RHEL
```bash
sudo yum install epel-release
sudo yum install ffmpeg ffmpeg-devel
```

#### macOS
```bash
brew install ffmpeg
```

#### Docker
Add to your Dockerfile:
```dockerfile
RUN apt-get update && apt-get install -y ffmpeg
```

### Verification
Test FFmpeg installation:
```bash
ffmpeg -version
ffprobe -version
```

## API Endpoints

### Upload Videos
```http
POST /api/media/upload-videos
Content-Type: multipart/form-data

{
  "videos": [file1, file2],
  "type": "posts",
  "quality": "medium",
  "output_format": "mp4"
}
```

### Get Processing Status
```http
GET /api/media/video-processing-status?media_id=123
```

### Convert Video Format
```http
POST /api/media/convert-video
{
  "media_id": 123,
  "output_format": "webm",
  "quality": "high"
}
```

### Generate Thumbnail
```http
POST /api/media/generate-video-thumbnail
{
  "media_id": 123,
  "time": 5.5,
  "width": 640,
  "height": 360
}
```

### Get Video Metadata
```http
GET /api/media/video-metadata?media_id=123
```

## Configuration

### Queue Configuration
Video processing jobs require a properly configured queue system:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 7200, // 2 hours for video processing
    ],
],
```

### Storage Configuration
Configure appropriate storage disks for video files:

```php
// config/filesystems.php
'disks' => [
    'videos' => [
        'driver' => 'local',
        'root' => storage_path('app/videos'),
        'url' => env('APP_URL').'/storage/videos',
        'visibility' => 'public',
    ],
],
```

## Processing Workflow

1. **Upload**: Video files are validated and temporarily stored
2. **Queue**: Processing job is dispatched to background queue
3. **Analysis**: FFprobe extracts video metadata (duration, resolution, codec)
4. **Conversion**: FFmpeg processes video with specified settings
5. **Variants**: Multiple formats/qualities are generated as needed
6. **Thumbnails**: Video thumbnails are extracted at specified times
7. **Storage**: Processed files are stored to configured disk
8. **Cleanup**: Temporary files are removed
9. **Notification**: MediaAttachment record is updated with results

## Performance Considerations

### Processing Time Estimation
- **Small videos** (<100MB): 2-5 minutes
- **Medium videos** (100-500MB): 5-15 minutes  
- **Large videos** (>500MB): 15-60 minutes

### Optimization Tips
- Use `medium` or `fast` presets for faster processing
- Lower CRF values increase quality but also processing time
- WebM format typically provides better compression than MP4
- Generate thumbnails during initial processing to avoid re-encoding

## Error Handling

Common processing errors and solutions:

### FFmpeg Not Found
```
Error: FFmpeg is not available for video processing
Solution: Install FFmpeg on the server
```

### Codec Not Supported
```
Error: Video conversion failed: Unknown encoder 'libvpx-vp9'
Solution: Install FFmpeg with VP9 support
```

### File Too Large
```
Error: Video file is too large
Solution: Increase upload limits or compress video before upload
```

### Invalid Duration
```
Error: Video duration exceeds maximum allowed
Solution: Check duration limits for upload type
```

## Monitoring

### Job Monitoring
Monitor video processing jobs:
```bash
php artisan queue:work --queue=default --timeout=7200
php artisan horizon:status
```

### Storage Monitoring
Track storage usage for video files:
```bash
du -sh storage/app/videos/
```

### Performance Monitoring
Log processing times and success rates:
```php
Log::info('Video processing completed', [
    'duration' => $processingTime,
    'original_size' => $originalSize,
    'compressed_size' => $compressedSize,
    'compression_ratio' => $compressionRatio,
]);
```

## Security

### File Validation
- MIME type verification
- File extension checks
- Content analysis to prevent malicious uploads
- Size limit enforcement

### Access Control
- User ownership verification
- Private video handling
- Temporary URL generation for secure access

### Content Filtering
- Basic spam detection
- Duration limits by context
- Resolution limits by upload type 