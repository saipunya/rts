# Third-party components

RTS Demo includes unmodified Windows x64 binary distributions of PHP and MariaDB, Microsoft Visual C++ Redistributable, and the existing Dompdf dependency tree. PHP is used as the local web server. MariaDB is a separate local database process; it is not installed as a Windows service.

Exact runtime versions, official download URLs and SHA256 checksums are recorded in `runtime-lock.json` in the program directory. The Microsoft redistributable is additionally checked for a valid Microsoft Authenticode signature by the installer before execution. The RTS installer itself is not code signed.

- PHP: https://www.php.net/ — PHP license and bundled notices are in `runtime/php`.
- MariaDB: https://mariadb.org/ — GPLv2 and other bundled notices are in `runtime/mariadb/COPYING`, `THIRDPARTY`, and the upstream distribution. The corresponding upstream source archive for the packaged version is included in `licenses/sources`. It can be extracted with a gzip/tar utility. Original upstream source: https://archive.mariadb.org/mariadb-11.4.13/source/mariadb-11.4.13.tar.gz .
- Microsoft Visual C++ Redistributable: https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist — separately licensed Microsoft runtime. The original signed installer is included in `runtime/vc_redist.x64.exe` and installs under its own terms. Redistribution rights must be checked by the distributor against its Microsoft license before commercial shipment.
- Dompdf, php-font-lib, php-svg-lib, Masterminds HTML5 and Sabberworm: package source and license notices are retained in `app/vendor`.
- Bootstrap 5.3.2/5.3.3, Bootstrap Icons 1.11.3, Popper 2.11.8, AOS 2.3.1 and Chart.js 4.4.1: MIT license, copies in this directory. JavaScript/CSS banners are retained.
- Lucide 0.278.0 and 0.468.0: ISC license, copy in this directory.
- Sarabun and Kanit: SIL Open Font License 1.1, copies in this directory.

The CDN asset source URLs and hashes are in `app/assets/offline/sources.json`. The listed third-party licenses remain applicable to their respective files; this file does not assign a license to the RTS application.
