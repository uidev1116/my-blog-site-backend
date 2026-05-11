import { mergeConfig } from './utils';
import { AcmsPathSegments } from './types';
import acmsPath from './acmsPath';
import parseAcmsPath from './parseAcmsPath';

interface AcmsPathHelper {
  acmsPath: typeof acmsPath;
  parseAcmsPath: typeof parseAcmsPath;
}

export default function createAcmsPathHelper(segments?: AcmsPathSegments): AcmsPathHelper {
  return {
    acmsPath: (paramsOrCtx, options = {}) => acmsPath(paramsOrCtx, mergeConfig({ segments }, options)),
    parseAcmsPath: (path, options = {}) => parseAcmsPath(path, mergeConfig({ segments }, options)),
  };
}
