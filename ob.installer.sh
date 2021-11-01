#!/bin/sh

# Copyright 2012-2020 OpenBroadcaster, Inc.

# This file is part of OpenBroadcaster Server.

# OpenBroadcaster Server is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

# OpenBroadcaster Server is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.

# You should have received a copy of the GNU Affero General Public License
# along with OpenBroadcaster Server. If not, see <http://www.gnu.org/licenses/>.

## install these packages before running this script:
apt-get install apache2 apg mariadb-server libapache2-mod-php7.3 php7.3 php7.3-mysql php7.3-mbstring php7.3-xml php7.3-gd php7.3-curl php7.3-imagick imagemagick ffmpeg vorbis-tools festival libavcodec-extra libavfilter-extra

## As root, run this script as an argument to the bash command, or by setting the executable bit.

## First, let's gather some data from our Administrator about how OpenBroadcaster should be configured on this system:

echo "Welcome to the OpenBroadcaster installation script."
echo ""
echo "I will assist with the installation OpenBroadcaster by asking you questions about your system and then creating an appropriate database and file structure for you."
echo ""
echo "Please enter appropriate values for your system.  Press Enter to accept the default values displayed in [square brackets].  Default values should be sane for a Debian system."
echo ""
echo "First, I need to know about where your files and directories will live and how to set permissions on them"
echo ""
read -p "Please enter the path to store web based files (DocumentRoot): [/var/www/openbroadcaster]" WEBROOT
echo ""
read -p "Please enter the path to store your media files: [/media/openbroadcaster]" MEDIAROOT
echo ""
read -p "Please enter the WebServer username: [www-data]" WEBUSER
echo ""
read -p "Please enter a non-root user to own the openbroadcaster installation: [www-data]" OBUSER
echo ""
echo ""
echo "Next, I would like to know about your MySQL database:"
echo ""
read -p "Would you like me to create your database for you (requires you enter your root mysql user/pass): [y]/n" USEDBRT
if [[ $USEDBRT == "" ]]; then
	USEDBRT=y
fi
if [[ $USEDBRT == "y" ]]; then
	echo ""
	read -p "Please provide a MySQL user with permission to create databases: [root]" DBSU
	echo ""
	read -sp "Please enter a password for the MySQL user just entered (Passwords will not display):" DBSUPASS && echo
elif [[ $USEDBRT == "n" ]]; then
	echo ""
	echo "Okay, I am going to assume you have already created a database and set up a user to log into MySQL as"
else
	echo "Sorry, I need a y or n as an answer.  Please try again"
	exit
fi
echo ""
read -p "Please enter the name of your OpenBroadcaster Database: [openbroadcaster]" OBDBNM
echo ""
read -p "Please provide a user to access the OpenBroadcaster Database: [obdbuser]" OBDBUSER
echo ""
read -sp "Please provide a password for the MySQL user just entered: [press enter to generate a random pass]" OBDBPASS && echo
echo ""
read -p "Please enter the MySQL host: [localhost]" DBHOST
echo ""
read -p "If you need a table prefix for your MySQL database, enter it here:" TBLPRE
echo ""
echo ""
echo "And now, I wish to know how you intend to access your OpenBroadcaster interface:"
echo ""
read -p "If you need a prefix for your CSS, enter it here:" CSSPRE
echo ""
read -p "Please enter the FQDN of the site's URL: [openbroadcaster.example.com]" OBFQDN
echo ""
read -p "Please enter the IP of the server hosting openbroadcaster : [192.168.25.10]" OBIP
echo ""
read -sp "Please provide a password for the OpenBroadcaster Admin user (Passwords will not display):" OBADMINPASS && echo
echo ""
read -sp "If you wish, provide salt for OpenBroadcaster user password: [Press Enter to generate random salt]" $SALT && echo
echo ""
echo ""
echo "Now, I am going to ask some things about how to configure your OpenBroadcaster Installation:"
echo ""
read -p "Please provide a From email address for OB system mail: [noreply@example.com]" OBRPLYML
echo ""
read -p "Please provide a Sender Name for OB system mail: [OpenBroadcaster]" OBMLNM
echo ""
echo ""
echo "Thank You.  I will now install OpenBroadcaster..."

## Now we need some variables defined to make the rest of the script easier to deal with.
## We will also use this section to set any default variables not set by the user

CWD=$(pwd)

if [[ $(echo $WEBROOT) == "" ]]; then
	WEBROOT=/var/www/openbroadcaster
fi
if [[ $(echo $MEDIAROOT) == "" ]]; then
	MEDIAROOT=/media/openbroadcaster
fi
if [[ $(echo $WEBUSER) == "" ]]; then
	WEBUSER=www-data
fi
if [[ $(echo $OBUSER) == "" ]]; then
	OBUSER=www-data
fi
if [[ $(echo $DBSU) == "" ]]; then
	DBSU=root
fi
if [[ $(echo $OBDBNM) == "" ]]; then
	OBDBNM=openbroadcaster
fi
if [[ $(echo $OBDBUSER) == "" ]]; then
	OBDBUSER=obdbuser
fi
if [[ $(echo $OBDBPASS) == "" ]]; then
	OBDBPASS=$(apg -m 16 -x 20 -a 1 -n 1 -M NCL)
fi
if [[ $(echo $DBHOST) == "" ]]; then
	DBHOST=localhost
fi
if [[ $(echo $OBFQDN) == "" ]]; then
	OBFQDN="openbroadcaster.example.com"
fi
if [[ $(echo $OBIP) == "" ]]; then
	OBIP="192.168.25.10"
fi
if [[ $(echo $OBRPLYML) == "" ]]; then
	OBRPLYML=noreply@example.com
fi
if [[ $(echo $OBMLNM) == "" ]]; then
	OBMLNM="OpenBroadcaster"
fi
if [[ $(echo $SALT) == "" ]]; then
	SALT=$(apg -m 16 -x 20 -a 1 -n 1 -M NCL)
fi

## Get the rest of our environment the way it needs to be.

## Dunno if I like this
#if [[ $(grep openbroadcaster /etc/passwd) == "" ]]; then
#	useradd -r -U openbroadcaster
#fi

if [ -e $CWD/ob.apache.conf ]; then
	rm $CWD/ob.apache.conf
fi
if [ -e $CWD/config.php ]; then 
	rm $CWD/config.php
fi
OBSLTPASS=$(php tools/password_hash.php "$OBADMINPASS" "$SALT")

## We will start with the easy stuff first, make the database and populate it.

if [[ $USEDBRT == "y" ]]; then
	mysqladmin -u $DBSU -p"$DBSUPASS" create $OBDBNM
	mysql -u $DBSU -p$DBSUPASS -e "GRANT CREATE,SELECT,INSERT,UPDATE,DELETE,ALTER on $OBDBNM.* to '$OBDBUSER'@'$DBHOST' IDENTIFIED BY '$OBDBPASS';"
fi
mysql -u $OBDBUSER -p"$OBDBPASS" $OBDBNM < $CWD/db/dbclean.sql
mysql -u $OBDBUSER -p"$OBDBPASS" $OBDBNM -e "UPDATE users SET password='$OBSLTPASS' WHERE username='admin';"

echo "Database created and populated..."

## Now let's put create our media directory and populate it:

mkdir -p $MEDIAROOT/{archive,uploads,cache}
chown -R $WEBUSER:$WEBUSER $MEDIAROOT
chmod -R 2770 $MEDIAROOT

echo "Media directory created and ready to use..."

## Now, let's populate our DocumentRoot:

mkdir -p $WEBROOT/assets/uploads
cp -ra $CWD/* $WEBROOT
chown -R $OBUSER:$OBUSER $WEBROOT
chown -R $WEBUSER:$WEBUSER $WEBROOT/assets
chmod -R 2770 $WEBROOT/assets
rm $WEBROOT/ob.installer.sh

echo "Site files are in place..."

## Symlink avconv to ffmpeg to support modern Linux distributions
ln -s /usr/bin/ffmpeg /usr/local/bin/avconv
ln -s /usr/bin/ffprobe /usr/local/bin/avprobe

## Set up any cron jobs:
echo "*/5 * * * * $WEBUSER /usr/bin/php $WEBROOT/cron.php" > /etc/cron.d/openbroadcaster

## We need to create a file full of variables for OB to access the DB and such.  
echo "<?php" >> $WEBROOT/config.php
echo "" >> $WEBROOT/config.php
echo "const OB_HASH_SALT='$SALT';" >> $WEBROOT/config.php
echo "const OB_DB_USER = '$OBDBUSER';" >> $WEBROOT/config.php
echo "const OB_DB_PASS = '$OBDBPASS';" >> $WEBROOT/config.php
echo "const OB_DB_HOST = '$DBHOST';" >> $WEBROOT/config.php
echo "const OB_DB_NAME = '$OBDBNM';" >> $WEBROOT/config.php
echo "const OB_MEDIA='$MEDIAROOT';" >> $WEBROOT/config.php
echo "const OB_MEDIA_UPLOADS='$MEDIAROOT/uploads';" >> $WEBROOT/config.php
echo "const OB_MEDIA_ARCHIVE='$MEDIAROOT/archive';" >> $WEBROOT/config.php
echo "const OB_CACHE='$MEDIAROOT/cache';" >> $WEBROOT/config.php
echo "const OB_SITE = 'http://$OBFQDN';" >> $WEBROOT/config.php
echo "const OB_EMAIL_REPLY = '$OBRPLYML';" >> $WEBROOT/config.php
echo "const OB_EMAIL_FROM = '$OBMLNM';" >> $WEBROOT/config.php

chown $OBUSER:$OBUSER $WEBROOT/config.php
chmod 640 $WEBROOT/config.php

echo "constants file created and populated..."

## Last, we will create an apache config file for our intrepid users...
echo "<VirtualHost $OBIP:80>" >> $CWD/ob.apache.conf
echo "	ServerName $OBFQDN" >> $CWD/ob.apache.conf
echo "	DocumentRoot $WEBROOT" >> $CWD/ob.apache.conf
echo "	<Directory $WEBROOT>" >> $CWD/ob.apache.conf
echo "		Options Indexes FollowSymLinks MultiViews" >> $CWD/ob.apache.conf
echo "		Require	all granted" >> $CWD/ob.apache.conf
echo "	</Directory>" >> $CWD/ob.apache.conf
echo "	<Directory $WEBROOT/.git>" >> $CWD/ob.apache.conf
echo "		Require all denied" >> $CWD/ob.apache.conf
echo "	</Directory>" >> $CWD/ob.apache.conf
echo "	<Directory $WEBROOT/tools>" >> $CWD/ob.apache.conf
echo "		Require all denied" >> $CWD/ob.apache.conf
echo "	</Directory>" >> $CWD/ob.apache.conf
echo "	<Directory $WEBROOT/db>" >> $CWD/ob.apache.conf
echo "		Require all denied" >> $CWD/ob.apache.conf
echo "	</Directory>" >> $CWD/ob.apache.conf
echo "ErrorLog /var/log/apache2/$OBFQDN/err.log" >> $CWD/ob.apache.conf
echo "LogLevel warn" >> $CWD/ob.apache.conf
echo "CustomLog /var/log/apache2/$OBFQDN/access.log combined" >> $CWD/ob.apache.conf
echo "php_value upload_max_filesize 1000M" >> $CWD/ob.apache.conf
echo "php_value post_max_size 1010M" >> $CWD/ob.apache.conf
echo "php_value short_open_tag On" >> $CWD/ob.apache.conf
echo "</VirtualHost>" >> $CWD/ob.apache.conf

echo ""
echo ""
echo "Assuming your DNS is configured, you should be able adjust ob.apache.conf to your system"
echo "and copy it to the appropriate spot for your machine, restart apache, and browse to"
echo "http://$OBFQDN to access your system.  Login in with username admin and the password you"
echo "set for the OpenBroadcaster Admin user.  Enjoy."
