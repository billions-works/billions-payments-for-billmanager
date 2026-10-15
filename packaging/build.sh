#!/bin/sh
set -eu

TARGET=${1:?Usage: build.sh ubuntu20|ubuntu24|debian12|debian13|el9 VERSION}
VERSION=${2:?Version required}
case "$VERSION" in *[!0-9.]*|'') echo 'Version must contain only digits and dots' >&2; exit 1;; esac
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
mkdir -p "$ROOT/dist"

case "$TARGET" in
  ubuntu20|ubuntu24|debian12|debian13)
    STAGE=$(mktemp -d)
    trap 'rm -rf "$STAGE"' EXIT HUP INT TERM
    mkdir -p "$STAGE/DEBIAN" "$STAGE/usr/local/mgr5/paymethods" \
      "$STAGE/usr/local/mgr5/cgi" "$STAGE/usr/local/mgr5/etc/xml" \
      "$STAGE/usr/local/mgr5/include/php/billions-bridge"
    install -m 755 "$ROOT/paymethods/pmbillions" "$STAGE/usr/local/mgr5/paymethods/pmbillions"
    install -m 755 "$ROOT/cgi/billionspayment.php" "$STAGE/usr/local/mgr5/cgi/billionspayment.php"
    install -m 755 "$ROOT/cgi/billionsresult.php" "$STAGE/usr/local/mgr5/cgi/billionsresult.php"
    install -m 644 "$ROOT/etc/xml/billmgr_mod_pmbillions.xml" "$STAGE/usr/local/mgr5/etc/xml/billmgr_mod_pmbillions.xml"
    install -m 644 "$ROOT/src/Billions.php" "$STAGE/usr/local/mgr5/include/php/billions-bridge/Billions.php"
    install -m 644 "$ROOT/src/Manager.php" "$STAGE/usr/local/mgr5/include/php/billions-bridge/Manager.php"
    mkdir -p "$STAGE/usr/share/doc/billions-payments-for-billmanager"
    install -m 644 "$ROOT/LICENSE" "$STAGE/usr/share/doc/billions-payments-for-billmanager/copyright"
    printf 'Package: billions-payments-for-billmanager\nVersion: %s\nSection: web\nPriority: optional\nArchitecture: all\nDepends: php-cli (>= 7.4), php-xml, php-mbstring\nMaintainer: Billions.works <support@billions.works>\nDescription: Official Billions.works payment gateway for BILLmanager\n' "$VERSION" > "$STAGE/DEBIAN/control"
    dpkg-deb --build --root-owner-group "$STAGE" "$ROOT/dist/billions-billmanager-$TARGET.deb"
    ;;
  el9)
    rpmbuild -bb "$ROOT/packaging/billions.spec" \
      --define "pkg_version $VERSION" \
      --define "source_root $ROOT" \
      --define "_topdir $ROOT/dist/rpmbuild" \
      --define "_rpmdir $ROOT/dist" \
      --define "dist .el9" \
      --define "_build_id_links none"
    mv "$ROOT/dist/noarch/billions-payments-for-billmanager-$VERSION-1.el9.noarch.rpm" \
      "$ROOT/dist/billions-billmanager-el9.noarch.rpm"
    ;;
  *) echo "Unknown target: $TARGET" >&2; exit 1;;
esac
