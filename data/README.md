# GeDefense Local Sovereign GeoIP, ASN & Cartography Engine

**STATUS: DIAMANT VGT SUPREME**

This directory hosts the 100% sovereign, local BGP/ASN and GeoIP database used by GeDefense WP (`VIS_Geo_Resolver` and `threat-map.php`).

---

### 1. Data Provenance & Attribution

#### A. GeoIP & ASN Database (`data/geoip.csv`)
- **Primary Source**: [`iplocate/ip-address-databases`](https://github.com/iplocate/ip-address-databases) — Public Open Data IP-to-Country and IP-to-ASN Databases.
- **Concrete Verification Match**: Subnet `1.0.4.0/22` is assigned to ASN `38803` with organization name `Gtelecom Pty Ltd` (AU), strictly matching the upstream dataset.
- **Upstream License**: **Creative Commons Attribution-ShareAlike 4.0 International Public License (CC BY-SA 4.0)**.
- **Required Attribution**:
  > *This product includes IP-to-Country and IP-to-ASN data from the iplocate IP Address Databases project (https://github.com/iplocate/ip-address-databases), licensed under CC BY-SA 4.0.*
- **Data Structure**: Pre-indexed and sorted ascending by 32-bit IPv4 integer ranges:
  `start_ip,end_ip,country_code,country_name,latitude,longitude,asn,as_organization`

#### B. Mercator World Map SVG Vector Geometry (`world-map.svg.php`)
- **Vector Source**: [`themustafaomar/jsvectormap`](https://github.com/themustafaomar/jsvectormap)
- **Map File**: `world-merc.js` (v1.7.0, https://cdn.jsdelivr.net/npm/jsvectormap@1.7.0/dist/maps/world-merc.js)
- **License**: **MIT License** (Copyright (c) 2020 Mustafa Omar, https://github.com/themustafaomar/jsvectormap/blob/main/LICENSE).

---

### 2. Architecture & Privacy Guarantees
- **0 Cloud Leaks**: All GeoIP coordinates, ISO 3166-1 alpha-2 country codes, Autonomous System Numbers (ASN), and organization names are resolved locally from `geoip.csv` via high-speed binary `fseek()` file lookups (~0.25ms - 0.45ms resolution time).
- **Zero External Network Requests**: The server never queries external GeoIP APIs (such as ipinfo.io, ipstack, or commercial web services), guaranteeing complete operator and visitor privacy and GDPR / DSGVO compliance.
- **No Reverse DNS Stalls**: Lookups do not invoke network DNS queries or socket connections during request evaluation.

---

### 3. Canonical Discovery Priority
The Geo resolver (`VIS_Geo_Resolver::get_csv_path()`) searches the following paths in priority order:
1. `VIS_PATH . 'data/geoip.csv'` (Canonical plugin directory)
2. `dirname(__DIR__, 2) . '/data/geoip.csv'` (Relative plugin root fallback)
3. `WP_CONTENT_DIR . '/uploads/gedefense/geoip.csv'` (Sovereign persistent uploads directory)
4. `wp_upload_dir()['basedir'] . '/gedefense/geoip.csv'` (Dynamic WordPress upload directory)
5. Custom paths supplied via WordPress filter `vis_geoip_csv_paths`.
