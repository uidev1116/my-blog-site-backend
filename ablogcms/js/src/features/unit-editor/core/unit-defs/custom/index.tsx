import type { UnitAlignOption, UnitAlignVersion, UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import InplaceEdit from './inplace-edit';

const alignOptions: Record<UnitAlignVersion, UnitAlignOption[]> = {
  v1: [
    { value: 'center', label: ACMS.i18n('unit_editor.align.full') },
    { value: 'auto', label: ACMS.i18n('unit_editor.align.auto') },
  ],
  v2: [],
};

const Custom: UnitDefInterface = {
  type: 'custom',
  name: 'カスタム',
  icon: 'build',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  supports: {
    align: (version) => alignOptions[version],
    duplicate: (unit, editor) => {
      const element = editor.dom.querySelector<HTMLElement>(`[data-unit-id="${unit.id}"]`);
      if (element === null) {
        // ユニットの要素が取得できない場合は複製不可
        return false;
      }
      const input = element.querySelector<HTMLInputElement>(
        'input[name$=":extension"][value="image"], input[name$=":extension"][value="file"]'
      );
      if (input !== null) {
        // 画像フィールド or ファイルフィールドが含まれている場合は複製不可
        return false;
      }
      return true;
    },
    moveHierarchy: (unit, editor) => {
      const element = editor.dom.querySelector<HTMLElement>(`[data-unit-id="${unit.id}"]`);
      if (element === null) {
        // ユニットの要素が取得できない場合は階層移動不可
        return false;
      }
      const input = element.querySelector<HTMLInputElement>(
        'input[name$=":extension"][value="image"], input[name$=":extension"][value="file"]'
      );
      if (input !== null) {
        // 画像フィールド or ファイルフィールドが含まれている場合は階層移動不可
        return false;
      }
      return true;
    },
  },
};

export default Custom;
