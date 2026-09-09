#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
build_stamp=$(date '+%Y%m%d-%H%M%S')
release_root="$project_root/release"
build_dir="$release_root/tuning-bodrbo-$build_stamp"
archive_path="$release_root/tuning-bodrbo-beget-$build_stamp.zip"

mkdir -p "$build_dir/assets" "$release_root"

cp "$project_root/index.html" "$build_dir/"
cp "$project_root/projects.html" "$build_dir/"
cp "$project_root/project.html" "$build_dir/"
cp "$project_root/motors.html" "$build_dir/"
cp "$project_root/motor.php" "$build_dir/"
cp "$project_root/404.html" "$build_dir/"
cp "$project_root/styles.css" "$build_dir/"
cp "$project_root/script.js" "$build_dir/"
cp "$project_root/motors.js" "$build_dir/"
cp "$project_root/motor-page.js" "$build_dir/"
cp "$project_root/submit-request.php" "$build_dir/"
cp "$project_root/marine-rocket-catalog.php" "$build_dir/"
cp "$project_root/motor-sitemap.php" "$build_dir/"
cp "$project_root/integration-config.example.php" "$build_dir/"
cp "$project_root/projects-data.js" "$build_dir/"
cp "$project_root/project-page.js" "$build_dir/"
cp "$project_root/robots.txt" "$build_dir/"
cp "$project_root/sitemap.xml" "$build_dir/"
cp "$project_root/.htaccess" "$build_dir/"

find "$project_root/assets" -maxdepth 1 -type f ! -name 'boatswain-face.png' -exec cp {} "$build_dir/assets/" \;
cp -R "$project_root/assets/projects" "$build_dir/assets/"

(cd "$build_dir" && zip -q -r "$archive_path" .)

printf '%s\n' "$build_dir"
printf '%s\n' "$archive_path"
