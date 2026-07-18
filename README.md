php artisan queue:work --queue=default --tries=2
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monokek-api/artisan queue:work --queue=default --tries=2
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ton-projet/storage/logs/worker.log
stopwaitsecs=3600


# Relire les fichiers de configuration de Supervisor
sudo supervisorctl reread

# Activer les nouveaux changements
sudo supervisorctl update

# Vérifier le statut de tes workers
sudo supervisorctl status
php artisan queue:restart
