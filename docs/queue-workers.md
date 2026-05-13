# Queue Workers

Wayfinder's native queue supports `sync`, `database`, `redis`, and `beanstalkd` connections. Use `sync` only for local development and tests; production workers should run under Supervisor, systemd, or a similar process manager.

## Local Sync Queue

`sync` executes jobs immediately in the same request or command process. It is the right default for local development and automated tests because it needs no worker process.

```env
QUEUE_CONNECTION=sync
```

Do not use `sync` for production background work. A slow job will block the request that dispatched it.

## Worker Options

Use bounded workers so your process manager can recycle them predictably:

```sh
php wayfinder queue:work \
  --connection=database \
  --queue=default \
  --tries=3 \
  --timeout=60 \
  --memory=128 \
  --max-jobs=500 \
  --max-time=3600
```

Recommended meanings:

- `--tries=3`: release and retry a failed job until attempts are exhausted.
- `--timeout=60`: stop a single job that runs too long.
- `--memory=128`: stop the worker after memory usage reaches the limit in MB.
- `--max-jobs=500`: recycle after bounded work.
- `--max-time=3600`: recycle after one hour.

## Database Queue

Use the database queue for simple production deployments where adding Redis is not worth the operational cost yet.

```env
QUEUE_CONNECTION=database
```

Create the tables before running workers:

```sh
php wayfinder make:queue-table
php wayfinder make:failed-jobs-table
php wayfinder migrate --force
```

Supervisor example:

```ini
[program:wayfinder-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/wayfinder queue:work --connection=database --queue=default --tries=3 --timeout=60 --memory=128 --max-jobs=500 --max-time=3600
directory=/var/www/app
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=90
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/queue-default.log
```

## Redis Queue

Use Redis when queue throughput or latency matters more than keeping infrastructure minimal.

```env
QUEUE_CONNECTION=redis
```

Supervisor example:

```ini
[program:wayfinder-queue-redis]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/wayfinder queue:work --connection=redis --queue=default --tries=3 --timeout=60 --memory=256 --max-jobs=1000 --max-time=3600
directory=/var/www/app
user=www-data
numprocs=4
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=90
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/queue-redis.log
```

## Named Queues

Run separate worker programs for named queues when jobs have different urgency or resource profiles.

```ini
[program:wayfinder-queue-emails]
command=php /var/www/app/wayfinder queue:work --connection=database --queue=emails --tries=3 --timeout=60 --memory=128 --max-jobs=500 --max-time=3600
directory=/var/www/app
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=90
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/queue-emails.log

[program:wayfinder-queue-imports]
command=php /var/www/app/wayfinder queue:work --connection=database --queue=imports --tries=2 --timeout=300 --memory=256 --max-jobs=100 --max-time=3600
directory=/var/www/app
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=360
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/queue-imports.log
```

After deploying new code, restart workers so they pick up the new release:

```sh
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart wayfinder-queue-default:*
```
