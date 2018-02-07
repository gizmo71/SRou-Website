# SimRacing.org.uk Website

This repository contains the SimRacing.org.uk website code, including UKGPL, but excluding the SMF forum software itself,
which should be unpacked into the [`smf` directory](public_html.srou/www/smf/).

## Database

The forum and league management software require a database whose setup is beyond the current scope of this repository.

## Web server

There are no particular dependencies on any specific web server (various flavours of Apache HTTPD have been used).
The current hosting arrangement uses HAProxy to offload HTTPS processing, so the backend servers only deal with HTTP calls.

The webserver must, however, set some environment variables which are used by the software to avoid hardcoding things like
paths, hostnames, usernames and so on in the code itself:
```ApacheConf
<Directory "/srv/www/SimRacing.org.uk/public_html*">
    Require all granted

    DirectoryIndex index.html index.php
    DirectoryIndexRedirect on

    SetEnv SROU_HOST_WWW www.simracing.org.uk
    SetEnv SROU_HOST_DOWNLOAD downloads.simracing.org.uk
    SetEnv SROU_HOST_REPLAY replay.simracing.org.uk
    SetEnv SROU_HOST_UKGPL www.ukgpl.com
    SetEnv SROU_DB_HOST an.internal.host.name
    SetEnv SROU_DB_PREFIX some_random_prefix_
    SetEnv SROU_ROOT /srv/www/SimRacing.org.uk
</Directory>
```

In addition, the most sensitive information such as passwords are stored in their own files in the [`cfg` directory](cfg).

As a consequence, changing certain SMF settings from the forum's own administrative pages tends to overwrite places
in [`Settings.php`](public_html.srou/www/smf/Settings.php) which are supposed to get values from the environment
with the values themselves. One must be careful when committing to restore such code to use the environment again.

There are also a number of sites driven from the same codebase which must be mapped appropriately. For example:
```ApacheConf
<VirtualHost *:80>
    DocumentRoot /srv/www/SimRacing.org.uk/public_html.srou/www
    Alias /smf/Themes/ukgpl /srv/www/SimRacing.org.uk/public_html.ukgpl/smf-theme
    Alias /downloads /srv/www/SimRacing.org.uk/public_html.srou/downloads
    ServerName www.simracing.org.uk
    ServerAlias simracing.org.uk
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot /srv/www/SimRacing.org.uk/public_html.srou/replays
    ScriptAlias /cgi-bin/ "/srv/www/SimRacing.org.uk/public_html.srou/replays/cgi-bin/"
    ServerName replays.simracing.org.uk
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot /srv/www/SimRacing.org.uk/public_html.srou/downloads
    ServerName downloads.simracing.org.uk
    RedirectMatch "^(.*)$" "https://www.simracing.org.uk/downloads$1"
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot /srv/www/SimRacing.org.uk/public_html.ukgpl
    ServerName www.ukgpl.com
    ServerAlias ukgpl.com

    RewriteEngine on

    RewriteCond %{HTTP_HOST} ^ukgpl.com$
    RewriteRule ^(.*)$ "https://www.ukgpl.com$1" [R=301,L]

    RedirectMatch "^/pages/(.*)\.php$" "/index.php/$1"
</VirtualHost>
```
