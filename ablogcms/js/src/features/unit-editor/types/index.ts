import type { UnitAlignVersion, UnitMenuItem } from '../core/types';

// サイズオプション
export interface SizeOption {
  value: string;
  label: string;
}

/**
 * 設定型
 */
export interface UnitEditorSettings {
  unitGroup: {
    enable: boolean;
    options: Array<{
      value: string;
      label: string;
    }>;
  };
  groupUnit: {
    classOptions: Array<{
      value: string;
      label: string;
    }>;
    tagOptions: Array<{
      value: string;
      label: string;
    }>;
  };
  align: {
    version: UnitAlignVersion;
  };
  // 各ユニットタイプのサイズオプション
  sizeOptions: {
    image: SizeOption[];
    eximage: SizeOption[];
    media: SizeOption[];
    map: SizeOption[];
    video: SizeOption[];
    youtube: SizeOption[];
  };
  unitDefs: UnitMenuItem[];
}
