Name: billions-payments-for-billmanager
Version: %{pkg_version}
Release: 1%{?dist}
Summary: Official Billions.works payment gateway for BILLmanager
License: MIT
URL: https://github.com/billions-works/billions-payments-for-billmanager
BuildArch: noarch
Requires: php-cli >= 7.4, php-xml, php-mbstring
%global debug_package %{nil}

%description
Billions.works native Public API payment module for BILLmanager.

%install
install -Dm755 %{source_root}/paymethods/pmbillions %{buildroot}/usr/local/mgr5/paymethods/pmbillions
install -Dm755 %{source_root}/cgi/billionspayment.php %{buildroot}/usr/local/mgr5/cgi/billionspayment.php
install -Dm755 %{source_root}/cgi/billionsresult.php %{buildroot}/usr/local/mgr5/cgi/billionsresult.php
install -Dm644 %{source_root}/etc/xml/billmgr_mod_pmbillions.xml %{buildroot}/usr/local/mgr5/etc/xml/billmgr_mod_pmbillions.xml
install -Dm644 %{source_root}/src/Billions.php %{buildroot}/usr/local/mgr5/include/php/billions-bridge/Billions.php
install -Dm644 %{source_root}/src/Manager.php %{buildroot}/usr/local/mgr5/include/php/billions-bridge/Manager.php
install -Dm644 %{source_root}/LICENSE %{buildroot}/usr/share/licenses/billions-payments-for-billmanager/LICENSE

%files
/usr/local/mgr5/paymethods/pmbillions
/usr/local/mgr5/cgi/billionspayment.php
/usr/local/mgr5/cgi/billionsresult.php
/usr/local/mgr5/etc/xml/billmgr_mod_pmbillions.xml
/usr/local/mgr5/include/php/billions-bridge/Billions.php
/usr/local/mgr5/include/php/billions-bridge/Manager.php
%license /usr/share/licenses/billions-payments-for-billmanager/LICENSE
