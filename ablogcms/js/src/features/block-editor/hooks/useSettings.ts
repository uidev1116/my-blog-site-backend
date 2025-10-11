import useSWRImmutable from 'swr/immutable';
import { fetchSettings } from '@features/block-editor/api';
import type { EditorSettings } from '@features/block-editor/types';
import { settingCacheKey } from '@features/block-editor/lib/utils/settingCacheKey';

async function fetcher() {
  const data = await fetchSettings();
  return data;
}

export function useSettings(): EditorSettings {
  const { data } = useSWRImmutable<EditorSettings>(settingCacheKey, fetcher);

  return {
    features: data?.features || {
      textItalic: false,
      textUnderline: false,
      textStrike: false,
      textCode: false,
      textMarker: false,
      textColor: false,
      fontSize: false,
      fontFamily: false,
      textSubscript: false,
      textSuperscript: false,
      customClass: false,
      tableBgColor: false,
    },
    blockMenus: data?.blockMenus || [],
    fontSize: data?.fontSize || [],
    fontFamily: data?.fontFamily || [],
    customClass: data?.customClass || [],
    imageSizes: data?.imageSizes || [],
    colorPalette: data?.colorPalette || [],
  };
}
