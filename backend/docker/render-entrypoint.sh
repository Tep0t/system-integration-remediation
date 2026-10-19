#!/bin/sh
set -eu

port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        printf '%s\n' 'PORT must be a valid TCP port number.' >&2
        exit 1
        ;;
esac

if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    printf '%s\n' 'PORT must be between 1 and 65535.' >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s|<VirtualHost \\*:10000>|<VirtualHost *:${port}>|" /etc/apache2/sites-available/000-default.conf

printf '%s\n' 'Running Laravel database migrations...'
if ! php artisan migrate --force; then
    printf '%s\n' 'Laravel database migrations failed; Apache will not start.' >&2
    exit 1
fi

printf '%s\n' 'Laravel database migrations completed.'
exec "$@"
