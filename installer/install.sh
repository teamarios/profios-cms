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
  --webserver nginx|apache  Web server for native mode (default: nginx)
  --domain example.com      Domain for SSL setup (optional)
  --email you@example.com   Email for Let's Encrypt (optional)
  --app-dir /opt/profios    Installation directory (default: /opt/profios-cms)
  --db-name profios_cms     MySQL database name (native mode)
  --db-user profios_user    MySQL user (native mode)
  --db-pass secret          MySQL password (native mode; generated if empty)
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

install_mode_docker() {
  write_progress 5 "running" "Starting Docker mode installation..." "start"
  install_docker_engine
  create_backup
  prepare_app_dir
  configure_env_defaults

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
  write_progress 5 "running" "Starting native mode installation..." "start"
  apt-get update
  if [[ "$WEBSERVER" == "apache" ]]; then
    write_progress 12 "running" "Installing Apache + PHP-FPM + stack..." "packages"
    apt-get install -y apache2 libapache2-mod-fcgid php-fpm php-cli php-mysql php-redis php-curl php-xml php-mbstring php-zip php-gd mysql-server redis-server varnish certbot python3-certbot-apache rsync curl unzip
  else
    write_progress 12 "running" "Installing Nginx + PHP-FPM + stack..." "packages"
    apt-get install -y nginx php-fpm php-cli php-mysql php-redis php-curl php-xml php-mbstring php-zip php-gd mysql-server redis-server varnish certbot python3-certbot-nginx rsync curl unzip
  fi

  create_backup
  prepare_app_dir
  configure_env_defaults

  write_progress 30 "running" "Configuring MySQL database..." "database"
  if [[ -z "$DB_PASS" ]]; then
    DB_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)"
  fi

  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \\`$DB_NAME\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \\`$DB_NAME\\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

  sed -i "s#^DB_DATABASE=.*#DB_DATABASE=\"$DB_NAME\"#" "$APP_DIR/.env"
  sed -i "s#^DB_USERNAME=.*#DB_USERNAME=\"$DB_USER\"#" "$APP_DIR/.env"
  sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=\"$DB_PASS\"#" "$APP_DIR/.env"
  sed -i "s#^APP_INSTALLED=.*#APP_INSTALLED=\"false\"#" "$APP_DIR/.env"
  write_progress 42 "running" "Running CMS migrations..." "migration"
  php "$APP_DIR/bin/migrate.php" || true

  write_progress 54 "running" "Configuring web server and FastCGI..." "webserver"
  PHP_FPM_SOCK="/run/php/php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm.sock"
  if [[ "$WEBSERVER" == "apache" ]]; then
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
  else
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

  chown -R www-data:www-data "$APP_DIR/storage"
  chown www-data:www-data "$APP_DIR/public/adminer.php" || true

  write_progress 76 "running" "Enabling services (PHP-FPM/MySQL/Redis/Varnish/Web)..." "services"
  PHP_FPM_SERVICE="$(systemctl list-unit-files | awk '/php.*-fpm\\.service/{print $1; exit}')"
  if [[ -n "$PHP_FPM_SERVICE" ]]; then
    systemctl enable --now "$PHP_FPM_SERVICE"
  fi
  if [[ "$WEBSERVER" == "apache" ]]; then
    systemctl enable --now apache2 mysql redis-server varnish || true
    apache2ctl configtest
    systemctl restart apache2
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
  echo "Adminer: http://${DOMAIN:-<server-ip>}/adminer.php"
  echo "Open: http://${DOMAIN:-<server-ip>}/setup"
}

require_root

if [[ "$MODE" == "native" && "$WEBSERVER" != "nginx" && "$WEBSERVER" != "apache" ]]; then
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
