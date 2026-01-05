#!/bin/bash
set -e

echo "=== PDF Service Setup ==="
echo ""

# Create service user
if ! id -u pdf-service > /dev/null 2>&1; then
    useradd -r -s /usr/sbin/nologin -M pdf-service
    echo "✓ Created user: pdf-service"
else
    echo "✓ User pdf-service already exists"
fi

# Create temp directory
mkdir -p /tmp/pdf-service
chown pdf-service:pdf-service /tmp/pdf-service
chmod 700 /tmp/pdf-service
echo "✓ Created temp directory: /tmp/pdf-service"

# Create log directory (readable by all users)
mkdir -p /opt/pdf-service/logs
chown pdf-service:pdf-service /opt/pdf-service/logs
chmod 755 /opt/pdf-service/logs
touch /opt/pdf-service/logs/service.log
touch /opt/pdf-service/logs/stdout.log
touch /opt/pdf-service/logs/stderr.log
chown pdf-service:pdf-service /opt/pdf-service/logs/*.log
chmod 644 /opt/pdf-service/logs/*.log
echo "✓ Created log directory: /opt/pdf-service/logs"

# Set permissions
chown -R pdf-service:pdf-service /opt/pdf-service
chmod 755 /opt/pdf-service
chmod 644 /opt/pdf-service/index.php
echo "✓ Set permissions"

# Generate API key if needed
if grep -q "your-secret-api-key" /opt/pdf-service/index.php; then
    API_KEY=$(openssl rand -hex 32)
    sed -i "s/your-secret-api-key-min-32-chars/$API_KEY/" /opt/pdf-service/index.php
    echo ""
    echo "✓ Generated API key:"
    echo "  $API_KEY"
    echo ""
    echo "  ⚠️  SAVE THIS KEY! Add to EspoCRM config: data/config.php"
    echo "  'pdfServiceApiKey' => '$API_KEY',"
    echo ""
fi

# Install systemd service
cp /opt/pdf-service/pdf-service.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable pdf-service
systemctl restart pdf-service

echo ""
echo "✓ Service installed and started"
echo ""

# Check status
sleep 2
if systemctl is-active --quiet pdf-service; then
    echo "✓ Service is running"
    
    # Test health endpoint
    echo ""
    echo "Testing service..."
    curl -s http://127.0.0.1:8888/health | python3 -m json.tool 2>/dev/null || echo "Health check OK"
else
    echo "✗ Service failed to start"
    systemctl status pdf-service --no-pager
    exit 1
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Service URL: http://127.0.0.1:8888"
echo "Log files:"
echo "  - Application log: /opt/pdf-service/logs/service.log"
echo "  - Stdout log: /opt/pdf-service/logs/stdout.log"
echo "  - Stderr log: /opt/pdf-service/logs/stderr.log"
echo ""
echo "View logs:"
echo "  tail -f /opt/pdf-service/logs/service.log"
echo ""