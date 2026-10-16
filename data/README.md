# GeDefense Local Sovereign GeoIP & ASN Database

**STATUS: DIAMANT VGT SUPREME**

This directory hosts the 100% sovereign, local BGP and GeoIP database used by GeDefense WP (`VIS_Geo_Resolver`).

### Architecture & Security Standards:
- **0 Cloud Leaks:** All GeoIP coordinates, ISO Country Codes, Autonomous System Numbers (ASN), and Organization names are resolved locally from `geoip.csv` via high-speed binary `fseek()` file lookups (~0.3ms resolution time).
- **Zero External Network Requests:** The server never queries external GeoIP APIs (such as ipinfo.io, ipstack, or maxmind web services), completely protecting operator and visitor privacy.
- **Fallback Discovery Priority:**
  1. `data/geoip.csv` (Plugin relative)
  2. `wp-content/uploads/gedefense/geoip.csv` (Sovereign Uploads directory)
  3. `C:/Users/Masterboard/Downloads/GeDefense-4.1.0-KINETIC-REWORK-RC/geoip.csv` (GeDefense Linux local bridge)
