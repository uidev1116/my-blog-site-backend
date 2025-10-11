import { type google, Loader, type LoaderOptions } from 'google-maps';

let googlePromise: Promise<google> | undefined;

const defaultOptions: LoaderOptions = {
  region: ACMS.Config.s2dRegion || 'JP',
};

/**
 * Google Maps APIを読み込む
 * @param {string} apiKey
 * @param {LoaderOptions} options
 * @return {Promise<google>}
 */
const loadGoogleMap = (apiKey: string, options: LoaderOptions = {}) => {
  if (googlePromise) {
    return googlePromise;
  }

  const loader = new Loader(apiKey, { ...defaultOptions, ...options });
  googlePromise = loader.load();
  return googlePromise;
};

export default loadGoogleMap;
