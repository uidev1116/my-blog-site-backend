import axiosClient from '../../../lib/axios';
import type {
  BlockMenuItem,
  CustomClassItem,
  ImageSizesItem,
  FontSizeItem,
  FontFamilyItem,
  EditorSettings,
} from '../types/index';

const fetchSettings = async (): Promise<EditorSettings> => {
  const endpoint = ACMS.Library.acmsLink(
    {
      tpl: 'ajax/edit/block-editor/settings.json',
      bid: ACMS.Config.bid,
      cid: ACMS.Config.cid,
    },
    false
  );
  const {
    data: options = {
      features: {
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
      blockMenus: [] as BlockMenuItem[],
      fontSize: [] as FontSizeItem[],
      fontFamily: [] as FontFamilyItem[],
      customClass: [] as CustomClassItem[],
      imageSizes: [] as ImageSizesItem[],
      colorPalette: [] as string[],
    },
  } = await axiosClient.get<EditorSettings>(endpoint, {
    responseType: 'json',
  });
  return options;
};

export { fetchSettings };
