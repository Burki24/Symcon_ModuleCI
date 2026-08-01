#!/usr/bin/env bash
set -euo pipefail

root="${GITHUB_WORKSPACE:-$(pwd)}"
count=0

while IFS= read -r -d '' file; do
    php -l "$file" > /dev/null
    count=$((count + 1))
done < <(
    find "$root" \
        -type d \( \
            -name .git -o \
            -name vendor -o \
            -name node_modules -o \
            -name build -o \
            -name dist \
        \) -prune -o \
        -type f -name '*.php' -print0
)

echo "PHP lint passed for ${count} file(s)."
