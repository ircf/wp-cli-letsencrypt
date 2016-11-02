# wp-encrypt-cli

This plugin generates a single SAN SSL Certificate from each domain in a WordPress network.

## Requirements
- Certbot (Let's Encrypt client)
- WP CLI

## Install
- Download wp-encrypt-cli to wp-content/plugins
- Enable plugin for network

## Usage (manually)
After creating a new website you should run the following command line :
```
cd /path/to/website && wp --allow-root wp-encrypt-cli
```

## Setup cron task (manually)
```
0 * * * * /usr/local/bin/certbot-auto renew --pre-hook "service nginx stop" --post-hook "service nginx start"
```

## Setup nginx config (manually)
```
server {
  listen 80;
	listen 443 ssl;
	ssl_certificate /etc/letsencrypt/live/yourdomain/fullchain.pem;
	ssl_certificate_key /etc/letsencrypt/live/yourdomain/privkey.pem; 
  ...
}
```
