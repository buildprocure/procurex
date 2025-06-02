#!/bin/sh

# Start Site24x7 Data Exporter in background
/opt/site24x7/S247DataExporter/bin/S247DataExporterDaemon start

# Start Apache in foreground
apache2-foreground
