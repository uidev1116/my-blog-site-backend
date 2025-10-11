import { useCallback, useState, useEffect, useRef, useMemo } from 'react';
import { HexColorPicker } from 'react-colorful';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { ColorButton } from './ColorButton';
import { Toolbar } from '../../ui/Toolbar';
import { Icon } from '../../ui/Icon';

export type ColorPickerProps = {
  color?: string;
  onChange?: (color: string) => void;
  onClear?: () => void;
};

const isHex = (v: string) => /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v);

export const ColorPicker = ({ color, onChange, onClear }: ColorPickerProps) => {
  const { colorPalette } = useSettingsContext();
  // 入力欄の表示値（不正でも保持）
  const [colorInputValue, setColorInputValue] = useState(color ?? '');
  // 直近の「有効だった色」（ピッカーの表示に使う）
  const lastValidColorRef = useRef<string>(isHex(color ?? '') ? (color as string) : '#000000');

  // 外部から color が変わったら同期
  useEffect(() => {
    if (typeof color === 'string') {
      setColorInputValue(color);
      if (isHex(color)) lastValidColorRef.current = color;
    }
  }, [color]);

  // 入力中：表示は更新、値が有効になったら即 onChange
  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const next = e.target.value.trim();
      setColorInputValue(next);
      if (isHex(next)) {
        lastValidColorRef.current = next;
        onChange?.(next);
      }
    },
    [onChange]
  );

  // フォーカスアウト：有効でなければ元に戻す
  const handleInputBlur = useCallback(() => {
    if (!isHex(colorInputValue)) {
      // 無効 → 表示だけ直近有効色へ戻す
      setColorInputValue(lastValidColorRef.current);
      return;
    }
    // 有効 → 念のため同期
    onChange?.(colorInputValue);
  }, [colorInputValue, onChange]);

  // ピッカー変更：常に有効な16進で渡してくれるので即時反映
  const handlePickerChange = useCallback(
    (hex: string) => {
      lastValidColorRef.current = hex;
      setColorInputValue(hex);
      onChange?.(hex);
    },
    [onChange]
  );

  // パレット選択
  const handlePalettePick = useCallback(
    (hex: string) => {
      lastValidColorRef.current = hex;
      setColorInputValue(hex);
      onChange?.(hex);
    },
    [onChange]
  );

  // リセット
  const handleClear = useCallback(() => {
    setColorInputValue('');
    onClear?.();
  }, [onClear]);

  // ピッカーに渡す色（常に有効な値）
  const pickerColor = useMemo(() => {
    return isHex(colorInputValue) ? colorInputValue : lastValidColorRef.current;
  }, [colorInputValue]);

  return (
    <div className="acms-admin-block-editor-text-menu-color-picker-container">
      <HexColorPicker
        className="acms-admin-block-editor-text-menu-color-picker-input"
        color={pickerColor}
        onChange={handlePickerChange}
      />
      <input
        type="text"
        className="acms-admin-block-editor-text-menu-color-picker-text-input"
        placeholder="#000000"
        value={colorInputValue}
        onChange={handleInputChange}
        onBlur={handleInputBlur}
        aria-label="カラーコード（#RRGGBB または #RGB）"
      />
      <div className="acms-admin-block-editor-text-menu-color-picker-palette">
        {colorPalette?.map((currentColor) => (
          <ColorButton
            active={currentColor === color}
            color={currentColor}
            key={currentColor}
            onColorChange={handlePalettePick}
          />
        ))}
        <Toolbar.Button type="button" tooltip="Reset color to default" onClick={handleClear} aria-label="色をリセット">
          <Icon name="undo" />
        </Toolbar.Button>
      </div>
    </div>
  );
};
