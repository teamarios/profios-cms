# Profios CMS (PHP + MySQL)

SEO-first custom CMS with visual builder, production hardening, and auto-install packaging.

## Core Capabilities

- Setup wizard (`/setup`) writes `.env`, creates schema, creates admin, and seeds defaults.
- SEO controls: meta, schema JSON-LD, internal links, geotagging, sitemap/robots.
- Security controls: CSP, HSTS, force HTTPS option, spam controls, role-based 2FA.
- Performance controls: CWV and PageSpeed-aligned toggles in `/admin/performance`.
- GitHub updates: pull/push from `/admin/updates`.

## Integrated Services

Configured in `/admin/settings`:

- Google Search Console verification token.
- GA4 Measurement ID.
- Server-side GTM URL + GA4 transport URL.
- Sentry DSN/environment/release/sample rate.
- Header/footer code injection for SEO team workflows.

## New Ops Status Page

`/admin/ops` provides live test buttons for:

- Search Console token
- GA4 setup
- SGTM endpoint reachability
- Sentry DSN validity
- CDN/WAF readiness
- SSL + HSTS + HTTPS readiness
- Cert auto-renew template readiness
- OPcache state
- PHP-FPM pool tuning template
- Compression readiness
- Image pipeline readiness
- Backup automation readiness
- Monitoring/alerts readiness
- Log retention/aggregation template readiness
- MySQL health
- Redis health

## Production Templates and Scripts Added

### PHP tuning
- `deploy/php/opcache-profios.ini`
- `deploy/php/php-fpm-profios.conf`

### Web compression
- `deploy/nginx/compression.conf`
- gzip defaults in nginx templates
- Apache `mod_deflate` in vhost template

### Backups and restore test
- `bin/backup.sh`
- `bin/restore-smoke-test.sh`
- systemd templates:
  - `deploy/systemd/profios-backup.service`
  - `deploy/systemd/profios-backup.timer`

### Monitoring/health
- Health endpoints:
  - `GET /healthz`
  - `GET /readyz`
- RUM endpoint:
  - `POST /rum/vitals`
- Monitoring script:
  - `bin/monitor-check.sh`
- systemd templates:
  - `deploy/systemd/profios-monitor.service`
  - `deploy/systemd/profios-monitor.timer`
- Healthcheck service now probes `/healthz`.

### SSL renew templates
- `deploy/systemd/cert-renew.service`
- `deploy/systemd/cert-renew.timer`

### Image optimization pipeline
- `bin/image-pipeline.sh`
- Source: `storage/uploads/originals`
- Output: `storage/uploads/variants` (responsive JPG/WebP/AVIF when supported)

### Log retention and aggregation starter
- `deploy/logging/logrotate-profios.conf`
- `deploy/monitoring/prometheus-rules.yml`

## Installer Enhancements

Native installer (`installer/install.sh`) now applies production templates automatically:

- OPcache ini deployment
- PHP-FPM pool config deployment
- backup/monitor/cert-renew systemd timers
- logrotate policy deployment
- auto-randomized internal credentials and secrets:
  - DB name/user/password
  - Redis password
  - session name
  - app key

## Recommended Request Path

`CDN/WAF -> Nginx -> Varnish -> Apache/PHP-FPM`

## Quick Start

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000/setup`.

## Post-Install Must-Do (UI)

1. Go to `/admin/settings`.
2. Configure Search Console + GA4 + SGTM + Sentry.
3. Enable/check production toggles (CDN, OPcache, Brotli/gzip, non-prod noindex).
4. Go to `/admin/performance` and apply all recommended toggles.
5. Go to `/admin/ops` and run all live tests.

## Auto Installer

### Interactive
```bash
sudo ./installer/profios install
```

### Direct
```bash
sudo bash installer/install.sh --mode native --webserver hybrid --domain example.com --email you@example.com
```

### Docker mode
```bash
sudo bash installer/install.sh --mode docker
```

### Rocky Linux (native)
```bash
sudo bash installer/install-rocky.sh --mode native --webserver hybrid --domain example.com --email you@example.com
```

### Rocky Linux wrapper
```bash
./installer/install-stack-rocky.sh --mode native --webserver hybrid --domain example.com --email you@example.com
```

## Utility Commands

```bash
# backup now
bin/backup.sh

# restore smoke test from latest backup
bin/restore-smoke-test.sh

# generate responsive image variants
bin/image-pipeline.sh

# run health checks used by monitoring
bin/monitor-check.sh
```
