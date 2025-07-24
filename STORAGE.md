# AI-Book File Storage System

This document describes the comprehensive file storage system for the AI-Book social networking platform.

## Overview

The storage system is designed with flexibility and scalability in mind, supporting both local development and cloud production environments with seamless migration capabilities.

## Storage Architecture

### Storage Disks

The system uses multiple storage disks organized by content type:

#### Local Storage Disks
- **`local`** - Private files (not publicly accessible)
- **`public`** - General public files
- **`avatars`** - User profile pictures
- **`posts`** - Post media attachments
- **`messages`** - Message attachments
- **`groups`** - Group photos and files
- **`temp`** - Temporary file processing
- **`secure`** - Secure private storage

#### Cloud Storage Disks (S3)
- **`s3`** - General cloud storage
- **`s3-avatars`** - Cloud avatar storage
- **`s3-posts`** - Cloud post media storage
- **`s3-messages`** - Cloud message attachments
- **`s3-groups`** - Cloud group files

### Automatic Disk Selection

The `StorageService` automatically selects the appropriate storage disk based on:

1. **Environment**: Production automatically uses cloud storage when AWS credentials are configured
2. **Content Type**: Different content types use optimized storage configurations
3. **Availability**: Falls back to local storage if cloud storage is not available

## Configuration

### Environment Variables

Configure storage in your `.env` file:

```bash
# Default filesystem
FILESYSTEM_DISK=local
FILESYSTEM_CLOUD=s3

# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket.s3.amazonaws.com
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Storage Links

Symbolic links provide public access to storage directories:

```bash
# Create storage links
php artisan storage:link
```

Links created:
- `public/storage` → `storage/app/public`
- `public/storage/avatars` → `storage/app/public/avatars`
- `public/storage/posts` → `storage/app/public/posts`
- `public/storage/messages` → `storage/app/public/messages`
- `public/storage/groups` → `storage/app/public/groups`

## StorageService API

### Upload Files

```php
use App\Services\StorageService;

$storageService = new StorageService();

// Upload a general file
$result = $storageService->uploadFile($uploadedFile, 'public', 'optional-folder');

// Upload and process an image
$results = $storageService->uploadImage($uploadedFile, 'avatars', [
    'thumbnail' => [150, 150],
    'medium' => [400, 300],
    'large' => [800, 600]
]);
```

### File Operations

```php
// Get file information
$info = $storageService->getFileInfo('public', 'path/to/file.jpg');

// Delete a file
$deleted = $storageService->deleteFile('public', 'path/to/file.jpg');

// Create temporary URL for private files
$url = $storageService->createTemporaryUrl('secure', 'private-file.pdf', 60);
```

### Cloud Migration

```php
// Migrate a file to cloud storage
$result = $storageService->migrateToCloud('avatars', 's3-avatars', 'user-avatar.jpg');
```

## HTTP API Endpoints

### Upload Image

```http
POST /api/media/upload-image
Content-Type: multipart/form-data
Authorization: Bearer {token}

{
  "image": "file",
  "type": "avatars|posts|messages|groups",
  "sizes": {
    "thumbnail": [150, 150],
    "medium": [400, 300]
  }
}
```

### Upload File

```http
POST /api/media/upload-file
Content-Type: multipart/form-data
Authorization: Bearer {token}

{
  "file": "file",
  "type": "public|messages|groups|temp",
  "folder": "optional-folder-name"
}
```

### Get File Information

```http
POST /api/media/file-info
Content-Type: application/json
Authorization: Bearer {token}

{
  "disk": "public",
  "path": "path/to/file.jpg"
}
```

### Create Temporary URL

```http
POST /api/media/temporary-url
Content-Type: application/json
Authorization: Bearer {token}

{
  "disk": "secure",
  "path": "private-file.pdf",
  "expires_in_minutes": 60
}
```

### Delete File

```http
DELETE /api/media/file
Content-Type: application/json
Authorization: Bearer {token}

{
  "disk": "public",
  "path": "path/to/file.jpg"
}
```

## Image Processing

### Automatic Processing

Images are automatically processed when uploaded:

1. **Validation**: File type, size, and format validation
2. **Resizing**: Multiple sizes generated based on content type
3. **Optimization**: JPEG compression with quality optimization
4. **Format Conversion**: All images converted to JPEG for consistency

### Default Image Sizes

- **Original**: Unchanged (up to validation limits)
- **Large**: 800×600 pixels
- **Medium**: 400×300 pixels  
- **Thumbnail**: 150×150 pixels

### Custom Sizes

Specify custom sizes when uploading:

```php
$sizes = [
    'banner' => [1200, 400],
    'card' => [300, 200],
    'icon' => [64, 64]
];

$results = $storageService->uploadImage($image, 'posts', $sizes);
```

## Cloud Migration

### Automatic Migration

Files automatically use cloud storage in production when AWS credentials are configured.

### Manual Migration

Use the Artisan command to migrate existing files:

```bash
# Migrate all files to cloud
php artisan storage:migrate-to-cloud all

# Migrate specific type
php artisan storage:migrate-to-cloud avatars

# Dry run (preview what would be migrated)
php artisan storage:migrate-to-cloud all --dry-run

# Process in smaller batches
php artisan storage:migrate-to-cloud posts --batch=50
```

### Migration Process

1. **Verification**: Checks if files exist locally
2. **Upload**: Copies files to cloud storage
3. **Verification**: Confirms successful cloud upload
4. **Cleanup**: Removes local files after successful upload
5. **Progress**: Shows real-time progress with error reporting

## Security Features

### File Validation

- **Type Validation**: Only allowed file types accepted
- **Size Limits**: Configurable maximum file sizes
- **Content Scanning**: Image content validation
- **Extension Filtering**: Dangerous file extensions blocked

### Access Control

- **Private Storage**: Secure disk for sensitive files
- **Temporary URLs**: Time-limited access to private files
- **Authentication**: API endpoints require authentication
- **Authorization**: User-specific file access controls

### Storage Isolation

- **Type Separation**: Different content types in separate directories
- **User Isolation**: Files organized by user/content ownership
- **Environment Separation**: Development/production storage isolation

## Performance Optimization

### Caching

- **CDN Ready**: URLs compatible with CloudFront and other CDNs
- **HTTP Caching**: Proper cache headers for static files
- **Image Optimization**: Automatic compression and format optimization

### Scalability

- **Multiple Sizes**: Pre-generated image sizes reduce processing
- **Async Processing**: Background jobs for heavy processing
- **Cloud Storage**: Unlimited scalability with S3
- **Batch Operations**: Efficient bulk file operations

## Monitoring and Maintenance

### Storage Metrics

Monitor storage usage:

```php
// Get storage statistics
$stats = [
    'total_files' => Storage::disk('public')->files(),
    'total_size' => array_sum(array_map(
        fn($file) => Storage::disk('public')->size($file),
        Storage::disk('public')->allFiles()
    ))
];
```

### Cleanup Operations

Regular maintenance tasks:

```bash
# Clean up temporary files older than 24 hours
php artisan storage:cleanup-temp

# Remove orphaned files (files not referenced in database)
php artisan storage:cleanup-orphaned

# Verify file integrity
php artisan storage:verify-integrity
```

## Development Workflow

### Local Development

1. Files stored in `storage/app/` directories
2. Symbolic links provide public access
3. No cloud dependencies required
4. Fast local file operations

### Production Deployment

1. Configure AWS credentials
2. Files automatically use S3 storage
3. Existing files can be migrated with command
4. CDN integration for global distribution

### Testing

Storage operations are fully testable:

```php
// In tests, use fake storage
Storage::fake('avatars');

$result = $storageService->uploadImage($fakeImage, 'avatars');

Storage::disk('avatars')->assertExists($result['original']['path']);
```

## Troubleshooting

### Common Issues

**Storage links not working**:
```bash
php artisan storage:link
```

**AWS S3 connection errors**:
- Verify AWS credentials in `.env`
- Check bucket permissions
- Confirm region settings

**Image processing failures**:
- Verify GD extension is installed
- Check memory limits for large images
- Validate image file integrity

**File upload failures**:
- Check PHP upload limits (`upload_max_filesize`, `post_max_size`)
- Verify directory permissions
- Check available disk space

### Debug Commands

```bash
# Test storage configuration
php artisan storage:test

# Check disk usage
php artisan storage:usage

# Verify cloud connectivity
php artisan storage:test-cloud

# List all configured disks
php artisan storage:disks
```

## Best Practices

### File Organization

1. **Consistent Naming**: Use UUID-based filenames
2. **Directory Structure**: Organize by date and type
3. **Content Types**: Separate different media types
4. **Backup Strategy**: Regular backups of critical files

### Performance

1. **Image Optimization**: Always compress images
2. **Lazy Loading**: Load images on demand
3. **CDN Usage**: Distribute static files globally
4. **Caching**: Implement proper HTTP caching

### Security

1. **Input Validation**: Validate all uploaded files
2. **Access Controls**: Implement proper authorization
3. **Scanning**: Scan uploads for malware
4. **Monitoring**: Log and monitor file operations

This storage system provides a robust, scalable, and secure foundation for all file operations in the AI-Book social networking platform. 