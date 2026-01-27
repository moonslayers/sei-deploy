# Webhook Documentation for sei-deploy

This document explains how to configure and use the webhook API for deploying updates to sei-deploy and its submodules.

## Overview

The sei-deploy server provides two webhook endpoints:
1. **POST /api/webhook/submodule** - Updates the sei-compilados submodule
2. **POST /api/webhook/deploy** - Pulls updates from the main repository and updates submodules

All webhooks are secured using RSA signatures to ensure authenticity.

## Security: RSA Signature

Each webhook request must include a digital signature created with the private RSA key.

### Payload Format

```json
{
  "timestamp": 1737350000,
  "action": "update_submodule" | "update_deploy",
  "repository": "sei-compilados" | "sei-deploy",
  "branch": "main",
  "project": "pmr-frontend" | null
}
```

### Key Fields
- `timestamp`: Unix timestamp (must be within ±5 minutes of server time)
- `action`: Type of action being performed
- `repository`: Repository name (only "sei-compilados" for submodule endpoint)
- `branch`: Git branch (only "main" is supported)
- `project`: **(Optional)** Project folder name in sei-compilados to deploy to `/var/www/{project}`. If provided, after a successful git pull, the frontend files will be automatically deployed to `/var/www/{project}`. Example: "pmr-frontend", "sagem-frontend", "sidi-frontend".
- `signature`: Base64-encoded RSA-SHA256 signature of the entire payload

### Signature Algorithm

The signature must be created using **RSA-SHA256**:
1. Encode the entire JSON payload as a UTF-8 string
2. Sign the string using the private key with SHA-256 hashing
3. Encode the signature in Base64

### Header Options

The signature can be provided in two ways:
1. **Header**: `X-Webhook-Signature: <signature_base64>` (recommended)
2. **Body**: Include `signature` field in the JSON payload

## Generating RSA Keys

### Generate Key Pair (on your machine)

```bash
# Generate private key
openssl genrsa -out webhook_private.pem 2048

# Generate public key
openssl rsa -in webhook_private.pem -pubout -out webhook_public.pem

# Encode public key in Base64 for .env
base64 -i webhook_public.pem | tr -d '\n'
```

### Configure Server

Add the Base64-encoded public key to your `.env` file:

```env
PUBLIC_KEY=<your_base64_encoded_public_key>
```

### Configure GitHub Actions

Add the **private key** to your repository secrets:
1. Go to repository Settings > Secrets and variables > Actions
2. Add a new secret named `WEBHOOK_PRIVATE_KEY`
3. Paste the content of `webhook_private.pem`

**NEVER** commit the private key to your repository!

## GitHub Actions Example

### For sei-compilados Updates (Without Deployment)

```yaml
name: Trigger sei-deploy Update

on:
  push:
    branches:
      - main
    paths:
      - 'dist/**'

jobs:
  trigger-webhook:
    runs-on: ubuntu-latest
    steps:
      - name: Generate signature
        id: generate-signature
        run: |
          TIMESTAMP=$(date +%s)
          PAYLOAD='{"timestamp":'$TIMESTAMP',"action":"update_submodule","repository":"sei-compilados","branch":"main"}'

          SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -sign <(echo "${{ secrets.WEBHOOK_PRIVATE_KEY }}" | base64 -d) -binary | base64)

          echo "timestamp=$TIMESTAMP" >> $GITHUB_OUTPUT
          echo "payload=$PAYLOAD" >> $GITHUB_OUTPUT
          echo "signature=$SIGNATURE" >> $GITHUB_OUTPUT

      - name: Send webhook
        uses: fjogeleit/http-request-action@v1
        with:
          url: ${{ secrets.WEBHOOK_URL }}/api/webhook/submodule
          method: POST
          contentType: application/json
          data: ${{ steps.generate-signature.outputs.payload }}
          customHeaders: '{
            "X-Webhook-Signature": "${{ steps.generate-signature.outputs.signature }}"
          }'
```

### For sei-compilados Updates With Automatic Frontend Deployment

```yaml
name: Trigger sei-deploy Update

on:
  push:
    branches:
      - main
    paths:
      - 'dist/**'

jobs:
  trigger-webhook:
    runs-on: ubuntu-latest
    steps:
      - name: Generate signature
        id: generate-signature
        run: |
          TIMESTAMP=$(date +%s)
          PAYLOAD='{"timestamp":'$TIMESTAMP',"action":"update_submodule","repository":"sei-compilados","branch":"main","project":"pmr-frontend"}'

          SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -sign <(echo "${{ secrets.WEBHOOK_PRIVATE_KEY }}" | base64 -d) -binary | base64)

          echo "timestamp=$TIMESTAMP" >> $GITHUB_OUTPUT
          echo "payload=$PAYLOAD" >> $GITHUB_OUTPUT
          echo "signature=$SIGNATURE" >> $GITHUB_OUTPUT

      - name: Send webhook
        uses: fjogeleit/http-request-action@v1
        with:
          url: ${{ secrets.WEBHOOK_URL }}/api/webhook/submodule
          method: POST
          contentType: application/json
          data: ${{ steps.generate-signature.outputs.payload }}
          customHeaders: '{
            "X-Webhook-Signature": "${{ steps.generate-signature.outputs.signature }}"
          }'
```

### Alternative Using curl

```yaml
name: Trigger sei-deploy Update

on:
  push:
    branches:
      - main
    paths:
      - 'dist/**'

jobs:
  trigger-webhook:
    runs-on: ubuntu-latest
    steps:
      - name: Setup private key
        run: |
          echo "${{ secrets.WEBHOOK_PRIVATE_KEY }}" | base64 -d > /tmp/private_key.pem

      - name: Generate and send webhook
        run: |
          TIMESTAMP=$(date +%s)
          PAYLOAD='{"timestamp":'$TIMESTAMP',"action":"update_submodule","repository":"sei-compilados","branch":"main"}'
          
          SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -sign /tmp/private_key.pem -binary | base64)
          
          curl -X POST "${{ secrets.WEBHOOK_URL }}/api/webhook/submodule" \
            -H "Content-Type: application/json" \
            -H "X-Webhook-Signature: $SIGNATURE" \
            -d "$PAYLOAD"

      - name: Cleanup
        if: always()
        run: rm -f /tmp/private_key.pem
```

## API Endpoints

### POST /api/webhook/submodule

Updates sei-compilados submodule and optionally deploys frontend files to `/var/www/{project}`.

**Request (Without Deployment):**
```json
{
  "timestamp": 1737350000,
  "action": "update_submodule",
  "repository": "sei-compilados",
  "branch": "main"
}
```

**Request (With Deployment):**
```json
{
  "timestamp": 1737350000,
  "action": "update_submodule",
  "repository": "sei-compilados",
  "branch": "main",
  "project": "pmr-frontend"
}
```

**Response (Success - Without Deployment):**
```json
{
  "status": "success",
  "message": "Submodule updated successfully",
  "data": {
    "repository": "sei-compilados",
    "branch": "main",
    "output": ["git output lines..."]
  }
}
```

**Response (Success - With Deployment):**
```json
{
  "status": "success",
  "message": "Submodule updated and deployed successfully",
  "data": {
    "repository": "sei-compilados",
    "branch": "main",
    "project": "pmr-frontend",
    "git_output": ["git output lines..."],
    "deploy_output": {
      "deployment": "success",
      "project": "pmr-frontend",
      "source": "/path/to/sei-compilados/pmr-frontend",
      "destination": "/var/www/pmr-frontend"
    }
  }
}
```

**Response (Error - Project Folder Not Found):**
```json
{
  "error": "Project folder not found",
  "message": "Folder 'pmr-frontend' does not exist in sei-compilados"
}
```

**Response (Error - Destination Folder Not Found):**
```json
{
  "error": "Destination folder not found",
  "message": "Folder 'pmr-frontend' does not exist in /var/www"
}
```

**Response (Error):**
```json
{
  "status": "error",
  "message": "Failed to update submodule",
  "error": "error details..."
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Submodule updated successfully",
  "data": {
    "repository": "sei-compilados",
    "branch": "main",
    "output": ["git output lines..."]
  }
}
```

**Response (Error):**
```json
{
  "status": "error",
  "message": "Failed to update submodule",
  "error": "error details..."
}
```

### POST /api/webhook/deploy

Pulls updates from the main repository and updates all submodules.

**Request:**
```json
{
  "timestamp": 1737350000,
  "action": "update_deploy",
  "repository": "sei-deploy",
  "branch": "main"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Deploy updated successfully",
  "data": {
    "branch": "main",
    "main_pull": ["git output lines..."],
    "submodule_update": ["submodule update lines..."]
  }
}
```

### GET /api/status

Returns the current status of the repository and submodules.

**Response:**
```json
{
  "status": "success",
  "data": {
    "current_branch": "main",
    "git_status": ["status lines..."],
    "submodule_status": ["submodule status lines..."]
  }
}
```

## Error Codes

- **400 Bad Request**: Invalid payload, missing fields, or invalid JSON
- **400 Bad Request**: Project folder not found in sei-compilados
- **400 Bad Request**: Destination folder not found in /var/www
- **401 Unauthorized**: Invalid signature or timestamp
- **500 Internal Server Error**: Server error during execution

## Testing the Webhook

### Using curl

```bash
# Generate payload and signature (without deployment)
TIMESTAMP=$(date +%s)
PAYLOAD='{"timestamp":'$TIMESTAMP',"action":"update_submodule","repository":"sei-compilados","branch":"main"}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -sign webhook_private.pem -binary | base64)

# Send request
curl -X POST "http://localhost:8000/api/webhook/submodule" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

```bash
# Generate payload and signature (with deployment)
TIMESTAMP=$(date +%s)
PAYLOAD='{"timestamp":'$TIMESTAMP',"action":"update_submodule","repository":"sei-compilados","branch":"main","project":"pmr-frontend"}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -sign webhook_private.pem -binary | base64)

# Send request
curl -X POST "http://localhost:8000/api/webhook/submodule" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

## Troubleshooting

### Invalid Signature
- Verify the public key is correctly configured in `.env`
- Ensure the private key matches the public key
- Check that the signature is Base64-encoded

### Timestamp Error
- Verify server time is synchronized
- Ensure timestamp is within ±5 minutes of current time

### Git Authentication Error
- Check `GITHUB_USER` and `GITHUB_TOKEN` in `.env`
- Ensure the token has appropriate repository permissions
- Verify git credential helper is configured correctly
- Check that `GITHUB_REPO_MAIN` and `GITHUB_REPO_SUBMODULE` are in format `user/repo` (e.g., `moonslayers/sei-deploy`)

### Error: "refusing to merge unrelated histories"
- This occurs when merging repositories without common history
- The system automatically uses `--allow-unrelated-histories` flag in `updateSubmodule()`
- If the error persists, manually reset the submodule:
  ```bash
  cd /path/to/sei-deploy/sei-compilados
  git reset --hard HEAD
  git pull --allow-unrelated-histories https://github.com/user/repo.git main
  ```
- For a complete reset, use:
  ```bash
  cd /path/to/sei-deploy
  git submodule deinit -f sei-compilados
  rm -rf .git/modules/sei-compilados
  git submodule update --init --remote sei-compilados
  ```

### Error: "Committer identity unknown" / "Please tell me who you are"
- The system automatically configures git identity from `.env`
- Ensure `GIT_USER_NAME` and `GIT_USER_EMAIL` are configured
- Default values: "Deploy Bot" / "deploy@server.com"
- The system uses `--local` flag to avoid permission issues

### Error: "Permission denied" on git config file
- The system uses `--local` flag instead of `--global`
- This writes to `.git/config` instead of `/var/www/.gitconfig`
- No manual action needed - this is handled automatically in `ensureGitIdentity()`
- If using the command manually: use `git config --local` instead of `git config --global`

### Error: Webhook timeout (curl: (28) Failed to connect after X ms)
- The webhook is taking too long to respond (> 134 seconds in default curl)
- Possible causes: slow Git pull, large deployments, network issues
- Solutions:
  - Increase timeout in GitHub Actions workflow: add `--max-time 300` to curl
  - Check server logs: `php artisan pail` or `tail -f storage/logs/laravel.log`
  - Verify server is running: `systemctl status nginx` or `systemctl status apache2`
  - Test connection locally: `curl http://localhost/api/webhook/submodule`

### Error: "Failed to connect to server" / "Couldn't connect to server"
- Server not responding on the specified port
- Check if server is running:
  ```bash
  # For nginx
  sudo systemctl status nginx
  
  # For Apache
  sudo systemctl status apache2
  
  # For php artisan serve
  php artisan serve --host=0.0.0.0 --port=8000
  ```
- Check if port is open: `netstat -tlnp | grep :80`
- Check firewall rules
- Test connection from server: `curl http://localhost/api/webhook/submodule`

### Error: "repository 'https://github.com/...' not found"
- Git cannot find the specified repository
- Check repository format in `.env`:
  - Correct: `GITHUB_REPO_MAIN=moonslayers/sei-deploy`
  - Incorrect: `GITHUB_REPO_MAIN=sei-deploy` (missing user)
- Verify GitHub token has repository access permissions
- Test token manually:
  ```bash
  curl -H "Authorization: token YOUR_TOKEN" \
    https://api.github.com/repos/username/repo
  ```

### Error: "unable to access 'https://github.com/...': Failed to connect"
- Server cannot connect to GitHub
- Possible causes: corporate proxy, firewall blocking github.com, DNS issues
- Test connection: `ping github.com` and `curl -I https://github.com`
- Configure proxy if needed:
  ```bash
  git config --local http.proxy http://proxy.example.com:8080
  git config --local https.proxy https://proxy.example.com:8080
  ```
