#!/bin/sh

# Only run daemon if explicitly needed
if [ "$SITE24X7_ENABLE" = "true" ] && [ -x /opt/site24x7/S247DataExporter/bin/S247DataExporterDaemon ]; then
  /opt/site24x7/S247DataExporter/bin/S247DataExporterDaemon start
fi

# Start Apache
apache2-foreground
