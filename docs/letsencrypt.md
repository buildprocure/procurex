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

## Automated deployment

`.github/workflows/deploy.yml` handles certificate setup during deployment:

- It stops the current containers before certificate maintenance.
- It creates `/etc/letsencrypt`, `/var/lib/letsencrypt`, and `/var/www/certbot` if they do not exist.
- It issues the initial certificate with the Certbot Docker image when `fullchain.pem` or `privkey.pem` is missing.
- It runs a Certbot renewal check when the certificate and renewal config already exist.
- It starts the containers after the certificate files are present.

## Renewal

Renewal runs automatically during deployment when this file exists:

```text
/etc/letsencrypt/renewal/buildprocure.com.conf
```

The deploy workflow uses standalone validation while the web container is stopped, so port 80 must point to the deployment server.

Optionally set the GitHub Actions secret `LETSENCRYPT_EMAIL` to register the certificate with an email address. If the secret is not set, Certbot runs with `--register-unsafely-without-email`.
