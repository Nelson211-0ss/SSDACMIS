# VPS Pull Guide

Use these commands on your VPS after you push changes to GitHub.

## If the project already exists on VPS

```bash
# 1) Go to project folder
cd /path/to/your/project

# 2) Check branch and status (optional but recommended)
git status
git branch

# 3) Pull latest code from GitHub
git pull origin <your-branch>
# Example:
# git pull origin main
```

## If this is the first deployment on VPS

```bash
git clone https://github.com/<username>/<repo>.git
cd <repo>
```

## Restart app after pull (if needed)

```bash
# PM2 example
pm2 restart all
# or
pm2 restart <app-name>
```

## Quick one-liner for updates (existing project)

```bash
cd /path/to/your/project && git pull origin main && pm2 restart <app-name>
```
