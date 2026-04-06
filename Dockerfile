# Use nginx alpine image for lightweight deployment
FROM nginx:alpine

# Copy static files to nginx html directory
COPY index.html /usr/share/nginx/html/
COPY privacy_policy.html /usr/share/nginx/html/
COPY styles.css /usr/share/nginx/html/
COPY kyberchat_logo.jpg /usr/share/nginx/html/
COPY favicon.ico /usr/share/nginx/html/

# Create nginx configuration for Cloud Run
RUN echo 'server { \
    listen 8080; \
    server_name localhost; \
    location / { \
        root /usr/share/nginx/html; \
        index index.html; \
        try_files $uri $uri/ /index.html; \
    } \
    # Security headers \
    add_header X-Frame-Options DENY; \
    add_header X-Content-Type-Options nosniff; \
    add_header X-XSS-Protection "1; mode=block"; \
}' > /etc/nginx/conf.d/default.conf

# Expose port 8080 (required for Cloud Run)
EXPOSE 8080

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
