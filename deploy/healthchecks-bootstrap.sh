#!/bin/bash
set -euo pipefail

EMAIL="${OMA_HC_ADMIN_EMAIL:-admin@oma-fizjo.pl}"
PASSWORD="${OMA_HC_ADMIN_PASSWORD:?podaj OMA_HC_ADMIN_PASSWORD}"
TZ_NAME="${OMA_HC_TZ:-Europe/Warsaw}"

if ! docker compose exec -T healthchecks ./manage.py shell -c "
import sys
from django.contrib.auth.models import User

sys.exit(0 if User.objects.filter(email='$EMAIL').exists() else 1)
" >/dev/null 2>&1; then
    docker compose exec -T healthchecks ./manage.py createsuperuser --email "$EMAIL" --password "$PASSWORD" >/dev/null
    echo "utworzono konto $EMAIL"
fi

docker compose exec -T healthchecks ./manage.py shell -c "
from datetime import timedelta

from django.contrib.auth.models import User
from hc.accounts.models import Project
from hc.api.models import Check

JOBS = [
    ('expired-carts', 'Sylius — porzucone koszyki', 'period', 24 * 60, 120),
    ('db-backup', 'Kopia bazy MySQL', 'cron', '0 3 * * *', 60),
    ('unpaid-orders', 'Sylius — nieopłacone zamówienia', 'period', 24 * 60, 120),
]

user = User.objects.get(email='$EMAIL')
project = Project.objects.filter(owner=user).first() or Project.objects.create(owner=user, name='OMA')

if not project.ping_key:
    project.set_ping_key()
    project.save()

for slug, name, kind, spec, grace_minutes in JOBS:
    check = Check.objects.filter(project=project, slug=slug).first() or Check(project=project, slug=slug)
    check.name = name
    check.grace = timedelta(minutes=grace_minutes)

    if kind == 'cron':
        check.kind = 'cron'
        check.schedule = spec
        check.tz = '$TZ_NAME'
    else:
        check.kind = 'simple'
        check.timeout = timedelta(minutes=spec)
        check.schedule = '* * * * *'

    check.save()

Check.objects.filter(project=project, slug='my-first-check', last_ping=None).delete()

print('PING_URL=http://healthchecks:8000/ping/' + project.ping_key)
" 2>&1 | grep '^PING_URL='
