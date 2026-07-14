# Use PHP-FPM Alpine as the base for PHP support
FROM php:8.2-fpm-alpine

# Install Nginx
RUN apk add --no-cache nginx

# Install PHP mysqli extension for database connectivity
RUN docker-php-ext-install mysqli

# Create the directory for Nginx run files
RUN mkdir -p /run/nginx

# Set the working directory
WORKDIR /var/www/html

# Copy all website files to the container
COPY index.html .
COPY about.html .
COPY privacy.html .
COPY support.html .
COPY submit_support.php .
COPY android.html .
COPY submit_beta_signup.php .
COPY styles.css .
COPY kyberchat_logo.png .
COPY favicon.ico .

# Create Nginx configuration to support PHP and Cloud Run (port 8080)
RUN echo 'server { \
    listen 8080; \
    server_name localhost; \
    root /var/www/html; \
    index index.html index.php; \
    \
    # Redirects for old/extension-based URLs to clean URLs \
    if ($request_uri ~ ^/privacy_policy(\.html)?$) { \
        return 301 /privacy; \
    } \
    if ($request_uri ~ ^/privacy\.html$) { \
        return 301 /privacy; \
    } \
    if ($request_uri ~ ^/android_beta(\.html)?$) { \
        return 301 /android; \
    } \
    if ($request_uri ~ ^/android\.html$) { \
        return 301 /android; \
    } \
    if ($request_uri ~ ^/about\.html$) { \
        return 301 /about; \
    } \
    if ($request_uri ~ ^/support\.html$) { \
        return 301 /support; \
    } \
    \
    location / { \
        try_files $uri $uri.html $uri/ /index.html; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
    # Security headers \
    add_header X-Frame-Options DENY; \
    add_header X-Content-Type-Options nosniff; \
    add_header X-XSS-Protection "1; mode=block"; \
}' > /etc/nginx/http.d/default.conf

# Expose port 8080 (required for Cloud Run)
EXPOSE 8080

CMD php-fpm -D && nginx -g "daemon off;"


