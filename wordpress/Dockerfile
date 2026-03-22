FROM wordpress:6.7-php8.3-fpm AS base

# Install Redis PHP extension for session handling and object cache
RUN pecl install redis && docker-php-ext-enable redis

# PHP-FPM config for Kubernetes
COPY php-fpm-kubewp.conf /usr/local/etc/php-fpm.d/zz-kubewp.conf

# PHP config: Redis sessions, OPcache for immutable deploys
RUN { \
    echo 'session.save_handler = redis'; \
    echo 'session.save_path = "tcp://${REDIS_HOST:-redis}:${REDIS_PORT:-6379}"'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.validate_timestamps = 0'; \
    echo 'opcache.revalidate_freq = 0'; \
    echo 'opcache.max_accelerated_files = 20000'; \
    echo 'opcache.memory_consumption = 256'; \
    echo 'opcache.interned_strings_buffer = 16'; \
    echo 'upload_max_filesize = 256M'; \
    echo 'post_max_size = 256M'; \
    echo 'memory_limit = 512M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 5000'; \
} > /usr/local/etc/php/conf.d/kubewp.ini

# Platform mu-plugins (always active, cannot be deactivated by users)
COPY mu-plugins/ /var/www/html/wp-content/mu-plugins/

# Platform wp-config additions (included by wp-config.php)
COPY wp-config-kubewp.php /var/www/html/wp-config-kubewp.php

# Switch PHP-FPM to Unix socket (not TCP 9000)
RUN sed -i 's/listen = 9000/listen = \/var\/run\/php\/php-fpm.sock/' \
    /usr/local/etc/php-fpm.d/zz-docker.conf && \
    sed -i '/listen = \/var\/run\/php\/php-fpm.sock/a listen.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660' \
    /usr/local/etc/php-fpm.d/zz-docker.conf

# Create socket directory
RUN mkdir -p /var/run/php && chown www-data:www-data /var/run/php

# Health check: verify PHP-FPM is responding
HEALTHCHECK --interval=10s --timeout=3s --retries=3 \
    CMD php-fpm-healthcheck || exit 1
