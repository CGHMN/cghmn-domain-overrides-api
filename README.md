# CGHMN Domain Overrides API

This HTTP endpoint allows you to fetch a list of all domains the CGHMN overrides.

## Requirements

This API requires only PHP8.4 and some web server capable of handling PHP scripts, e.g. `nginx` with `php8.4-fpm`.

## Example NGINX configuration

Assuming this repository is checked out to `/opt/cghmn-domain-overrides-api` on a Debian host:

```
server {
	listen 80 default_server;
	server_name _;

	root /opt/cghmn-domain-overrides-api/web;

	location / {
		try_files $uri $uri/ /router.php;
	}

	location ~ \.php$ {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/run/php/php8.4-fpm.sock;
	}
}
```
