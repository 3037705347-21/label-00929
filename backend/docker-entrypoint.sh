#!/bin/bash
set -e

# 等待 MySQL 就绪
echo "Waiting for MySQL to be ready..."
while ! php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=3306', '${DB_USERNAME:-root}', '${DB_PASSWORD:-root123}');" 2>/dev/null; do
    echo "MySQL is unavailable - sleeping"
    sleep 2
done
echo "MySQL is ready!"

# 初始化数据库（如果需要）
if [ -f /var/www/html/database/schema.sql ]; then
    echo "Checking database initialization..."
    php -r "
        \$pdo = new PDO('mysql:host=${DB_HOST:-mysql}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-root123}');
        \$pdo->exec('CREATE DATABASE IF NOT EXISTS ${DB_DATABASE:-tandianda} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    "

    # 检查是否需要导入表结构
    TABLES=$(php -r "
        \$pdo = new PDO('mysql:host=${DB_HOST:-mysql};dbname=${DB_DATABASE:-tandianda}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-root123}');
        \$result = \$pdo->query('SHOW TABLES');
        echo \$result->rowCount();
    ")

    if [ "$TABLES" -eq "0" ]; then
        echo "Importing database schema..."
        mysql -h ${DB_HOST:-mysql} -u ${DB_USERNAME:-root} -p${DB_PASSWORD:-root123} ${DB_DATABASE:-tandianda} < /var/www/html/database/schema.sql
        echo "Database schema imported successfully!"
    else
        echo "Database already initialized, skipping import."
    fi
fi

# 确保目录权限正确
chown -R www-data:www-data /var/www/html/storage

# 启动 PHP-FPM
php-fpm -D

# 启动 Nginx（前台运行）
echo "Starting Nginx..."
nginx -g "daemon off;"
