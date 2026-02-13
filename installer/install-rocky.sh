#!/usr/bin/env bash
set -euo pipefail

MODE="native"
WEBSERVER="nginx"
DOMAIN=""
EMAIL=""
APP_DIR="/opt/profios-cms"
DB_NAME="profios_cms"
DB_USER="profios_user"
DB_PASS=""
DB_ROOT_PASS=""
REDIS_PASS=""
REDIS_SERVICE_NAME="redis"
REDIS_CONF_PATH="/etc/redis/redis.conf"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="/var/backups/profios-cms"
LAST_BACKUP_FILE="/var/tmp/profios-cms-last-backup.txt"
PROGRESS_FILE=""
LAST_PROGRESS=0
LAST_STEP="init"

usage() {
  cat <<USAGE
Usage: sudo bash installer/install-rocky.sh [options]

Options:
  --mode native|docker      Install mode (default: native)
  --webserver nginx|apache|hybrid  Web server for native mode (default: nginx)
  --domain example.com      Domain for SSL setup (optional)
  --email you@example.com   Email for Let's Encrypt (optional)
  --app-dir /opt/profios    Installation directory (default: /opt/profios-cms)
  --db-name profios_cms     DB name (auto-randomized if default)
  --db-user profios_user    DB user (auto-randomized if default)
  --db-pass secret          DB password (generated if empty)
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
    echo "Run as root: sudo bash installer/install-rocky.sh ..."
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
  local conf="$REDIS_CONF_PATH"
  if [[ -f "$conf" ]]; then
    if grep -qE '^#?requirepass ' "$conf"; then
      sed -E -i "s!^#?requirepass .*!requirepass $REDIS_PASS!" "$conf"
    else
      echo "requirepass $REDIS_PASS" >> "$conf"
    fi
  fi
}

install_redis_stack_rocky() {
  if dnf -y install redis; then
    REDIS_SERVICE_NAME="redis"
    REDIS_CONF_PATH="/etc/redis/redis.conf"
    return
  fi

  if dnf -y install valkey; then
    REDIS_SERVICE_NAME="valkey"
    REDIS_CONF_PATH="/etc/valkey/valkey.conf"
    return
  fi

  if dnf -y install redis7; then
    REDIS_SERVICE_NAME="redis"
    REDIS_CONF_PATH="/etc/redis/redis.conf"
    return
  fi

  if dnf -y install redis6; then
    REDIS_SERVICE_NAME="redis"
    REDIS_CONF_PATH="/etc/redis/redis.conf"
    return
  fi

  echo "Unable to install redis/valkey package on this Rocky host."
  exit 1
}

install_production_templates() {
  write_progress 72 "running" "Applying production tuning templates..." "tuning"

  if [[ -f "$APP_DIR/deploy/php/opcache-profios.ini" ]]; then
    cp "$APP_DIR/deploy/php/opcache-profios.ini" /etc/php.d/99-profios-opcache.ini || true
  fi

  if [[ -f "$APP_DIR/deploy/php/php-fpm-profios.conf" ]]; then
    cp "$APP_DIR/deploy/php/php-fpm-profios.conf" /etc/php-fpm.d/www.conf || true
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

install_docker_engine_rocky() {
  write_progress 8 "running" "Checking Docker engine..." "docker"
  if command -v docker >/dev/null 2>&1; then
    dnf -y install rsync curl
    return
  fi

  write_progress 12 "running" "Installing Docker engine..." "docker"
  dnf -y install dnf-plugins-core curl rsync
  dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
  dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
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

install_php_repos_rocky() {
  local rhel_ver
  rhel_ver="$(rpm -E %rhel)"
  dnf -y install epel-release
  dnf -y install "https://rpms.remirepo.net/enterprise/remi-release-${rhel_ver}.rpm"
  dnf -y module reset php
  dnf -y module enable php:remi-8.3
}

install_mode_docker() {
  write_progress 5 "running" "Starting Docker mode installation..." "start"
  install_docker_engine_rocky
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
}

install_mode_native() {
  write_progress 5 "running" "Starting Rocky native installation..." "start"
  dnf -y update
  install_php_repos_rocky

  write_progress 12 "running" "Installing Rocky packages..." "packages"
  dnf -y install --allowerasing \
    nginx httpd varnish mariadb-server certbot \
    php php-fpm php-cli php-mysqlnd php-pecl-redis php-curl php-xml php-mbstring php-zip php-gd php-opcache \
    curl rsync unzip tar cronie logrotate git || true

  dnf -y install --allowerasing \
    nginx httpd varnish mariadb-server certbot \
    php php-fpm php-cli php-mysqlnd php-redis php-curl php-xml php-mbstring php-zip php-gd php-opcache \
    curl rsync unzip tar cronie logrotate git || true

  dnf -y install \
    nginx httpd varnish mariadb-server certbot \
    php php-fpm php-cli php-mysqlnd php-curl php-xml php-mbstring php-zip php-gd php-opcache \
    curl rsync unzip tar cronie logrotate git

  if [[ "$WEBSERVER" == "apache" ]]; then
    dnf -y install python3-certbot-apache
  else
    dnf -y install python3-certbot-nginx
  fi
  install_redis_stack_rocky

  create_backup
  prepare_app_dir
  configure_env_defaults
  configure_stack_credentials

  write_progress 30 "running" "Configuring MariaDB database..." "database"
  systemctl enable --now mariadb
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

  write_progress 42 "running" "Running CMS migrations..." "migration"
  php "$APP_DIR/bin/migrate.php" || true

  write_progress 54 "running" "Configuring web stack..." "webserver"
  PHP_FPM_SOCK="/run/php-fpm/www.sock"

  if [[ "$WEBSERVER" == "apache" || "$WEBSERVER" == "hybrid" ]]; then
    cat > /etc/httpd/conf.d/profios-cms.conf <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN:-_}
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \\.php$>
        SetHandler "proxy:unix:$PHP_FPM_SOCK|fcgi://localhost/"
    </FilesMatch>

    ErrorLog /var/log/httpd/profios-cms-error.log
    CustomLog /var/log/httpd/profios-cms-access.log combined
</VirtualHost>
EOF
  fi

  if [[ "$WEBSERVER" == "hybrid" ]]; then
    sed -E -i 's/^Listen[[:space:]]+80$/Listen 8081/' /etc/httpd/conf/httpd.conf
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8081>/g' /etc/httpd/conf.d/profios-cms.conf

    cat > /etc/nginx/conf.d/profios-cms.conf <<EOF
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
    cp "$APP_DIR/deploy/nginx/profios-cms.conf" /etc/nginx/conf.d/profios-cms.conf
    sed -i "s#root /var/www/profios-cms/public;#root $APP_DIR/public;#" /etc/nginx/conf.d/profios-cms.conf
    sed -i "s#unix:/run/php/php-fpm.sock#unix:/run/php-fpm/www.sock#g" /etc/nginx/conf.d/profios-cms.conf
  fi

  rm -f /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/welcome.conf || true

  write_progress 62 "running" "Installing Adminer..." "adminer"
  curl -fsSL https://www.adminer.org/latest.php -o "$APP_DIR/public/adminer.php"
  chown -R apache:apache "$APP_DIR/storage"
  chown apache:apache "$APP_DIR/public/adminer.php" || true

  write_progress 76 "running" "Enabling services..." "services"
  configure_native_redis_auth
  install_production_templates
  systemctl enable --now php-fpm "$REDIS_SERVICE_NAME" varnish crond

  if [[ "$WEBSERVER" == "apache" ]]; then
    systemctl enable --now httpd
    apachectl configtest
    systemctl restart httpd
  elif [[ "$WEBSERVER" == "hybrid" ]]; then
    systemctl enable --now httpd nginx
    apachectl configtest
    nginx -t
    systemctl restart httpd
    systemctl restart varnish
    systemctl restart nginx
  else
    systemctl enable --now nginx
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
    systemctl enable --now certbot-renew.timer || true
  fi

  write_progress 100 "completed" "Rocky native stack installed. Open /setup to continue." "done"
  echo
  echo "Rocky deployment completed."
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
write_progress 1 "running" "Rocky installer initialized." "init"

case "$MODE" in
  docker) install_mode_docker ;;
  native) install_mode_native ;;
  *) echo "Invalid mode: $MODE"; exit 1 ;;
esac
