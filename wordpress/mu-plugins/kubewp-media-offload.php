<?php
/**
 * Plugin Name: KubeWP Media Offload
 * Description: Offloads media uploads to S3/R2-compatible object storage
 * Version: 1.0.0
 * Author: KubeWP
 */
defined('ABSPATH') || exit;

// Load Composer autoloader for aws-sdk-php (per D-12, pitfall 4)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class KubeWP_Media_Offload {
    private string $bucket;
    private string $endpoint;
    private string $region;
    private string $key;
    private string $secret;
    private string $cdn_url;
    private ?\Aws\S3\S3Client $s3_client = null;

    public function __construct() {
        $this->bucket   = defined('KUBEWP_S3_BUCKET')   ? KUBEWP_S3_BUCKET   : '';
        $this->endpoint = defined('KUBEWP_S3_ENDPOINT') ? KUBEWP_S3_ENDPOINT : '';
        $this->region   = defined('KUBEWP_S3_REGION')   ? KUBEWP_S3_REGION   : 'auto';
        $this->key      = defined('KUBEWP_S3_KEY')      ? KUBEWP_S3_KEY      : '';
        $this->secret   = defined('KUBEWP_S3_SECRET')   ? KUBEWP_S3_SECRET   : '';
        $this->cdn_url  = defined('KUBEWP_CDN_URL')     ? KUBEWP_CDN_URL     : '';

        if (empty($this->bucket) || empty($this->endpoint)) {
            return; // Object storage not configured, skip
        }

        // Rewrite media URLs to CDN/object storage
        add_filter('wp_get_attachment_url', [$this, 'rewrite_url'], 10, 2);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_srcset'], 10, 5);

        // Intercept uploads and send to object storage
        add_filter('wp_handle_upload', [$this, 'upload_to_s3'], 10, 2);

        // Handle deletions
        add_action('delete_attachment', [$this, 'delete_from_s3']);
    }

    /**
     * Get or create the S3 client (lazy singleton).
     */
    private function get_s3_client(): \Aws\S3\S3Client {
        if (!$this->s3_client) {
            $this->s3_client = new \Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => $this->region,
                'endpoint'    => $this->endpoint,
                'credentials' => [
                    'key'    => $this->key,
                    'secret' => $this->secret,
                ],
                'use_path_style_endpoint' => true,
            ]);
        }
        return $this->s3_client;
    }

    /**
     * Rewrite attachment URL to CDN URL
     */
    public function rewrite_url(string $url, int $attachment_id): string {
        if (empty($this->cdn_url)) return $url;
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['baseurl'], rtrim($this->cdn_url, '/'), $url);
    }

    /**
     * Rewrite srcset URLs to CDN
     */
    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (empty($this->cdn_url)) return $sources;
        $upload_dir = wp_upload_dir();
        foreach ($sources as &$source) {
            $source['url'] = str_replace(
                $upload_dir['baseurl'],
                rtrim($this->cdn_url, '/'),
                $source['url']
            );
        }
        return $sources;
    }

    /**
     * Upload file to S3-compatible storage after WordPress processes it
     */
    public function upload_to_s3(array $upload, string $context): array {
        if (isset($upload['error']) && $upload['error']) {
            return $upload;
        }

        $file_path = $upload['file'];
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

        try {
            $s3 = $this->get_s3_client();
            $s3->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $relative_path,
                'SourceFile'  => $file_path,
                'ContentType' => $upload['type'],
            ]);
            // Remove local copy (ephemeral emptyDir, gone on pod restart)
            @unlink($file_path);
        } catch (\Aws\Exception\AwsException $e) {
            error_log('KubeWP S3 upload failed: ' . $e->getMessage());
            // Don't fail the upload - file exists locally as fallback
        }

        return $upload;
    }

    /**
     * Delete file from S3 when attachment is deleted
     */
    public function delete_from_s3(int $attachment_id): void {
        $file = get_attached_file($attachment_id);
        if (!$file) return;

        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file);

        try {
            $s3 = $this->get_s3_client();
            $s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $relative_path,
            ]);
            // Also delete thumbnail sizes
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($relative_path);
                foreach ($metadata['sizes'] as $size) {
                    $s3->deleteObject([
                        'Bucket' => $this->bucket,
                        'Key'    => $base_dir . '/' . $size['file'],
                    ]);
                }
            }
        } catch (\Aws\Exception\AwsException $e) {
            error_log('KubeWP S3 delete failed: ' . $e->getMessage());
        }
    }
}

new KubeWP_Media_Offload();
