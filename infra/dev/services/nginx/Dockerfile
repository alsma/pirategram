FROM nginx:1.27-alpine

COPY nginx.conf /etc/nginx/
COPY fastcgi_params /etc/nginx/

RUN \
    rm /etc/nginx/conf.d/default.conf && \
    adduser -S -D -H -u 1001 -s /sbin/nologin www-data

CMD ["nginx"]

EXPOSE 80 443
