# HeirLuxury - Budget Deployment Guide

**Hetzner VPS + Cloudflare — self-managed, ~$5.50/month**

---

## Cost Breakdown

| Service | Cost | Purpose |
|---------|------|---------|
| Hetzner CX22 | €3.79/month | 2 vCPU, 4GB RAM, 40GB SSD |
| Cloudflare | Free | DNS, CDN, SSL |
| Domain | ~$12/year | Your domain |
| **TOTAL** | **~€4.79/month + domain** | **~$70/year** |

No Forge. No GCS. No MySQL. SQLite + pre-generated thumbnails + Cloudflare edge caching.

---

## Architecture

```
Local dev machine                          Production (Hetzner CX22)
┌──────────────────────┐                   ┌──────────────────────────┐
│ Raw imports (~100GB)  │                   │ Laravel app              │
│ ↓ thumbnails:generate │   rsync/deploy   │ SQLite database          │
│ Thumbnails (~4GB)     │ ─────────────→   │ Thumbnails only (~4GB)   │
│ SQLite database       │                   │ Nginx + PHP-FPM          │
└──────────────────────┘                   └────────────┬─────────────┘
                                                        │
                                           ┌────────────▼─────────────┐
                                           │ Cloudflare (CDN + SSL)   │
                                           │ Caches thumbnails at edge│
                                           │ Free SSL certificates    │
                                           └──────────────────────────┘
```

Raw product images stay on your dev machine. Only optimized WebP thumbnails
(card 400x300, gallery 800x800, thumb 96x96) are deployed to the server.

---

## Prerequisites

- [ ] Domain registered on Cloudflare
- [ ] Hetzner Cloud account
- [ ] GitHub repository with your code
- [ ] SSH key pair on your local machine
- [ ] All thumbnails pre-generated locally (`php artisan thumbnails:generate`)

---

## Step 1: Prepare Locally

### 1.1 Pre-generate All Thumbnails

This creates optimized WebP images for every product. Run on your dev machine
where the raw imports live:

```bash
cd C:\Users\simon\Dev\Laravel\HeirLuxury
php artisan thumbnails:generate
```

This takes a while (~470K images). The output goes to `storage/app/public/thumbnails/`.

### 1.2 Push Code to GitHub

```bash
git add .
git commit -m "Prepare for production deployment"
git push origin main
```

The `.gitignore` already excludes `imports/` and `thumbnails/` — thumbnails are
synced separately via rsync (Step 7).

---

## Step 2: Create Hetzner Server

### 2.1 Sign Up

1. Go to https://www.hetzner.com/cloud
2. Create account, verify email, add payment

### 2.2 Create Server

1. Click "Add Server"
2. Configure:
   - **Location**: Nuremberg (EU) or Ashburn (US) — closest to your users
   - **Image**: Ubuntu 24.04
   - **Type**: Shared vCPU → **CX22** (2 vCPU, 4GB RAM, 40GB SSD) — €3.79/month
   - **SSH Key**: Add your public key
   - **Name**: `heirluxury`
3. Click "Create & Buy Now"

Copy the server IP address once it's ready.

---

## Step 3: Provision the Server

SSH in and install everything:

```bash
ssh root@YOUR_SERVER_IP
```

### 3.1 Create Deploy User

```bash
adduser deploy --disabled-password --gecos ""
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Allow deploy user to restart services without password
echo "deploy ALL=(ALL) NOPASSWD: /usr/sbin/service php8.3-fpm reload, /usr/sbin/service nginx reload" > /etc/sudoers.d/deploy
```

### 3.2 Install PHP 8.3 + Extensions

```bash
apt update && apt upgrade -y
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y \
    php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-sqlite3 php8.3-gd php8.3-zip \
    php8.3-bcmath php8.3-intl php8.3-opcache \
    nginx git unzip curl
```

### 3.3 Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### 3.4 Install Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

### 3.5 Configure PHP-FPM

```bash
cat > /etc/php/8.3/fpm/pool.d/heirluxury.conf << 'EOF'
[heirluxury]
user = deploy
group = deploy
listen = /run/php/heirluxury.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500

php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.max_accelerated_files] = 10000
php_admin_value[opcache.validate_timestamps] = 0
EOF

service php8.3-fpm restart
```

### 3.6 Configure Nginx

```bash
cat > /etc/nginx/sites-available/heirluxury << 'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /home/deploy/heirluxury/public;

    index index.php;
    charset utf-8;

    # Thumbnail images — aggressive caching
    location /storage/thumbnails/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    # Static assets — long cache
    location ~* \.(css|js|svg|woff2?|ttf|eot|ico)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/heirluxury.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/heirluxury /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && service nginx reload
```

### 3.7 Enable Swap (safety net for 4GB RAM)

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

### 3.8 Set Up Firewall

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

---

## Step 4: Deploy the Application

### 4.1 Clone Repository

```bash
su - deploy
git clone git@github.com:YOUR_USERNAME/heirluxury.git /home/deploy/heirluxury
cd /home/deploy/heirluxury
```

### 4.2 Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 4.3 Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_NAME="HeirLuxury"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=sqlite
DB_DATABASE=/home/deploy/heirluxury/database/database.sqlite

FILESYSTEM_DISK=public

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 4.4 Set Up Database

```bash
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
```

Or copy your local SQLite database directly:

```bash
# From your local machine:
scp database/database.sqlite deploy@YOUR_SERVER_IP:/home/deploy/heirluxury/database/
```

### 4.5 Storage Symlink & Permissions

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### 4.6 Cache Config

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## Step 5: Configure Cloudflare

### 5.1 DNS Records

In Cloudflare dashboard → your domain → DNS → Records:

| Type | Name | Content | Proxy | TTL |
|------|------|---------|-------|-----|
| A | @ | YOUR_SERVER_IP | Proxied (orange) | Auto |
| CNAME | www | yourdomain.com | Proxied (orange) | Auto |

### 5.2 SSL/TLS

1. Go to SSL/TLS → Overview
2. Set mode: **Full (strict)**

   For Full (strict) to work, you need a valid cert on your origin server.
   The easiest way: use a Cloudflare Origin Certificate.

3. Go to SSL/TLS → Origin Server → Create Certificate
4. Generate a certificate (default 15 years is fine)
5. Copy the certificate and private key

On your server:

```bash
sudo mkdir -p /etc/ssl/cloudflare
sudo nano /etc/ssl/cloudflare/cert.pem    # Paste certificate
sudo nano /etc/ssl/cloudflare/key.pem     # Paste private key
sudo chmod 600 /etc/ssl/cloudflare/key.pem
```

Update nginx to serve HTTPS:

```bash
sudo nano /etc/nginx/sites-available/heirluxury
```

Add this server block above the existing one:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /home/deploy/heirluxury/public;

    ssl_certificate /etc/ssl/cloudflare/cert.pem;
    ssl_certificate_key /etc/ssl/cloudflare/key.pem;

    # ... (same location blocks as the port 80 server)
}
```

```bash
sudo nginx -t && sudo service nginx reload
```

### 5.3 Edge Settings

In Cloudflare dashboard:

- SSL/TLS → Edge Certificates → Always Use HTTPS: **On**
- SSL/TLS → Edge Certificates → Minimum TLS: **1.2**
- Speed → Optimization → Auto Minify: **CSS, JS**
- Caching → Configuration → Browser Cache TTL: **1 month**

### 5.4 Cache Rules for Thumbnails

Go to Rules → Page Rules (or Cache Rules):

**Rule: Cache product thumbnails**
- URL: `yourdomain.com/storage/thumbnails/*`
- Cache Level: Cache Everything
- Edge Cache TTL: 1 month
- Browser Cache TTL: 1 month

This means Cloudflare serves cached thumbnails from its edge network — your
VPS barely gets hit for image requests after the first load.

---

## Step 6: Create Deploy Script

On the server, create a deploy script:

```bash
cat > /home/deploy/deploy.sh << 'SCRIPT'
#!/bin/bash
set -e

cd /home/deploy/heirluxury

echo "Pulling latest code..."
git pull origin main

echo "Installing dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "Building assets..."
npm ci && npm run build

echo "Running migrations..."
php artisan migrate --force

echo "Caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "Restarting PHP-FPM..."
sudo service php8.3-fpm reload

echo "Deploy complete!"
SCRIPT

chmod +x /home/deploy/deploy.sh
```

### Deploy workflow:

```bash
# From your local machine, after pushing to main:
ssh deploy@YOUR_SERVER_IP '~/deploy.sh'
```

Or set up a GitHub webhook for auto-deploy (optional, see Appendix A).

---

## Step 7: Sync Thumbnails

Thumbnails are not in git (too large). Sync them separately with rsync.

### First-time full sync (from your dev machine):

```bash
rsync -avz --progress \
    storage/app/public/thumbnails/ \
    deploy@YOUR_SERVER_IP:/home/deploy/heirluxury/storage/app/public/thumbnails/
```

### After adding products or regenerating thumbnails:

```bash
rsync -avz --progress --delete \
    storage/app/public/thumbnails/ \
    deploy@YOUR_SERVER_IP:/home/deploy/heirluxury/storage/app/public/thumbnails/
```

The `--delete` flag removes thumbnails on the server that no longer exist locally
(e.g., deleted products).

---

## Step 8: Verify

### 8.1 Test the site

1. Visit `https://yourdomain.com`
2. Check:
   - [ ] Homepage loads
   - [ ] Category pages show products with thumbnails
   - [ ] Product detail pages show gallery images
   - [ ] Search works
   - [ ] Mobile layout is correct
   - [ ] Contact form sends emails

### 8.2 Performance

- https://pagespeed.web.dev/ — target 90+
- https://gtmetrix.com/ — target Grade A

Cloudflare edge caching means repeat visitors load thumbnails from the nearest
CDN node, not your VPS. First-visit performance depends on server location.

### 8.3 Monitor

```bash
# Check disk usage (should be well under 40GB)
df -h

# Check memory
free -h

# Check nginx logs
tail -f /var/log/nginx/error.log
```

---

## Daily Workflow

```
1. Make changes locally
2. If product images changed → php artisan thumbnails:generate
3. git add . && git commit -m "..." && git push
4. ssh deploy@SERVER '~/deploy.sh'
5. If thumbnails changed → rsync thumbnails (Step 7)
```

---

## Troubleshooting

### 500 Error After Deploy

```bash
ssh deploy@YOUR_SERVER_IP
cd ~/heirluxury
php artisan config:clear
php artisan cache:clear
tail -50 storage/logs/laravel.log
```

### Broken/Missing Images

Thumbnails weren't synced. Run the rsync command from Step 7.
If a specific product is broken, check that it has a thumbnail:

```bash
ls storage/app/public/thumbnails/card/<brand-section-gender>/<product-folder>/
```

### Out of Disk Space

```bash
df -h
du -sh storage/app/public/thumbnails/  # Check thumbnail size
```

If thumbnails grow too large, consider pruning unused sizes or compressing further.

### Database Issues

```bash
# Check SQLite file
ls -la database/database.sqlite

# Verify data
php artisan tinker --execute="echo App\Models\Product::count();"
```

---

## Appendix A: GitHub Webhook Auto-Deploy (Optional)

Create a simple webhook endpoint to auto-deploy on push:

1. Install webhook tool on server:

```bash
sudo apt install -y webhook
```

2. Create webhook config:

```bash
cat > /home/deploy/hooks.json << 'JSON'
[
  {
    "id": "deploy",
    "execute-command": "/home/deploy/deploy.sh",
    "command-working-directory": "/home/deploy/heirluxury",
    "pass-arguments-to-command": [],
    "trigger-rule": {
      "match": {
        "type": "payload-hmac-sha256",
        "secret": "YOUR_WEBHOOK_SECRET",
        "parameter": {
          "source": "header",
          "name": "X-Hub-Signature-256"
        }
      }
    }
  }
]
JSON
```

3. Run webhook listener:

```bash
webhook -hooks /home/deploy/hooks.json -port 9000 &
```

4. In GitHub → repo → Settings → Webhooks → Add:
   - URL: `http://YOUR_SERVER_IP:9000/hooks/deploy`
   - Secret: `YOUR_WEBHOOK_SECRET`
   - Events: Just the push event

Remember to open port 9000 in ufw: `sudo ufw allow 9000/tcp`

---

## Appendix B: Backups

### SQLite Database

Add a cron job to back up the database daily:

```bash
crontab -e
```

```
0 3 * * * cp /home/deploy/heirluxury/database/database.sqlite /home/deploy/backups/database-$(date +\%Y\%m\%d).sqlite && find /home/deploy/backups/ -name "*.sqlite" -mtime +7 -delete
```

This keeps 7 days of daily backups.

### Full Server Snapshot

Hetzner offers server snapshots at €0.012/GB/month. Take one after initial setup
and periodically:

1. Hetzner Console → Server → Snapshots → Create Snapshot
2. Cost: ~€0.50/month for a 40GB snapshot
