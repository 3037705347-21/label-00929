# Backend Dockerfile - PHP API Server
# 使用官方 PHP-FPM 镜像，支持 ARM64 和 AMD64
FROM php:8.2-fpm

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖和 PHP 扩展
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 复制 Nginx 配置
COPY nginx.conf /etc/nginx/sites-available/default

# 复制应用代码
COPY . /var/www/html

# 创建必要的目录并设置权限
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# 复制启动脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 暴露端口
EXPOSE 80

# 启动脚本
ENTRYPOINT ["docker-entrypoint.sh"]
