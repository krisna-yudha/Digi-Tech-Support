import api from '../services/api';

/**
 * Normalisasi URL dengan protokol http/https
 */
export function normalizeUrl(input) {
  let url = (input || '').trim();
  if (!url) return '';
  if (!/^https?:\/\//i.test(url)) {
    url = 'https://' + url;
  }
  return url;
}

/**
 * Ekstrak domain dari URL
 */
export function extractDomain(url) {
  try {
    const parsed = new URL(normalizeUrl(url));
    return parsed.hostname.replace(/^www\./i, '');
  } catch {
    return url || '';
  }
}

/**
 * Dapatkan fallback favicon Google S2 beresolusi tinggi (128px)
 */
export function getGoogleFavicon(url) {
  try {
    const domain = extractDomain(url);
    if (!domain) return '';
    return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=128`;
  } catch {
    return '';
  }
}

/**
 * Scraping icon dan metadata link dari backend dengan fallback ke Google S2
 */
export async function scrapeLinkMetadata(rawUrl) {
  const normalized = normalizeUrl(rawUrl);
  if (!normalized) {
    return null;
  }

  const fallbackDomain = extractDomain(normalized);
  const fallbackIcon = getGoogleFavicon(normalized);

  try {
    const res = await api.get('/scrape-link-icon', {
      params: { url: normalized }
    });

    if (res.data?.success && res.data?.data) {
      return {
        url: res.data.data.url || normalized,
        domain: res.data.data.domain || fallbackDomain,
        iconUrl: res.data.data.icon_url || fallbackIcon,
        title: res.data.data.title || null,
        categorySuggestion: res.data.data.category_suggestion || null
      };
    }
  } catch (err) {
    // Jika backend offline atau timeout, gunakan fallback Google S2
  }

  return {
    url: normalized,
    domain: fallbackDomain,
    iconUrl: fallbackIcon,
    title: null,
    categorySuggestion: null
  };
}
