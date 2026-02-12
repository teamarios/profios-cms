# Profios CMS (PHP + MySQL)

SEO-first custom CMS with visual page builder, advanced security, GitHub update controls, Core Web Vitals optimization controls, and auto-install packaging.

## Implemented Features

- Setup wizard (`/setup`) writes `.env`, creates DB schema, admin account, and default content.
- Setup page now shows live server-stack install progress (`/setup/progress`) from installer scripts.
- Admin SEO panel (`/admin/settings`):
  - Header/footer script injection
  - GTM + server-side GTM endpoint
  - Global schema JSON-LD
  - Internal links JSON
  - Geotag fields (lat/lng/region)
- SEO audit scoring per page (score + issues shown in page list/editor).
- SEO one-click fix actions in page editor:
  - Auto-fix meta title/description/canonical
  - Auto-generate schema JSON-LD
  - Auto-fill missing image alt text
- 2FA with Google Authenticator:
  - Enabled during setup
  - OTP URI + secret provisioning
  - Backup codes (generated, regenerated, consumed once)
  - Account security page (`/admin/security`)
  - Role-based enforcement policy via `security_force_2fa_roles`
- GitHub update controls (`/admin/updates`):
  - Save repo URL/branch/git user config
  - Pull latest changes from GitHub
  - Push current CMS updates to GitHub
- Performance Center (`/admin/performance`):
  - Core settings for CSS/JS/image/font/cache/third-party optimization
  - Audit matrix covering CWV + major PageSpeed opportunities

## Performance Metrics Managed

The Performance Center provides settings and recommendation tracking for:

- LCP, INP, CLS, FCP, TTFB
- Render blocking requests
- Font display
- Efficient cache lifetimes
- Layout shift culprits
- Forced reflow
- LCP breakdown / request discovery
- Network dependency tree
- Improve image delivery
- Duplicated JavaScript
- Legacy JavaScript
- Third-party code impact
- Reduce JavaScript / main-thread work
- Reduce unused CSS / JS
- Missing image width/height
- Minify CSS
- Enormous network payloads
- Long main-thread tasks
- User timing marks/measures
- Non-composited animations

## Quick Start

1. Start app:
```bash
php -S localhost:8000 -t public
```
2. Open setup:
- `http://localhost:8000/setup`
3. Fill all fields and install.

## Upgrade Existing Installations

Run idempotent migrator:

```bash
php bin/migrate.php
```

## PocketBase-style Installer

### Single command interactive install
```bash
sudo ./installer/profios install
```

### Health check
```bash
./installer/profios doctor --mode docker
```

### Rollback to last backup
```bash
sudo ./installer/profios rollback
```

### Build distributable package
```bash
./installer/profios package
```

## Full Stack Auto-Install Scripts

### Stack installer wrapper
```bash
./installer/install-stack.sh --mode native --webserver nginx --domain example.com --email you@example.com
```

### Direct installer (all-in-one)
```bash
sudo bash installer/install.sh --mode native --webserver apache --domain example.com --email you@example.com
```

Installs and auto-configures:
- Nginx or Apache
- PHP + PHP-FPM (FastCGI)
- MySQL
- Redis
- Varnish
- Adminer (`/adminer.php` for native, `:8080` for docker)
- Certbot AutoSSL

### Docker mode
```bash
sudo bash installer/install.sh --mode docker
```

### Remote package install
```bash
bash installer/remote-install.sh https://your-cdn/profios-cms.tar.gz --mode docker
```

Installer notes:
- Creates backup snapshot before deployment.
- Writes progress to `storage/install-progress.json`.
- Setup page polls progress in real time.
- Auto-attempts rollback on failed install.
- Runs migration automatically after install.

## Deployment Templates

- Nginx: `deploy/nginx/profios-cms.conf`
- Nginx micro-cache: `deploy/nginx/fastcgi-microcache.conf`
- Apache: `deploy/apache/profios-cms.conf`
- Varnish: `deploy/varnish/default.vcl`
- Docker stack: `deploy/compose/docker-compose.yml`
- systemd restart policy: `deploy/systemd/install-overrides.sh`

## AutoSSL (Let’s Encrypt)

- Nginx:
```bash
bash deploy/ssl/setup-letsencrypt-nginx.sh yourdomain.com you@example.com
```
- Apache:
```bash
bash deploy/ssl/setup-letsencrypt-apache.sh yourdomain.com you@example.com
```

## Recommended Production Stack

- CDN/WAF -> Varnish -> Nginx -> PHP-FPM
- Redis cache
- MySQL primary + backup strategy
- SSL auto-renewal via certbot timer
