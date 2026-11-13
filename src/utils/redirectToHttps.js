/**
 * Some shared-hosting Nginx configurations serve static files without
 * evaluating .htaccess. The PHP API enforces HTTPS independently.
 */
export function redirectToHttps() {
  const { protocol, hostname, href } = window.location;
  if (protocol !== 'http:' || ['localhost', '127.0.0.1', '::1'].includes(hostname)) {
    return false;
  }

  const secureUrl = new URL(href);
  secureUrl.protocol = 'https:';
  window.location.replace(secureUrl.href);
  return true;
}
