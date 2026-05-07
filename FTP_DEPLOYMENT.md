# FTP Deployment

Herold is deployed to shared hosting via FTPS. There are two deployment methods: automated via GitHub Actions (on release tags) and manual via a local script.

## Prerequisites

### Remote Server

- PHP 8.5 with extensions: pdo_sqlite, mbstring, xml
- Apache with `DocumentRoot` pointing to `public/`
- HTTPS enabled (required for MediaRecorder API)
- SSH access (for running migrations)

### Local (for manual deployment)

- Docker (running — used for `composer install` and `npm run build`)
- [lftp](https://lftp.yar.ru/) installed locally

## Server Setup (One-Time)

1. Place a `.env` file in the application root on the server (the directory that `FTP_BASE_PATH` points to). This file is never overwritten by deployments.

2. Upload an empty marker file `.herold-deploy-root` to the same directory. This marker is a safety guard: deployment aborts if it is missing, preventing `mirror --delete` from wiping an unintended directory when `FTP_BASE_PATH` is misconfigured.

3. Ensure the following directories exist and are writable by Apache:
   - `storage/app/private/audio/`
   - `storage/framework/cache/`
   - `storage/framework/sessions/`
   - `storage/framework/views/`
   - `storage/logs/`
   - `database/data/`

4. Run initial migrations via SSH:
   ```
   php artisan migrate --force
   ```

## Configuration

### GitHub Actions (automated)

FTP credentials are stored as GitHub repository secrets/variables:

| Name            | Type     | Description                        |
|-----------------|----------|------------------------------------|
| `FTP_HOST`      | Secret   | FTP server hostname                |
| `FTP_USER`      | Secret   | FTP username                       |
| `FTP_PASSWORD`  | Secret   | FTP password                       |
| `FTP_BASE_PATH` | Variable | Remote path to application root    |
| `ENABLE_FTP_DEPLOY` | Variable | Set to `true` to enable deployment |

### Local script

Add FTP credentials to your `.env` file:

```
FTP_HOST=...
FTP_USER=...
FTP_PASSWORD=...
FTP_BASE_PATH=...
```

## Deploying

### Automated (GitHub Actions)

Push a version tag to trigger the deploy workflow:

```
git tag v1.0.0
git push origin v1.0.0
```

The workflow (`.github/workflows/deploy.yml`) runs only when `ENABLE_FTP_DEPLOY` is set to `true`.

### Manual (local)

```
./scripts/deploy.sh
```

This will:

1. Run `composer install --no-dev` inside the Docker app container
2. Run `npm run build` inside the Docker node container
3. Upload everything via FTPS using `lftp mirror --reverse --delete`

### After deployment

If the release includes database migrations, run them via SSH:

```
php artisan migrate --force
```

## What Gets Uploaded

Everything except:

| Excluded                  | Reason                                      |
|---------------------------|---------------------------------------------|
| `.git/`, `.github/`       | Version control, not needed on server       |
| `.claude/`, `.opencode/`  | AI tool config                              |
| `node_modules/`, `.vite/` | Build tools, not needed at runtime          |
| `.env`                    | Manually managed on server, never overwritten |
| `database/data/`          | SQLite database lives on server             |
| `storage/app/private/`    | User uploads (audio files) live on server   |
| `storage/logs/`           | Server-side logs                            |
| `storage/framework/sessions/`, `storage/framework/cache/` | Runtime data |
| `tests/`, `phpunit.xml`, `vitest.config.ts` | Test infrastructure |
| `Dockerfile`, `docker-compose.yml`, `docker-entrypoint.sh` | Local dev only |
| `package.json`, `package-lock.json`, `vite.config.ts` | Build config, not runtime |
| `docs/`, `prompts/`, `scripts/`, `backups/` | Documentation and tooling |
| `CLAUDE.md`, `AGENTS.md`, `DESIGN.md`, `README.md` | Project docs |
| `adr/`, `poc-ui/`, `icons/`, `srs/`, `sh/` | Development artifacts |

Notably, `vendor/` and `public/build/` **are** uploaded — the server has no Composer or Node.js.

## Backups

Before deploying, consider running a backup:

```
./scripts/backup.sh
```

This creates a timestamped zip in `backups/` containing the SQLite database, audio recordings, and `.env`. See the script for details.
