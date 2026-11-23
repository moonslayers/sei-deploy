# Runbook consola terminal en prod

Operator: `uguess` con sudo. Comandos tmux SOLO como www-data: `sudo -H -u www-data ...`. Nunca `chsh`. PrivateTmp=no (systemd) → socket en `/tmp/tmux-33` (uid 33). `/tmp/tmux-33` es 700 www-data → `Permission denied` esperado si inspeccionas como uguess.

## Pre-deploy
```bash
sudo -H -u www-data tmux -V
sudo -H -u www-data id
ls -ld /tmp/tmux-33 2>/dev/null; ls -ld storage/app/terminal
sudo -H -u www-data bash -c 'cd /opt/sei-deploy && php artisan view:clear'
```
Verificar `storage/app/terminal` owner www-data WRITABLE.

## Limpiar artefactos debug
```bash
for L in wwwtest ""; do
  sudo -H -u www-data tmux -L wwwtest kill-server 2>/dev/null
done
sudo -H -u www-data tmux kill-server 2>/dev/null
rm -f /tmp/tmux-www.conf /tmp/www-test
find storage/app/terminal -maxdepth 1 -name '*.log' -size 0 -delete
# preservar un .log de ejemplo si se desea para smoke test
```

## Deploy
Self-deploy via webhook o manual; incluye view:clear? No — self-deploy NO corre view:clear (AGENTS). Correr manual si síntoma de terminal rota:
```bash
sudo -H -u www-data bash -c 'cd /opt/sei-deploy && php artisan view:clear && php artisan config:clear'
```

## Post-deploy
1. Crear sesión desde UI `/admin` → Console.
2. Inspeccionar como www-data:
```bash
sudo -H -u www-data tmux -S /tmp/tmux-33/default ls
sudo -H -u www-data tmux -S /tmp/tmux-33/default capture-pane -pt <sei-uuid>
```
3. Log >0 bytes:
```bash
ls -l storage/app/terminal/<uuid>.log
tail -n 20 storage/app/terminal/<uuid>.log
```
4. Polling 200 con output:
```bash
curl -s -o /dev/null -w "%{http_code}" "https://host/sei/terminal/<uuid>/output?offset=0"
```
5. Sin sesiones test colgadas:
```bash
sudo -H -u www-data tmux ls | grep -E 'test-bash|wwwtest' || echo OK
```

## Pendiente tras cierre Tarea 1
Ajustar paths/flags del socket aislado (`-f/-L/-S`) según interfaz final de TerminalService; hoy asume `/tmp/tmux-33/default`.