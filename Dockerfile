FROM php:8.3-cli-alpine

WORKDIR /app
COPY src/ src/
COPY meters/ meters/
COPY bin/ bin/
COPY public/ public/

RUN mkdir -p /data /app/config
