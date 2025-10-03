#!/bin/bash

# Vertragsverwaltung Release Builder
# Creates a production-ready ZIP package for webhosting deployment

set -e

VERSION="1.0.0"
RELEASE_NAME="vertragsverwaltung-v${VERSION}"
RELEASE_DIR="/tmp/${RELEASE_NAME}"
CURRENT_DIR="$(pwd)"

echo "🚀 Building Vertragsverwaltung Release v${VERSION}"
echo "=================================================="

# Clean up any existing release
rm -rf "${RELEASE_DIR}"
rm -f "${CURRENT_DIR}/${RELEASE_NAME}.zip"

# Create release directory
mkdir -p "${RELEASE_DIR}"

echo "📦 Copying files..."

# Copy all files except excluded ones
cp -r "${CURRENT_DIR}"/* "${RELEASE_DIR}/" 2>/dev/null || true
cp -r "${CURRENT_DIR}"/.htaccess "${RELEASE_DIR}/" 2>/dev/null || true

# Remove excluded files and directories
while IFS= read -r line; do
    # Skip comments and empty lines
    [[ "$line" =~ ^#.*$ ]] && continue
    [[ -z "$line" ]] && continue
    
    # Remove trailing slash and leading/trailing whitespace
    line=$(echo "$line" | sed 's:/*$::' | xargs)
    
    # Remove file/directory if it exists
    if [ -e "${RELEASE_DIR}/${line}" ]; then
        rm -rf "${RELEASE_DIR}/${line}"
    fi
done < "${CURRENT_DIR}/.release-exclude"

# Ensure required directories exist and have correct permissions
echo "📁 Creating required directories..."
mkdir -p "${RELEASE_DIR}/storage/"{cache,logs,sessions,uploads}
mkdir -p "${RELEASE_DIR}/config"

# Set proper permissions (files: 644, directories: 755, writable: 775)
echo "🔒 Setting file permissions..."
find "${RELEASE_DIR}" -type d -exec chmod 755 {} \;
find "${RELEASE_DIR}" -type f -exec chmod 644 {} \;
chmod 775 "${RELEASE_DIR}/storage"/{cache,logs,sessions,uploads}
chmod 775 "${RELEASE_DIR}/public/uploads"

# Ensure .htaccess files are included and protected
echo "🛡️  Securing .htaccess files..."
chmod 644 "${RELEASE_DIR}/.htaccess"
if [ -f "${RELEASE_DIR}/storage/.htaccess" ]; then
    chmod 644 "${RELEASE_DIR}/storage/.htaccess"
fi

# Create empty .htaccess for storage directories if not exists
cat > "${RELEASE_DIR}/storage/.htaccess" << 'EOF'
# Deny all access to storage directory
Require all denied

<Files "*.log">
    Require all denied
</Files>
EOF

cat > "${RELEASE_DIR}/config/.htaccess" << 'EOF'
# Deny all access to config directory
Require all denied

<Files "*.php">
    Require all denied
</Files>
EOF

# Create VERSION file
echo "${VERSION}" > "${RELEASE_DIR}/VERSION"
echo "$(date '+%Y-%m-%d %H:%M:%S')" > "${RELEASE_DIR}/BUILD_DATE"

# Add release-specific files
echo "📄 Adding release documentation..."
cat > "${RELEASE_DIR}/README-RELEASE.txt" << 'EOF'
VERTRAGSVERWALTUNG v1.0.0
=========================

🎉 Herzlichen Glückwunsch! Sie haben erfolgreich die Vertragsverwaltung heruntergeladen.

SCHNELLE INSTALLATION:
1. Alle Dateien in das Web-Root Ihres Hostings hochladen
2. Browser öffnen und zu Ihrer Domain navigieren
3. Automatischer Installations-Wizard startet
4. 6 einfache Schritte befolgen
5. Fertig! 🚀

SYSTEMANFORDERUNGEN:
- PHP 8.1+ mit Extensions: pdo_mysql, sodium, intl, mbstring, fileinfo
- MySQL 8.0+ oder MariaDB 10.5+
- Apache Webserver mit mod_rewrite

DETAILLIERTE ANLEITUNG:
Siehe INSTALLATION.md für vollständige Installationsanweisungen.

SUPPORT:
- GitHub: https://github.com/fksnet/vertragsverwaltung
- Issues: Bug-Reports via GitHub Issues

Viel Erfolg mit Ihrer neuen Vertragsverwaltung! 📋
EOF

# Remove create-release.sh from the package
rm -f "${RELEASE_DIR}/create-release.sh"

echo "🗜️  Creating ZIP archive..."
cd /tmp
zip -r "${RELEASE_NAME}.zip" "${RELEASE_NAME}/" > /dev/null

# Move to project directory
mv "/tmp/${RELEASE_NAME}.zip" "${CURRENT_DIR}/"

# Cleanup
rm -rf "${RELEASE_DIR}"

echo "✅ Release package created successfully!"
echo "📁 File: ${RELEASE_NAME}.zip"
echo "📊 Size: $(du -h "${CURRENT_DIR}/${RELEASE_NAME}.zip" | cut -f1)"
echo ""
echo "🚀 Ready for deployment!"
echo "   Users can now extract this ZIP file to their web root"
echo "   and the installation wizard will automatically start."