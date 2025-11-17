import { contrastColor } from '../utils';

/**
 * ブラウザネイティブのカラーピッカー（input type="color"）を初期化
 * テーマカラー選択時にナビゲーションバーの色をリアルタイムで反映
 */
export default function dispatchThemeColorPicker(context: Document | Element) {
  const colorInputs = context.querySelectorAll<HTMLInputElement>('.js-acms-theme-color-picker');

  if (colorInputs.length === 0) {
    return;
  }

  // テーマカラー更新用の要素を取得
  const navbar = document.querySelector<HTMLElement>('.acms-admin-navbar-admin');
  const fonts = document.querySelectorAll<HTMLElement>(
    '.acms-admin-icon-logo, .acms-admin-navbar-admin-nav > li > a, .acms-admin-navbar-admin-nav > li > button'
  );
  const profileIcons = document.querySelectorAll<HTMLElement>('.acms-admin-user-profile');

  colorInputs.forEach((colorInput) => {
    // カラー変更ハンドラー
    const handleColorChange = () => {
      const color = colorInput.value;

      // ナビゲーションバーのテーマカラーを更新
      if (navbar) {
        navbar.style.background = color;
      }
      profileIcons.forEach((profileIcon) => {
        profileIcon.style.border = `2px solid ${contrastColor(color, '#505050')}`;
      });
      fonts.forEach((font) => {
        font.style.color = contrastColor(color, '#505050');
      });
    };

    // input イベント（リアルタイム更新用）
    colorInput.addEventListener('input', handleColorChange);
    // change イベント（確定時の更新用）
    colorInput.addEventListener('change', handleColorChange);
  });
}
