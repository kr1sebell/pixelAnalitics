#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$ROOT_DIR"

git init
git add .
git commit -m "Initial commit"

echo "Добавьте удалённый репозиторий: git remote add origin <url>"
echo "Затем выполните git push -u origin main"
