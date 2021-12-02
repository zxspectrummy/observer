#!/bin/sh -e

if ! id app >/dev/null 2>&1; then
	addgroup -g $OWNER_GID app
	adduser -D -h /var/www/html -G app -u $OWNER_UID app
fi

mkdir -p /home/media/archive \
  /home/media/uploads \
  /home/media/cache \
  /var/www/html/assets/uploads

chown -R www-data /home/media /var/www/html/assets

OBCONF_SALT=$(apg -m 16 -x 20 -a 1 -n 1 -M NCL)
UPDATES_PASSWORD_HASH=$(php -r "echo password_hash('updates',PASSWORD_DEFAULT).\"\n\";")

cat > /var/www/html/config.php <<EOF
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
?>
EOF

if [ -n "$OBCONF_PASS" ]; then
  php tools/password_change.php admin $OBCONF_PASS
fi
