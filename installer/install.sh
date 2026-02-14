#!/usr/bin/env bash
set -euo pipefail

MODE="docker"
WEBSERVER="nginx"
DOMAIN=""
EMAIL=""
APP_DIR="/opt/profios-cms"
DB_NAME="profios_cms"
DB_USER="profios_user"
DB_PASS=""
DB_ROOT_PASS=""
REDIS_PASS=""
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="/var/backups/profios-cms"
LAST_BACKUP_FILE="/var/tmp/profios-cms-last-backup.txt"
PROGRESS_FILE=""
LAST_PROGRESS=0
LAST_STEP="init"

usage() {
  cat <<USAGE
Usage: sudo bash installer/install.sh [options]

Options:
  --mode docker|native      Install mode (default: docker)
  --webserver nginx|apache|hybrid  Web server for native mode (default: nginx)
  --domain example.com      Domain for SSL setup (optional)
  --email you@example.com   Email for Let's Encrypt (optional)
  --app-dir /opt/profios    Installation directory (default: /opt/profios-cms)
  --db-name profios_cms     MySQL database name (native mode)
  --db-user profios_user    MySQL user (native mode)
  --db-pass secret          MySQL password (native mode; generated if empty)
  --redis-pass secret       Redis password (generated if empty)
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode) MODE="$2"; shift 2 ;;
    --webserver) WEBSERVER="$2"; shift 2 ;;
    --domain) DOMAIN="$2"; shift 2 ;;
    --email) EMAIL="$2"; shift 2 ;;
    --app-dir) APP_DIR="$2"; shift 2 ;;
    --db-name) DB_NAME="$2"; shift 2 ;;
    --db-user) DB_USER="$2"; shift 2 ;;
    --db-pass) DB_PASS="$2"; shift 2 ;;
    --redis-pass) REDIS_PASS="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown arg: $1"; usage; exit 1 ;;
  esac
done

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root: sudo bash installer/install.sh ..."
    exit 1
  fi
}

json_escape() {
  printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

write_progress() {
  local percent="$1"
  local status="$2"
  local message="$3"
  local step="${4:-install}"

  LAST_PROGRESS="$percent"
  LAST_STEP="$step"

  if [[ -z "$PROGRESS_FILE" ]]; then
    return
  fi

  mkdir -p "$(dirname "$PROGRESS_FILE")"
  local msg_escaped
  msg_escaped="$(json_escape "$message")"
  local step_escaped
  step_escaped="$(json_escape "$step")"
  cat > "$PROGRESS_FILE" <<EOF
{"percent":$percent,"status":"$status","step":"$step_escaped","message":"$msg_escaped","updated_at":"$(date -Iseconds)"}
EOF
}

on_error() {
  local code=$?
  write_progress "${LAST_PROGRESS:-0}" "failed" "Installer failed during step: ${LAST_STEP:-unknown}" "${LAST_STEP:-failed}"
  exit $code
}

generate_secret() {
  tr -dc 'A-Za-z0-9' </dev/urandom | head -c "${1:-24}"
}

set_env_value() {
  local key="$1"
  local value="$2"
  local env_file="$APP_DIR/.env"
  local escaped
  escaped="${value//\\/\\\\}"
  escaped="${escaped//&/\\&}"
  escaped="${escaped//#/\\#}"
  if grep -q "^${key}=" "$env_file"; then
    sed -i "s#^${key}=.*#${key}=\"${escaped}\"#" "$env_file"
  else
    echo "${key}=\"${value}\"" >> "$env_file"
  fi
}

configure_docker_compose_env() {
  local compose_env="$APP_DIR/deploy/compose/.env"
  cat > "$compose_env" <<EOF
MYSQL_ROOT_PASSWORD=$DB_ROOT_PASS
MYSQL_DATABASE=$DB_NAME
MYSQL_USER=$DB_USER
MYSQL_PASSWORD=$DB_PASS
REDIS_PASSWORD=$REDIS_PASS
EOF
}

configure_native_redis_auth() {
  local conf="/etc/redis/redis.conf"
  if [[ -f "$conf" ]]; then
    if grep -qE '^#?requirepass ' "$conf"; then
      sed -E -i "s!^#?requirepass .*!requirepass $REDIS_PASS!" "$conf"
    else
      echo "requirepass $REDIS_PASS" >> "$conf"
    fi
  fi
}

install_production_templates() {
  write_progress 72 "running" "Applying production tuning templates..." "tuning"

  if [[ -f "$APP_DIR/deploy/php/opcache-profios.ini" ]]; then
    for dir in /etc/php/*/fpm/conf.d; do
      [[ -d "$dir" ]] || continue
      cp "$APP_DIR/deploy/php/opcache-profios.ini" "$dir/99-profios-opcache.ini" || true
    done
    for dir in /etc/php/*/cli/conf.d; do
      [[ -d "$dir" ]] || continue
      cp "$APP_DIR/deploy/php/opcache-profios.ini" "$dir/99-profios-opcache.ini" || true
    done
  fi

  if [[ -f "$APP_DIR/deploy/php/php-fpm-profios.conf" ]]; then
    for dir in /etc/php/*/fpm/pool.d; do
      [[ -d "$dir" ]] || continue
      cp "$APP_DIR/deploy/php/php-fpm-profios.conf" "$dir/www.conf" || true
    done
  fi

  if [[ -f "$APP_DIR/deploy/systemd/profios-backup.service" ]]; then
    cp "$APP_DIR/deploy/systemd/profios-backup.service" /etc/systemd/system/profios-backup.service
  fi
  if [[ -f "$APP_DIR/deploy/systemd/profios-backup.timer" ]]; then
    cp "$APP_DIR/deploy/systemd/profios-backup.timer" /etc/systemd/system/profios-backup.timer
  fi
  if [[ -f "$APP_DIR/deploy/systemd/profios-monitor.service" ]]; then
    cp "$APP_DIR/deploy/systemd/profios-monitor.service" /etc/systemd/system/profios-monitor.service
  fi
  if [[ -f "$APP_DIR/deploy/systemd/profios-monitor.timer" ]]; then
    cp "$APP_DIR/deploy/systemd/profios-monitor.timer" /etc/systemd/system/profios-monitor.timer
  fi
  if [[ -f "$APP_DIR/deploy/systemd/cert-renew.service" ]]; then
    cp "$APP_DIR/deploy/systemd/cert-renew.service" /etc/systemd/system/cert-renew.service
  fi
  if [[ -f "$APP_DIR/deploy/systemd/cert-renew.timer" ]]; then
    cp "$APP_DIR/deploy/systemd/cert-renew.timer" /etc/systemd/system/cert-renew.timer
  fi
  if [[ -f "$APP_DIR/deploy/logging/logrotate-profios.conf" ]]; then
    cp "$APP_DIR/deploy/logging/logrotate-profios.conf" /etc/logrotate.d/profios-cms
  fi

  systemctl daemon-reload
  systemctl enable --now profios-backup.timer profios-monitor.timer cert-renew.timer || true
}

configure_stack_credentials() {
  write_progress 26 "running" "Generating secure DB and Redis credentials..." "credentials"

  if [[ -z "$DB_NAME" || "$DB_NAME" == "profios_cms" ]]; then
    DB_NAME="profios_$(generate_secret 10 | tr 'A-Z' 'a-z')"
  fi
  if [[ -z "$DB_USER" || "$DB_USER" == "profios_user" ]]; then
    DB_USER="u_$(generate_secret 10 | tr 'A-Z' 'a-z')"
  fi

  if [[ -z "$DB_PASS" ]]; then
    DB_PASS="$(generate_secret 24)"
  fi
  if [[ -z "$REDIS_PASS" ]]; then
    REDIS_PASS="$(generate_secret 24)"
  fi
  if [[ -z "$DB_ROOT_PASS" ]]; then
    DB_ROOT_PASS="$(generate_secret 24)"
  fi

  set_env_value "STACK_AUTO_CREDENTIALS" "true"
  set_env_value "DB_DATABASE" "$DB_NAME"
  set_env_value "DB_USERNAME" "$DB_USER"
  set_env_value "DB_PASSWORD" "$DB_PASS"
  set_env_value "REDIS_PASSWORD" "$REDIS_PASS"
  set_env_value "SESSION_NAME" "profios_$(generate_secret 20 | tr 'A-Z' 'a-z')"
  set_env_value "APP_KEY" "$(generate_secret 64)"
  set_env_value "APP_INSTALLED" "false"

  if [[ "$MODE" == "docker" ]]; then
    set_env_value "DB_HOST" "db"
    set_env_value "REDIS_HOST" "redis"
  else
    set_env_value "DB_HOST" "127.0.0.1"
    set_env_value "REDIS_HOST" "127.0.0.1"
  fi
}

install_docker_engine() {
  write_progress 8 "running" "Checking Docker engine..." "docker"
  if command -v docker >/dev/null 2>&1; then
    apt-get update
    apt-get install -y rsync
    return
  fi

  write_progress 12 "running" "Installing Docker engine..." "docker"
  apt-get update
  apt-get install -y ca-certificates curl gnupg rsync
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo ${VERSION_CODENAME}) stable" > /etc/apt/sources.list.d/docker.list
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
}

prepare_app_dir() {
  write_progress 20 "running" "Preparing application directory..." "filesystem"
  mkdir -p "$APP_DIR"
  rsync -a --delete --exclude ".git" --exclude "storage/cache/*" --exclude "storage/logs/*" "$SOURCE_DIR/" "$APP_DIR/"
  chown -R root:root "$APP_DIR"
}

create_backup() {
  write_progress 16 "running" "Creating backup snapshot..." "backup"
  if [[ -d "$APP_DIR" ]] && [[ -n "$(ls -A "$APP_DIR" 2>/dev/null || true)" ]]; then
    mkdir -p "$BACKUP_DIR"
    local stamp
    stamp="$(date +%Y%m%d-%H%M%S)"
    local backup_path="$BACKUP_DIR/profios-cms-$stamp.tar.gz"
    tar -czf "$backup_path" -C "$(dirname "$APP_DIR")" "$(basename "$APP_DIR")"
    echo "$backup_path" > "$LAST_BACKUP_FILE"
    echo "Backup created: $backup_path"
  fi
}

configure_env_defaults() {
  write_progress 24 "running" "Configuring environment defaults..." "env"
  if [[ ! -f "$APP_DIR/.env" ]]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
  fi
  if [[ -n "$DOMAIN" ]]; then
    sed -i "s#^APP_URL=.*#APP_URL=\"https://$DOMAIN\"#" "$APP_DIR/.env"
  fi
}

apply_runtime_permissions() {
  mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/cache"
  touch "$APP_DIR/storage/logs/app.log"

  chown -R www-data:www-data "$APP_DIR/storage"
  chown www-data:www-data "$APP_DIR/public/adminer.php" 2>/dev/null || true
  chown www-data:www-data "$APP_DIR/.env" 2>/dev/null || true

  chmod 755 "$APP_DIR" "$APP_DIR/public" "$APP_DIR/storage" "$APP_DIR/storage/logs" "$APP_DIR/storage/cache" 2>/dev/null || true
  chmod 664 "$APP_DIR/storage/logs/app.log" 2>/dev/null || true
  chmod 640 "$APP_DIR/.env" 2>/dev/null || true
}

install_mode_docker() {
  write_progress 5 "running" "Starting Docker mode installation..." "start"
  install_docker_engine
  create_backup
  prepare_app_dir
  configure_env_defaults
  configure_stack_credentials
  configure_docker_compose_env

  write_progress 40 "running" "Starting Docker services..." "docker-compose"
  pushd "$APP_DIR/deploy/compose" >/dev/null
  docker compose up -d --build
  write_progress 70 "running" "Running database migrations..." "migration"
  docker compose exec -T php php /var/www/html/bin/migrate.php || true
  popd >/dev/null

  write_progress 100 "completed" "Docker stack installed. Open /setup to continue." "done"
  echo
  echo "Docker deployment is up."
  echo "Open: http://<server-ip>/setup"
  echo "Adminer: http://<server-ip>:8080"
  echo "DB/Redis credentials were auto-generated and injected into .env"
}

install_mode_native() {
  write_progress 5 "running" "Starting native mode installation..." "start"
  apt-get update
  if [[ "$WEBSERVER" == "apache" ]]; then
    write_progress 12 "running" "Installing Apache + PHP-FPM + stack..." "packages"
    apt-get install -y apache2 libapache2-mod-fcgid php-fpm php-cli php-mysql php-redis php-curl php-xml php-mbstring php-zip php-gd mysql-server redis-server varnish certbot python3-certbot-apache rsync curl unzip
  elif [[ "$WEBSERVER" == "hybrid" ]]; then
    write_progress 12 "running" "Installing Nginx + Apache + PHP-FPM + Varnish stack..." "packages"
    apt-get install -y nginx apache2 libapache2-mod-fcgid php-fpm php-cli php-mysql php-redis php-curl php-xml php-mbstring php-zip php-gd mysql-server redis-server varnish certbot python3-certbot-nginx rsync curl unzip
  else
    write_progress 12 "running" "Installing Nginx + PHP-FPM + stack..." "packages"
    apt-get install -y nginx php-fpm php-cli php-mysql php-redis php-curl php-xml php-mbstring php-zip php-gd mysql-server redis-server varnish certbot python3-certbot-nginx rsync curl unzip
  fi

  create_backup
  prepare_app_dir
  configure_env_defaults
  configure_stack_credentials

  write_progress 30 "running" "Configuring MySQL database..." "database"

  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \\`$DB_NAME\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \\`$DB_NAME\\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

  write_progress 42 "running" "Running CMS migrations..." "migration"
  php "$APP_DIR/bin/migrate.php" || true

  write_progress 54 "running" "Configuring web server and FastCGI..." "webserver"
  PHP_FPM_SOCK="/run/php/php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm.sock"
  if [[ "$WEBSERVER" == "apache" || "$WEBSERVER" == "hybrid" ]]; then
    APACHE_CONF_DST="/etc/apache2/sites-available/profios-cms.conf"
    cat > "$APACHE_CONF_DST" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN:-_}
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \\.php$>
        SetHandler \"proxy:unix:$PHP_FPM_SOCK|fcgi://localhost/\"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/profios-cms-error.log
    CustomLog \${APACHE_LOG_DIR}/profios-cms-access.log combined
</VirtualHost>
EOF
    a2enmod proxy_fcgi setenvif rewrite headers
    a2dissite 000-default || true
    a2ensite profios-cms.conf
  fi

  if [[ "$WEBSERVER" == "hybrid" ]]; then
    # Apache receives dynamic app requests behind varnish.
    sed -i 's/Listen 80/Listen 8081/g' /etc/apache2/ports.conf
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8081>/g' /etc/apache2/sites-available/profios-cms.conf

    # Nginx is public-facing and serves static files directly.
    NGINX_CONF_DST="/etc/nginx/sites-available/profios-cms.conf"
    cat > "$NGINX_CONF_DST" <<EOF
server {
    listen 80;
    server_name ${DOMAIN:-_};
    root $APP_DIR/public;
    index index.php;

    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|webp|avif|woff2?)$ {
        try_files \$uri =404;
        expires 30d;
        add_header Cache-Control "public, max-age=2592000, immutable";
        access_log off;
    }

    location / {
        proxy_pass http://127.0.0.1:6081;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
    ln -sf "$NGINX_CONF_DST" /etc/nginx/sites-enabled/profios-cms.conf
    rm -f /etc/nginx/sites-enabled/default

    # Varnish caches dynamic responses, bypasses admin/setup/authenticated traffic.
    cat > /etc/varnish/default.vcl <<'EOF'
vcl 4.1;

backend default {
    .host = "127.0.0.1";
    .port = "8081";
}

sub vcl_recv {
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    if (req.url ~ "^/admin" || req.url ~ "^/setup" || req.url ~ "^/adminer.php") {
        return (pass);
    }
    if (req.http.Cookie) {
        return (pass);
    }
}

sub vcl_backend_response {
    if (beresp.ttl <= 0s) {
        set beresp.ttl = 120s;
    }
    set beresp.grace = 6h;
}
EOF
  elif [[ "$WEBSERVER" == "nginx" ]]; then
    NGINX_CONF_SRC="$APP_DIR/deploy/nginx/profios-cms.conf"
    NGINX_CONF_DST="/etc/nginx/sites-available/profios-cms.conf"
    cp "$NGINX_CONF_SRC" "$NGINX_CONF_DST"
    sed -i "s#root /var/www/profios-cms/public;#root $APP_DIR/public;#" "$NGINX_CONF_DST"
    sed -i "s#php-fpm.sock#php/$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm.sock#" "$NGINX_CONF_DST" || true
    ln -sf "$NGINX_CONF_DST" /etc/nginx/sites-enabled/profios-cms.conf
    rm -f /etc/nginx/sites-enabled/default
  fi

  write_progress 62 "running" "Installing Adminer..." "adminer"
  curl -fsSL https://www.adminer.org/latest.php -o "$APP_DIR/public/adminer.php"

  apply_runtime_permissions

  write_progress 76 "running" "Enabling services (PHP-FPM/MySQL/Redis/Varnish/Web)..." "services"
  configure_native_redis_auth
  install_production_templates
  PHP_FPM_SERVICE="$(systemctl list-unit-files | awk '/php.*-fpm\\.service/{print $1; exit}')"
  if [[ -n "$PHP_FPM_SERVICE" ]]; then
    systemctl enable --now "$PHP_FPM_SERVICE"
  fi
  if [[ "$WEBSERVER" == "apache" ]]; then
    systemctl enable --now apache2 mysql redis-server varnish || true
    apache2ctl configtest
    systemctl restart apache2
  elif [[ "$WEBSERVER" == "hybrid" ]]; then
    systemctl enable --now apache2 nginx mysql redis-server varnish || true
    apache2ctl configtest
    nginx -t
    systemctl restart apache2
    systemctl restart varnish
    systemctl restart nginx
  else
    systemctl enable --now nginx mysql redis-server varnish || true
    nginx -t
    systemctl restart nginx
  fi

  write_progress 88 "running" "Configuring AutoSSL..." "ssl"
  if [[ -n "$DOMAIN" && -n "$EMAIL" ]]; then
    if [[ "$WEBSERVER" == "apache" ]]; then
      certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect
    else
      certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect
    fi
    systemctl enable --now certbot.timer
  fi

  write_progress 100 "completed" "Native stack installed. Open /setup to continue." "done"
  echo
  echo "Native deployment completed."
  echo "DB user: $DB_USER"
  echo "DB pass: $DB_PASS"
  echo "Redis pass: $REDIS_PASS"
  echo "Adminer: http://${DOMAIN:-<server-ip>}/adminer.php"
  echo "Open: http://${DOMAIN:-<server-ip>}/setup"
}

require_root

if [[ "$MODE" == "native" && "$WEBSERVER" != "nginx" && "$WEBSERVER" != "apache" && "$WEBSERVER" != "hybrid" ]]; then
  echo "Invalid --webserver value: $WEBSERVER"
  exit 1
fi

PROGRESS_FILE="$APP_DIR/storage/install-progress.json"
trap on_error ERR
write_progress 1 "running" "Installer initialized." "init"

case "$MODE" in
  docker) install_mode_docker ;;
  native) install_mode_native ;;
  *) echo "Invalid mode: $MODE"; exit 1 ;;
esac
