# wp-encrypt-cli

This plugin generates a single SAN SSL Certificate with all domains in a WordPress network from CLI.

This plugin does NOT provide a web interface like wp-encrypt does, for many reasons :
- wp-encrypt does that just fine, but :
- giving write access to your SSL certificates from web is NOT recommended
- generating a large SAN (100+ domains) from web may not work (timeout)
- after generating your SAN certificate you need to reload your web server, this can't/shouldn't be done from web
- CLI is required to setup and renew your SSL certificate, so why not using it for generating it ?

## Requirements
- Certbot (Let's Encrypt client)
```
cd /usr/local/bin/
wget https://dl.eff.org/certbot-auto
chmod a+x certbot-auto
```

- WP CLI
```
cd /usr/local/bin/
wget -O wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod a+x wp
```
## Install
- Download wp-encrypt-cli to wp-content/plugins
- Enable plugin for network

## Setup and usage with nginx

### Setup the certificate

This command has to be executed once after install and after creating a new website on your WP network :
```
cd /path/to/website && wp --allow-root wp-encrypt-cli && service nginx reload
```

### Setup cron task

Add this command line to your crontab (```crontab -e```) :

```
0 * * * * /usr/local/bin/certbot-auto renew --pre-hook "service nginx stop" --post-hook "service nginx start"
```

### Setup nginx config

Add the SSL directives and challenge location to your nginx vhost (```nano /etc/nginx/sites-enabled/yourdomain```) :

```
server {
	listen 80;
	listen 443 ssl;
	ssl_certificate /etc/letsencrypt/live/yourdomain/fullchain.pem;
	ssl_certificate_key /etc/letsencrypt/live/yourdomain/privkey.pem;
	
	# allow let's encrypt acme challenge
        location ^~ /.well-known/acme-challenge/ {
	    allow all;
	}
	...
}
```

And restart nginx (```service nginx reload```)
