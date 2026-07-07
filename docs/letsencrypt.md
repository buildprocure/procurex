# Let's Encrypt SSL

The ProcureX image expects certificates from the host at:

```text
/etc/letsencrypt/live/buildprocure.com/fullchain.pem
/etc/letsencrypt/live/buildprocure.com/privkey.pem
```

The web container also mounts `/var/www/certbot` and serves HTTP-01 challenge files from:

```text
/.well-known/acme-challenge/
```

## Initial certificate

On the server, stop the web container so Certbot can bind to port 80 and issue the free certificate:

```bash
sudo mkdir -p /var/www/certbot

docker compose stop web

sudo certbot certonly --standalone \
  -d buildprocure.com \
  -d www.buildprocure.com \
  -d hub.buildprocure.com \
  -d hirenow.buildprocure.com \
  -d ncees.buildprocure.com
```

Then start the web container with the issued certificate mounted:

```bash
docker compose up -d web
```

## Renewal

Let Certbot renew automatically and restart the web container after renewal:

```bash
sudo certbot renew --webroot -w /var/www/certbot --deploy-hook "docker compose -f /path/to/docker-compose.yml restart web"
```

Replace `/path/to/docker-compose.yml` with the live compose file path on the server.
