# Frontend Admin Dockerfile - Nginx Static Server
# 使用官方 Nginx 镜像，支持 ARM64 和 AMD64
FROM nginx:1.25-alpine

# 设置工作目录
WORKDIR /usr/share/nginx/html

# 删除默认的 nginx 静态文件
RUN rm -rf /usr/share/nginx/html/*

# 复制前端静态文件
COPY . /usr/share/nginx/html

# 复制 Nginx 配置
COPY nginx.conf /etc/nginx/conf.d/default.conf

# 设置文件权限
RUN chmod -R 755 /usr/share/nginx/html

# 暴露端口
EXPOSE 80

# 启动 Nginx
CMD ["nginx", "-g", "daemon off;"]
