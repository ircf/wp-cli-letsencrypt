![travis-ci status](https://travis-ci.org/ircf/wp-cli-letsencrypt.svg?branch=master)

# wp-cli-letsencrypt

This plugin generates a single SAN SSL Certificate with all domains in a WordPress network from CLI using [Let's Encrypt](https://letsencrypt.org/).

Network subdomains and domains from [WordPress MU Domain Mapping](https://wordpress.org/plugins/wordpress-mu-domain-mapping/) can be optionally included (see Create the certificate).

This plugin does NOT provide a web interface like [WP Encrypt](https://fr.wordpress.org/plugins/wp-encrypt/) does, for many reasons :
- wp-encrypt does that just fine, but :
- giving write access to your SSL certificates from web is NOT recommended
- generating a large SAN (100+ domains) from web may not work (timeout)
- after generating your SAN certificate you need to reload your web server, this can't/shouldn't be done from web
- CLI is required to setup and renew your SSL certificate, so why not using it for generating it ?

WARNING : If you have any existing SSL Let's encrypt certificate on your server, this plugin will remove them !
If you plan to use multiple Let's encrypt certificates on your server, we recommend NOT to use this plugin for now.

## Requirements
- Certbot (Let's Encrypt client)
```
cd /usr/local/bin/
wget https://dl.eff.org/certbot-auto
chmod a+x certbot-auto
certbot-auto register --agree-tos
```

- WP CLI
```
cd /usr/local/bin/
wget -O wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod a+x wp
```
## Install
- Download wp-cli-letsencrypt to wp-content/plugins
- Enable plugin for network

## Setup and usage with nginx

### Setup ACME challenge in nginx config

For Let's Encrypt to deliver your certificate, you need to setup a challenge URL in your web server :
Add the following location block to your nginx vhost (```nano /etc/nginx/sites-enabled/yourdomain```) :

```
server {
  ...
  # allow let's encrypt acme challenge
  location ^~ /.well-known/acme-challenge/ {
    allow all;
  }
  ...
}
```

And reload nginx (```service nginx reload```)

### Create the certificate

The following command has to be executed once after install and each time after creating a new website on your WP network,
in order to create or update your SAN SSL certificate :
```
cd /path/to/website && wp --allow-root letsencrypt && service nginx reload
```

By default the certificate won't include network subdomains and domains from wp_domain_mapping.
Run the help command if you want to list available options :

```
cd /path/to/website && wp --allow-root help letsencrypt
```

### Setup cron task

Add this command line to your crontab (```crontab -e```) :

```
0 0 * * * /usr/local/bin/certbot-auto renew --post-hook "service nginx reload"
```

Each day at midnight Certbot will check, renew your certificate and restart nginx ONLY if needed (~ each 3 month).

### Setup SSL in nginx config

Add the SSL directives to your nginx vhost (```nano /etc/nginx/sites-enabled/yourdomain```) :

```
server {
  listen 80;
  listen 443 ssl;
  ssl_certificate /etc/letsencrypt/live/yourdomain/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/yourdomain/privkey.pem;
  ...
}
```

And reload nginx (```service nginx reload```)

That's it ! Now to switch all your websites to HTTPS, you have to change the blog URL 
in WordPress and your theme, or just use a plugin like [Really Simple SSL](https://fr.wordpress.org/plugins/really-simple-ssl/) that will do the job for you.

You can also follow [tutorials to optimize you ssl config](https://bjornjohansen.no/optimizing-https-nginx).
