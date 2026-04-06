# kubewp-runtime

Cloud-native WordPress runtime for Kubernetes. Provides production-ready Nginx reverse proxy and WordPress PHP-FPM container images designed for KubeWP managed WordPress hosting, but usable by anyone running WordPress on Kubernetes.

## Overview

kubewp-runtime packages WordPress into a two-container pod architecture optimized for Kubernetes deployments. The images handle caching, security hardening, media offloading, and platform integration out of the box -- so operators get a production-grade WordPress runtime without manual tuning.

## Architecture

The runtime uses a two-container pod model:

- **Nginx** (reverse proxy with Coraza WAF) -- handles TLS termination, static file serving, WAF rules via Coraza, fastcgi page caching, rate limiting on wp-login and xmlrpc, and proxies PHP requests to the WordPress container via FastCGI over a shared Unix socket.
- **WordPress** (PHP-FPM with KubeWP mu-plugins) -- runs WordPress with OPcache, Redis sessions, Composer-managed dependencies, and platform mu-plugins for media offloading, security policy enforcement, and Kubernetes integration.

Both containers share a volume for the Unix socket (`/var/run/php/php-fpm.sock`) and the WordPress document root.

```
                    +---------------------------+
  Ingress --------> |  Nginx Container          |
                    |  - Coraza WAF             |
                    |  - Static file serving    |
                    |  - FastCGI page cache     |
                    |  - Rate limiting          |
                    |         |                 |
                    |    Unix Socket            |
                    |         |                 |
                    |  WordPress Container      |
                    |  - PHP-FPM                |
                    |  - OPcache                |
                    |  - Redis sessions         |
                    |  - mu-plugins             |
                    +---------------------------+
```

## Components

### Nginx

Reverse proxy with Coraza WAF and WordPress-optimized configuration.

- **Image:** `ghcr.io/kubesage/kubewp-nginx`
- **Base:** `nginx:1.27-alpine`
- **Features:**
  - Coraza WAF with OWASP CRS (DetectionOnly mode by default)
  - FastCGI page cache (512MB, 60min TTL)
  - Gzip compression for text, CSS, JS, SVG
  - Rate limiting on wp-login.php (5r/m) and xmlrpc.php (1r/m)
  - Security headers (HSTS, X-Frame-Options, X-Content-Type-Options)
  - CDN-friendly Cache-Control headers
  - Blocked access to sensitive files (wp-config.php, readme.html)
  - Health check endpoint at `/kubewp-health`

### WordPress

PHP-FPM runtime with OPcache, Composer dependencies, and KubeWP mu-plugins.

- **Image:** `ghcr.io/kubesage/kubewp-wordpress`
- **Base:** `wordpress:6.7-php8.3-fpm`
- **Features:**
  - Redis PHP extension for session handling
  - OPcache with `validate_timestamps=0` (immutable deploys)
  - PHP-FPM tuned for Kubernetes (dynamic pm, 20 max children)
  - Unix socket listener (not TCP 9000)
  - Composer-managed dependencies (aws-sdk-php for S3 offload)
  - Platform wp-config with environment variable configuration
  - All config via environment variables (database, Redis, S3, auth keys)

## Building

Build the Nginx image:

```bash
docker build -t kubewp-nginx:dev nginx/
```

Build the WordPress image:

```bash
docker build -t kubewp-wordpress:dev wordpress/
```

## mu-plugins

KubeWP includes three must-use plugins that are always active and cannot be deactivated by site owners:

- **kubewp-platform.php** -- Platform integration hooks: health check endpoint, WP-Cron disabling (replaced by K8s CronJob), admin notices for immutable filesystem, and runtime info reporting.
- **kubewp-media-offload.php** -- S3-compatible media offload. Uploads media to object storage (S3, R2, MinIO), rewrites URLs to CDN, removes local copies. Uses aws-sdk-php via Composer.
- **kubewp-policy.php** -- Security policy enforcement. Reads a blocklist JSON file written by the data-plane agent and deactivates blocked plugins at runtime on every request.

## License

Apache License 2.0. See [LICENSE](LICENSE).
