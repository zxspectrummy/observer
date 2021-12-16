#!/bin/sh -e

if ! id app >/dev/null 2>&1; then
  addgroup -g $OWNER_GID app
  adduser -D -h /opt/observer/app -G app -u $OWNER_UID app
fi

mkdir -p /home/media/archive \
  /home/media/uploads \
  /home/media/cache \
  /${SCRIPT_ROOT}/assets/uploads

chown -R www-data /home/media ${SCRIPT_ROOT}/assets

OBCONF_SALT=$(apg -m 16 -x 20 -a 1 -n 1 -M NCL)
UPDATES_PASSWORD_HASH=$(php -r "echo password_hash('updates',PASSWORD_DEFAULT).\"\n\";")

cat >/opt/observer/app/config.php <<EOF
<?php
const OB_HASH_SALT = '$OBCONF_SALT';
const OB_DB_USER = '$MYSQL_USER';
const OB_DB_PASS = '$MYSQL_PASSWORD';
const OB_DB_HOST = 'db';
const OB_DB_NAME = 'obdb';
const OB_MEDIA = '/home/media';
const OB_MEDIA_UPLOADS = '/home/media/uploads';
const OB_MEDIA_ARCHIVE = '/home/media/archive';
const OB_CACHE = '/home/media/cache';
const OB_SITE = '$OBCONF_URL';
const OB_EMAIL_REPLY = '$OBCONF_EMAIL';
const OB_EMAIL_FROM = 'OpenBroadcaster';
const OB_UPDATES_USER = 'updates';
const OB_UPDATES_PW = '$UPDATES_PASSWORD_HASH';
const OB_ASSETS = '${SCRIPT_ROOT}/assets'
?>
EOF

cp -r /opt/observer/app/* /var/www/html/

if [ -n "$ADMIN_PASSWORD" ]; then
  php /var/www/html/tools/password_change.php admin $ADMIN_PASSWORD
fi
