<?php
/**
 * Plugin Name: KubeWP Media Offload
 * Description: Offloads media uploads to S3/R2-compatible object storage
 * Version: 1.0.0
 * Author: KubeWP
 */
defined('ABSPATH') || exit;

class KubeWP_Media_Offload {
    private string $bucket;
    private string $endpoint;
    private string $region;
    private string $key;
    private string $secret;
    private string $cdn_url;

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

        // TODO: Implement S3 PutObject using AWS SDK or raw HTTP signing
        // For v1, use the AWS SDK for PHP (aws/aws-sdk-php)
        // $s3->putObject([
        //     'Bucket' => $this->bucket,
        //     'Key'    => $relative_path,
        //     'Body'   => fopen($file_path, 'rb'),
        //     'ContentType' => $upload['type'],
        //     'ACL'    => 'public-read',
        // ]);

        // After successful upload, optionally remove local copy
        // (for immutable deploys, local copy is in emptyDir and ephemeral anyway)

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

        // TODO: Implement S3 DeleteObject
        // $s3->deleteObject([
        //     'Bucket' => $this->bucket,
        //     'Key'    => $relative_path,
        // ]);
    }
}

new KubeWP_Media_Offload();
