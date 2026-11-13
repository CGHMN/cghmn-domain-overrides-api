#!/bin/sh
# Download up-to-date domain name override lists from CGHMN DNS server
# and load them into dnsmasq if they are newer than a locally cached version
#
# Call like so via cron or systemd-timer:
#
# update-cghmn-dns-list.sh <target-conf-file> <server[:<[port>]]> [<filter=value>] [...]
# update-cghmn-dns-list.sh /etc/dnsmasq-cghmn.conf 100.64.11.19 allowed_types=dead,tld denied_domains=aol.com
#
# and add the following to your dnsmasq.conf file:
# conf-file=/etc/dnsmasq-cghmn.conf

set -eu

# command line arguments
TARGET_FILE="${1}"; shift
HOST="${1}"; shift

# assemble final list url from the remaining command line arguments
IFS='&' url="http://${HOST}/lists/filtered?as=dnsmasq${*:+&}${*}"

# define and set exit handler
trap 'cleanup; trap - EXIT; exit' EXIT INT HUP TERM
cleanup() {
	if [ -e "${tmp_file:-}" ]; then
		rm "${tmp_file}"
	fi
}

# download configuration file to temporary location
tmp_file="$(mktemp)"
if command -v wget >/dev/null 2>&1; then
	wget -q -O "${tmp_file}" "${url}"
elif command -v curl >/dev/null 2>&1; then
	curl --silent --output "${tmp_file}" "${url}"
else
	echo "Neither curl nor wget was found in \$PATH, make sure one of them is available" >&2
	exit 1
fi

# get last update timestamp from downloaded file
upstream_last_update="$(awk '/^# Last Update Timestamp: /{ print $5 }' "${tmp_file}")"

# do nothing if the existing configuration file is the same age or newer than the downloaded file
if [ -e "${TARGET_FILE}" ] && [ "${upstream_last_update}" -le "$(stat -c %Y "${TARGET_FILE}")" ]; then
	exit 0
fi

# ensure sbin directories are in search path to find dnsmasq
export PATH="${PATH}:/sbin:/usr/sbin:/usr/local/sbin"

# ensure downloaded file is a valid dnsmasq configuration
if ! dnsmasq --test --conf-file="${tmp_file}" >/dev/null 2>&1; then
	echo "Downloaded configuration file is not valid" >&2
	exit 1
fi

# move downloaded file to final target location
mv "${tmp_file}" "${TARGET_FILE}"

# restart dnsmasq
if command -v systemctl >/dev/null 2>&1; then
	systemctl reload dnmasq.service
elif command -v service >/dev/null 2>&1; then
	service dnsmasq reload
elif [ -x /etc/init.d/dnsmasq ]; then
	/etc/init.d/dnsmasq reload
else
	echo "Unsure how to reload dnsmasq" >&2
	exit 1
fi
