FROM node:22.12.0-alpine

RUN apk add --update --no-cache python3 make g++ git openssh tar pngquant && \
     ln -sf python3 /usr/bin/python && \
     npm install -g npm@11.0.0 svgo

COPY image-optimize /usr/local/bin/image-optimize
RUN chmod +x /usr/local/bin/image-optimize

USER node

WORKDIR /frontend

CMD node optional-install.js && npm run dev
